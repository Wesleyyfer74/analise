<?php
require_once dirname(__DIR__, 2) . '/includes/asaas.php';
start_secure_session();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Metodo invalido.');
    }
    validate_csrf_token($_POST['csrf_token'] ?? null);
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Informe um e-mail valido.');
    }
    $existing = db_fetch('SELECT id FROM users WHERE email = ?', [$email]);
    if ($existing) {
        $user_id = (int)$existing['id'];
    } else {
        $user_id = create_or_update_client([
            'name' => trim((string)$_POST['name']),
            'email' => $email,
            'phone' => trim((string)($_POST['phone'] ?? '')),
            'document' => trim((string)($_POST['document'] ?? '')),
            'business_name' => trim((string)($_POST['business_name'] ?? '')),
            'password' => (string)$_POST['password'],
            'status' => 'active',
        ]);
    }
    $payment = asaas_create_subscription_payment($user_id);
    $_SESSION['checkout_payment_id'] = $payment['id'];
    header('Location: ../../pix.php?id=' . (int)$payment['id'], true, 303);
    exit;
} catch (Throwable $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: ../../comprar.php', true, 303);
    exit;
}
