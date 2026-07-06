<?php
require_once dirname(__DIR__, 2) . '/includes/asaas.php';
start_secure_session();

function only_digits($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function has_repeated_digits($digits) {
    return preg_match('/^(\d)\1+$/', $digits) === 1;
}

function valid_cpf($cpf) {
    $cpf = only_digits($cpf);
    if (strlen($cpf) !== 11 || has_repeated_digits($cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int)$cpf[$i] * (($t + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int)$cpf[$t] !== $digit) {
            return false;
        }
    }
    return true;
}

function valid_cnpj($cnpj) {
    $cnpj = only_digits($cnpj);
    if (strlen($cnpj) !== 14 || has_repeated_digits($cnpj)) {
        return false;
    }

    $weights = [
        [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
        [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2],
    ];

    for ($step = 0; $step < 2; $step++) {
        $sum = 0;
        foreach ($weights[$step] as $i => $weight) {
            $sum += (int)$cnpj[$i] * $weight;
        }
        $remainder = $sum % 11;
        $digit = $remainder < 2 ? 0 : 11 - $remainder;
        if ((int)$cnpj[12 + $step] !== $digit) {
            return false;
        }
    }
    return true;
}

function normalize_document($document) {
    $digits = only_digits($document);
    if ($digits === '') {
        throw new Exception('Informe um CPF ou CNPJ valido para gerar o Pix.');
    }
    if (strlen($digits) === 11 && valid_cpf($digits)) {
        return $digits;
    }
    if (strlen($digits) === 14 && valid_cnpj($digits)) {
        return $digits;
    }
    throw new Exception('CPF ou CNPJ invalido. Confira os numeros e tente novamente.');
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Metodo invalido.');
    }
    validate_csrf_token($_POST['csrf_token'] ?? null);
    $_SESSION['checkout_old'] = [
        'name' => trim((string)($_POST['name'] ?? '')),
        'business_name' => trim((string)($_POST['business_name'] ?? '')),
        'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
        'phone' => trim((string)($_POST['phone'] ?? '')),
        'document' => trim((string)($_POST['document'] ?? '')),
    ];

    $name = trim((string)($_POST['name'] ?? ''));
    if ($name === '') {
        throw new Exception('Informe seu nome completo.');
    }

    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um e-mail valido.');
    }
    $phone = only_digits($_POST['phone'] ?? '');
    if (strlen($phone) < 10 || strlen($phone) > 11) {
        throw new Exception('Informe um telefone/WhatsApp valido com DDD.');
    }
    $document = normalize_document($_POST['document'] ?? '');
    $client_data = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'document' => $document,
        'business_name' => trim((string)($_POST['business_name'] ?? '')),
        'password' => (string)($_POST['password'] ?? ''),
        'status' => 'active',
    ];

    $existing = db_fetch('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        $user_id = (int)$existing['id'];
        create_or_update_client($client_data, $user_id);
    } else {
        $user_id = create_or_update_client($client_data);
    }
    $payment = asaas_create_subscription_payment($user_id);
    unset($_SESSION['checkout_old']);
    $_SESSION['checkout_payment_id'] = $payment['id'];
    header('Location: ../../pix.php?id=' . (int)$payment['id'], true, 303);
    exit;
} catch (Throwable $e) {
    $message = $e->getMessage();
    if (stripos($message, 'CPF/CNPJ') !== false || stripos($message, 'cpfCnpj') !== false) {
        $message = 'CPF ou CNPJ invalido. Confira os numeros e tente novamente.';
    }
    $_SESSION['error'] = $message;
    header('Location: ../../comprar.php', true, 303);
    exit;
}
