<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        validate_csrf_token($_POST['csrf_token'] ?? null);
        if (login_user($_POST['email'] ?? '', $_POST['password'] ?? '', 'admin')) { header('Location: index.php', true, 303); exit; }
        $error = 'Login invalido.';
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin</title><style>body{font-family:Inter,system-ui,sans-serif;background:#0f172a}.box{width:min(420px,calc(100% - 28px));margin:70px auto;background:white;border-radius:22px;padding:24px}input{width:100%;padding:13px;margin:6px 0 14px;border:1px solid #ddd;border-radius:12px}.btn{width:100%;padding:14px;border:0;border-radius:12px;background:#7c3aed;color:white;font-weight:900}.err{color:#b42318}</style></head><body><main class="box"><h1>Admin</h1><?php if($error): ?><p class="err"><?= h($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><label>E-mail</label><input name="email" type="email" required><label>Senha</label><input name="password" type="password" required><button class="btn">Entrar</button></form></main></body></html>
