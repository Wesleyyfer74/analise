<?php
require_once __DIR__ . '/database.php';

function current_user() {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return db_fetch('SELECT * FROM users WHERE id = ?', [(int)$_SESSION['user_id']]) ?: null;
}

function require_login() {
    start_secure_session();
    $user = current_user();
    if (!$user) {
        $login = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/cliente/') !== false ? 'login.php' : 'cliente/login.php';
        header('Location: ' . $login, true, 303);
        exit;
    }
    return $user;
}

function require_admin() {
    start_secure_session();
    $user = current_user();
    if (!$user || $user['role'] !== 'admin') {
        $login = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false ? 'login.php' : 'admin/login.php';
        header('Location: ' . $login, true, 303);
        exit;
    }
    return $user;
}

function login_user($email, $password, $role = null) {
    $email = strtolower(trim((string)$email));
    $user = db_fetch('SELECT * FROM users WHERE email = ?', [$email]);
    if (!$user || !password_verify((string)$password, $user['password_hash'])) {
        return false;
    }
    if ($role && $user['role'] !== $role) {
        return false;
    }
    if ($user['status'] !== 'active') {
        return false;
    }
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['user_role'] = $user['role'];
    session_regenerate_id(true);
    return true;
}

function logout_user() {
    unset($_SESSION['user_id'], $_SESSION['user_role'], $_SESSION['report_id'], $_SESSION['report_ids']);
    session_regenerate_id(true);
}

function client_profile($user_id) {
    return db_fetch('SELECT * FROM client_profiles WHERE user_id = ?', [(int)$user_id]);
}

function subscription_active($profile = null) {
    if (!$profile || $profile['subscription_status'] !== 'active') {
        return false;
    }
    if (empty($profile['subscription_expires_at'])) {
        return false;
    }
    return strtotime($profile['subscription_expires_at']) >= time();
}

function days_until_expiration($profile) {
    if (empty($profile['subscription_expires_at'])) {
        return null;
    }
    return (int)ceil((strtotime($profile['subscription_expires_at']) - time()) / 86400);
}

function require_active_client() {
    $user = require_login();
    if ($user['role'] !== 'client') {
        header('Location: admin/index.php', true, 303);
        exit;
    }
    $profile = client_profile($user['id']);
    if (!subscription_active($profile)) {
        $_SESSION['error'] = 'Sua assinatura esta inativa ou expirada. Renove para usar o analisador.';
        header('Location: cliente/painel.php', true, 303);
        exit;
    }
    if ((int)$profile['tokens_remaining'] <= 0) {
        $_SESSION['error'] = 'Seus tokens acabaram. Renove a assinatura para continuar.';
        header('Location: cliente/painel.php', true, 303);
        exit;
    }
    return [$user, $profile];
}

function consume_token($user_id, $report_id) {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $profile = db_fetch('SELECT tokens_remaining FROM client_profiles WHERE user_id = ?', [(int)$user_id]);
        if (!$profile || (int)$profile['tokens_remaining'] <= 0) {
            throw new Exception('Tokens insuficientes.');
        }
        db_execute('UPDATE client_profiles SET tokens_remaining = tokens_remaining - 1 WHERE user_id = ?', [(int)$user_id]);
        db_execute(
            'INSERT INTO usage_events (user_id, event_type, tokens_used, report_id, created_at) VALUES (?, ?, ?, ?, ?)',
            [(int)$user_id, 'analysis', 1, $report_id, date('Y-m-d H:i:s')]
        );
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function create_or_update_client($data, $user_id = null) {
    $pdo = db();
    $now = date('Y-m-d H:i:s');
    $email = strtolower(trim((string)$data['email']));
    $password = (string)($data['password'] ?? '');
    $status = $data['status'] ?? 'active';
    $quota = (int)($data['monthly_token_quota'] ?? env_int('MONTHLY_TOKEN_QUOTA', 30, 1, 10000));

    if ($user_id) {
        $fields = [$data['name'], $email, $data['phone'] ?? null, $status, $now, $user_id];
        db_execute('UPDATE users SET name = ?, email = ?, phone = ?, status = ?, updated_at = ? WHERE id = ?', $fields);
        if ($password !== '') {
            db_execute('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user_id]);
        }
    } else {
        if ($password === '') {
            $password = bin2hex(random_bytes(4));
        }
        db_execute(
            'INSERT INTO users (role, name, email, phone, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            ['client', $data['name'], $email, $data['phone'] ?? null, password_hash($password, PASSWORD_DEFAULT), $status, $now]
        );
        $user_id = (int)$pdo->lastInsertId();
    }

    $profile = client_profile($user_id);
    if ($profile) {
        db_execute(
            'UPDATE client_profiles SET business_name = ?, document = ?, monthly_token_quota = ? WHERE user_id = ?',
            [$data['business_name'] ?? null, $data['document'] ?? null, $quota, $user_id]
        );
    } else {
        db_execute(
            'INSERT INTO client_profiles (user_id, business_name, document, tokens_remaining, monthly_token_quota, subscription_status) VALUES (?, ?, ?, ?, ?, ?)',
            [$user_id, $data['business_name'] ?? null, $data['document'] ?? null, 0, $quota, 'inactive']
        );
    }
    return $user_id;
}
