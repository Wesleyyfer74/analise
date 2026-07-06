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

function asaas_request_with_key($method, $endpoint, $api_key, $payload = null) {
    if (!$api_key) {
        throw new Exception('Chave Asaas nao configurada.');
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

function app_secret_key() {
    $secret = env_value('APP_SECRET', '') ?: env_value('ADMIN_PASSWORD', '') ?: env_value('ASAAS_WEBHOOK_TOKEN', '');
    return hash('sha256', $secret !== '' ? $secret : 'studio-visagismo-local-key', true);
}

function encrypt_secret($value) {
    if ($value === null || $value === '') {
        return null;
    }
    $iv = random_bytes(16);
    $cipher = openssl_encrypt((string)$value, 'AES-256-CBC', app_secret_key(), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new Exception('Nao foi possivel proteger a chave da subconta.');
    }
    return base64_encode($iv . $cipher);
}

function decrypt_secret($value) {
    if (!$value) {
        return null;
    }
    $raw = base64_decode((string)$value, true);
    if ($raw === false || strlen($raw) <= 16) {
        return null;
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', app_secret_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

function owner_settings() {
    return db_fetch('SELECT * FROM owner_settings WHERE id = 1') ?: null;
}

function owner_split_config() {
    $settings = owner_settings();
    if (!$settings || empty($settings['wallet_id']) || ($settings['status'] ?? '') !== 'active') {
        return null;
    }
    $percent = (float)($settings['split_percent'] ?? 70);
    if ($percent <= 0 || $percent >= 100) {
        return null;
    }
    return [
        'wallet_id' => trim((string)$settings['wallet_id']),
        'percent' => $percent,
    ];
}

function asaas_create_owner_subaccount($data) {
    $cpf_cnpj = preg_replace('/\D+/', '', (string)($data['cpf_cnpj'] ?? ''));
    if ($cpf_cnpj === '') {
        throw new Exception('Informe o CPF ou CNPJ da dona do sistema.');
    }

    $payload = [
        'name' => trim((string)($data['name'] ?? '')),
        'email' => strtolower(trim((string)($data['email'] ?? ''))),
        'loginEmail' => strtolower(trim((string)($data['email'] ?? ''))),
        'cpfCnpj' => $cpf_cnpj,
        'mobilePhone' => preg_replace('/\D+/', '', (string)($data['mobile_phone'] ?? '')),
        'incomeValue' => (float)($data['income_value'] ?? 0),
        'address' => trim((string)($data['address'] ?? '')),
        'addressNumber' => trim((string)($data['address_number'] ?? '')),
        'complement' => trim((string)($data['complement'] ?? '')),
        'province' => trim((string)($data['province'] ?? '')),
        'postalCode' => preg_replace('/\D+/', '', (string)($data['postal_code'] ?? '')),
    ];

    if (!empty($data['phone'])) {
        $payload['phone'] = preg_replace('/\D+/', '', (string)$data['phone']);
    }
    if (strlen($cpf_cnpj) === 11 && !empty($data['birth_date'])) {
        $payload['birthDate'] = $data['birth_date'];
    }
    if (strlen($cpf_cnpj) === 14 && !empty($data['company_type'])) {
        $payload['companyType'] = $data['company_type'];
    }

    foreach (['name', 'email', 'cpfCnpj', 'mobilePhone', 'incomeValue', 'address', 'addressNumber', 'province', 'postalCode'] as $field) {
        if (($payload[$field] ?? '') === '' || $payload[$field] === 0.0) {
            throw new Exception('Preencha todos os dados obrigatorios da subconta Asaas.');
        }
    }

    $response = asaas_request('POST', 'accounts', $payload);
    $wallet_id = $response['walletId'] ?? null;
    $api_key = $response['apiKey'] ?? null;
    if (!$wallet_id) {
        throw new Exception('Asaas nao retornou o walletId da subconta.');
    }

    save_owner_settings([
        'name' => $payload['name'],
        'email' => $payload['email'],
        'cpf_cnpj' => $cpf_cnpj,
        'birth_date' => $data['birth_date'] ?? null,
        'company_type' => $data['company_type'] ?? null,
        'phone' => preg_replace('/\D+/', '', (string)($data['phone'] ?? '')),
        'mobile_phone' => $payload['mobilePhone'],
        'income_value' => $payload['incomeValue'],
        'address' => $payload['address'],
        'address_number' => $payload['addressNumber'],
        'complement' => $payload['complement'],
        'province' => $payload['province'],
        'postal_code' => $payload['postalCode'],
        'wallet_id' => $wallet_id,
        'api_key_encrypted' => encrypt_secret($api_key),
        'split_percent' => (float)($data['split_percent'] ?? 70),
        'status' => 'active',
        'last_error' => null,
        'raw_payload' => json_encode($response, JSON_UNESCAPED_UNICODE),
    ]);
    return $response;
}

function save_owner_settings($data) {
    $existing = owner_settings();
    $now = date('Y-m-d H:i:s');
    $fields = [
        'name', 'email', 'cpf_cnpj', 'birth_date', 'company_type', 'phone',
        'mobile_phone', 'income_value', 'address', 'address_number', 'complement',
        'province', 'postal_code', 'wallet_id', 'api_key_encrypted',
        'split_percent', 'status', 'last_error', 'raw_payload',
    ];
    $values = [];
    foreach ($fields as $field) {
        $values[] = $data[$field] ?? null;
    }

    if ($existing) {
        $set = implode(', ', array_map(fn($field) => "$field = ?", $fields));
        db_execute("UPDATE owner_settings SET $set, updated_at = ? WHERE id = 1", array_merge($values, [$now]));
        return;
    }

    db_execute(
        'INSERT INTO owner_settings (id, ' . implode(', ', $fields) . ', created_at, updated_at) VALUES (1, ' . implode(', ', array_fill(0, count($fields), '?')) . ', ?, ?)',
        array_merge($values, [$now, $now])
    );
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

    $owner_split = owner_split_config();
    $wallet = $owner_split['wallet_id'] ?? trim((string)env_value('ASAAS_PROJECT_WALLET_ID', ''));
    $percent = $owner_split['percent'] ?? (float)env_value('ASAAS_PROJECT_SPLIT_PERCENT', '0');
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

    $owner_share_value = $wallet !== '' && $percent > 0 ? round($amount * ($percent / 100), 2) : null;
    $platform_share_value = $owner_share_value !== null ? round($amount - $owner_share_value, 2) : $amount;

    db_execute(
        'INSERT INTO payments (user_id, asaas_payment_id, asaas_customer_id, status, billing_type, amount, due_date, invoice_url, pix_payload, pix_qr_code, split_wallet_id, owner_share_percent, owner_share_value, platform_share_value, raw_payload, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
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
            $wallet ?: null,
            $wallet !== '' ? $percent : null,
            $owner_share_value,
            $platform_share_value,
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
