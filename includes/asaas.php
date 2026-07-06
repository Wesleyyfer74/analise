<?php
require_once __DIR__ . '/auth.php';

function asaas_base_url() {
    return env_value('ASAAS_ENV', 'sandbox') === 'production'
        ? 'https://api.asaas.com/v3'
        : 'https://api-sandbox.asaas.com/v3';
}

function asaas_request($method, $endpoint, $payload = null) {
    $api_key = env_value('ASAAS_API_KEY');
    if (!$api_key) {
        throw new Exception('ASAAS_API_KEY nao configurada.');
    }
    $ch = curl_init(asaas_base_url() . '/' . ltrim($endpoint, '/'));
    $headers = [
        'Content-Type: application/json',
        'access_token: ' . $api_key,
        'User-Agent: StudioVisagismoIA/1.0',
    ];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
    $body = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($body === false || $error) {
        throw new Exception('Erro de comunicacao com Asaas: ' . $error);
    }
    $json = json_decode($body, true);
    if ($http < 200 || $http >= 300) {
        $message = is_array($json) ? ($json['errors'][0]['description'] ?? $json['message'] ?? $body) : $body;
        throw new Exception('Asaas HTTP ' . $http . ': ' . $message);
    }
    return is_array($json) ? $json : [];
}

function asaas_find_or_create_customer($user_id) {
    $user = db_fetch('SELECT u.*, cp.document, cp.asaas_customer_id FROM users u JOIN client_profiles cp ON cp.user_id = u.id WHERE u.id = ?', [$user_id]);
    if (!$user) {
        throw new Exception('Cliente nao encontrado.');
    }
    if (!empty($user['asaas_customer_id'])) {
        return $user['asaas_customer_id'];
    }

    $payload = [
        'name' => $user['name'],
        'email' => $user['email'],
        'mobilePhone' => preg_replace('/\D+/', '', (string)$user['phone']),
    ];
    if (!empty($user['document'])) {
        $payload['cpfCnpj'] = preg_replace('/\D+/', '', (string)$user['document']);
    }

    $customer = asaas_request('POST', 'customers', $payload);
    $customer_id = $customer['id'] ?? null;
    if (!$customer_id) {
        throw new Exception('Asaas nao retornou o ID do cliente.');
    }
    db_execute('UPDATE client_profiles SET asaas_customer_id = ? WHERE user_id = ?', [$customer_id, $user_id]);
    return $customer_id;
}

function asaas_create_subscription_payment($user_id) {
    $customer_id = asaas_find_or_create_customer($user_id);
    $amount = (float)env_value('SUBSCRIPTION_PRICE', '97.00');
    $due_date = date('Y-m-d', strtotime('+1 day'));

    $payload = [
        'customer' => $customer_id,
        'billingType' => 'PIX',
        'value' => $amount,
        'dueDate' => $due_date,
        'description' => 'Assinatura Studio Visagismo IA - 30 dias',
        'externalReference' => 'client:' . $user_id . ':' . bin2hex(random_bytes(4)),
    ];

    $wallet = trim((string)env_value('ASAAS_PROJECT_WALLET_ID', ''));
    $percent = (float)env_value('ASAAS_PROJECT_SPLIT_PERCENT', '0');
    if ($wallet !== '' && $percent > 0) {
        $payload['split'] = [[
            'walletId' => $wallet,
            'percentualValue' => $percent,
        ]];
    }

    $payment = asaas_request('POST', 'payments', $payload);
    $payment_id = $payment['id'] ?? null;
    if (!$payment_id) {
        throw new Exception('Asaas nao retornou o ID da cobranca.');
    }

    $pix_payload = null;
    $pix_qr = null;
    try {
        $pix = asaas_request('GET', 'payments/' . rawurlencode($payment_id) . '/pixQrCode');
        $pix_payload = $pix['payload'] ?? null;
        $pix_qr = $pix['encodedImage'] ?? null;
    } catch (Throwable $e) {
        debug_log('asaas.pix_qr_failed', ['message' => $e->getMessage()], 'WARNING');
    }

    db_execute(
        'INSERT INTO payments (user_id, asaas_payment_id, asaas_customer_id, status, billing_type, amount, due_date, invoice_url, pix_payload, pix_qr_code, raw_payload, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $user_id,
            $payment_id,
            $customer_id,
            strtolower($payment['status'] ?? 'pending'),
            'PIX',
            $amount,
            $due_date,
            $payment['invoiceUrl'] ?? null,
            $pix_payload,
            $pix_qr,
            json_encode($payment, JSON_UNESCAPED_UNICODE),
            date('Y-m-d H:i:s'),
        ]
    );

    return db_fetch('SELECT * FROM payments WHERE asaas_payment_id = ?', [$payment_id]);
}

function asaas_balance() {
    return asaas_request('GET', 'finance/balance');
}

function activate_client_subscription($payment_id, $payment_payload = []) {
    $payment = db_fetch('SELECT * FROM payments WHERE asaas_payment_id = ?', [$payment_id]);
    if (!$payment || empty($payment['user_id'])) {
        return false;
    }
    $user_id = (int)$payment['user_id'];
    $days = env_int('SUBSCRIPTION_DAYS', 30, 1, 365);
    $quota = env_int('MONTHLY_TOKEN_QUOTA', 30, 1, 10000);
    $current = client_profile($user_id);
    $base = $current && subscription_active($current)
        ? strtotime($current['subscription_expires_at'])
        : time();
    $expires = date('Y-m-d H:i:s', $base + ($days * 86400));

    db_execute(
        'UPDATE client_profiles SET subscription_status = ?, subscription_expires_at = ?, tokens_remaining = ?, monthly_token_quota = ? WHERE user_id = ?',
        ['active', $expires, $quota, $quota, $user_id]
    );
    db_execute(
        'UPDATE payments SET status = ?, net_value = ?, paid_at = ?, raw_payload = ?, updated_at = ? WHERE asaas_payment_id = ?',
        [
            'paid',
            $payment_payload['netValue'] ?? $payment['net_value'],
            date('Y-m-d H:i:s'),
            json_encode($payment_payload, JSON_UNESCAPED_UNICODE),
            date('Y-m-d H:i:s'),
            $payment_id,
        ]
    );
    return true;
}
