<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $connection = strtolower(env_value('DB_CONNECTION', 'sqlite'));
    if ($connection === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env_value('DB_HOST', 'localhost'),
            env_value('DB_PORT', '3306'),
            env_value('DB_NAME', '')
        );
        $pdo = new PDO($dsn, env_value('DB_USER', ''), env_value('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } else {
        $path = env_value('DB_DATABASE', 'data/app.sqlite');
        if ($path === 'data/app.sqlite' && env_value('RAILWAY_VOLUME_MOUNT_PATH')) {
            $path = rtrim(env_value('RAILWAY_VOLUME_MOUNT_PATH'), '/\\') . '/app.sqlite';
        }
        if (!preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) && strpos($path, '/') !== 0) {
            $path = BASE_DIR . '/' . $path;
        }
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    db_migrate($pdo);
    return $pdo;
}

function db_driver() {
    return db()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

function db_now_expression() {
    return db_driver() === 'mysql' ? 'NOW()' : "datetime('now')";
}

function db_migrate(PDO $pdo) {
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'mysql') {
        $auto = 'INT AUTO_INCREMENT PRIMARY KEY';
        $text = 'LONGTEXT';
        $bool = 'TINYINT(1)';
        $datetime = 'DATETIME';
    } else {
        $auto = 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $text = 'TEXT';
        $bool = 'INTEGER';
        $datetime = 'TEXT';
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id $auto,
        role VARCHAR(20) NOT NULL DEFAULT 'client',
        name VARCHAR(120) NOT NULL,
        email VARCHAR(160) NOT NULL UNIQUE,
        phone VARCHAR(40) NULL,
        password_hash VARCHAR(255) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'active',
        created_at $datetime NOT NULL,
        updated_at $datetime NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS client_profiles (
        user_id INT PRIMARY KEY,
        business_name VARCHAR(160) NULL,
        document VARCHAR(40) NULL,
        asaas_customer_id VARCHAR(80) NULL,
        tokens_remaining INT NOT NULL DEFAULT 0,
        monthly_token_quota INT NOT NULL DEFAULT 0,
        subscription_status VARCHAR(20) NOT NULL DEFAULT 'inactive',
        subscription_expires_at $datetime NULL,
        last_alert_at $datetime NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS payments (
        id $auto,
        user_id INT NULL,
        asaas_payment_id VARCHAR(80) NULL UNIQUE,
        asaas_customer_id VARCHAR(80) NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        billing_type VARCHAR(20) NOT NULL DEFAULT 'PIX',
        amount DECIMAL(10,2) NOT NULL DEFAULT 0,
        net_value DECIMAL(10,2) NULL,
        due_date DATE NULL,
        paid_at $datetime NULL,
        invoice_url VARCHAR(500) NULL,
        pix_payload $text NULL,
        pix_qr_code $text NULL,
        raw_payload $text NULL,
        created_at $datetime NOT NULL,
        updated_at $datetime NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS usage_events (
        id $auto,
        user_id INT NOT NULL,
        event_type VARCHAR(40) NOT NULL,
        tokens_used INT NOT NULL DEFAULT 0,
        report_id VARCHAR(32) NULL,
        created_at $datetime NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webhook_events (
        id $auto,
        provider VARCHAR(40) NOT NULL,
        event_id VARCHAR(120) NOT NULL UNIQUE,
        event_type VARCHAR(80) NOT NULL,
        payload $text NOT NULL,
        processed_at $datetime NOT NULL
    )");

    ensure_default_admin($pdo);
}

function ensure_default_admin(PDO $pdo) {
    $email = strtolower(trim(env_value('ADMIN_EMAIL', 'admin@gerador.spacered.com.br')));
    $password = env_value('ADMIN_PASSWORD', '');
    if ($email === '' || $password === '') {
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        return;
    }

    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("INSERT INTO users (role, name, email, password_hash, status, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        'admin',
        'Administrador',
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        'active',
        $now,
    ]);
}

function db_fetch($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

function db_fetch_all($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function db_execute($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}
