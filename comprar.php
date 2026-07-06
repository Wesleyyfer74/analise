<?php
require_once __DIR__ . '/includes/asaas.php';
start_secure_session();
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Assinar por Pix</title>
    <style>
        body{margin:0;background:#f5f7fb;color:#172033;font-family:Inter,system-ui,sans-serif}.wrap{width:min(760px,calc(100% - 28px));margin:0 auto;padding:28px 0}.card{background:white;border:1px solid #e7ebf2;border-radius:24px;padding:24px;box-shadow:0 18px 50px rgba(15,23,42,.08)}label{display:block;font-weight:800;margin:14px 0 6px}input{width:100%;padding:13px;border:1px solid #d7dce5;border-radius:12px;font:inherit}.btn{margin-top:18px;width:100%;border:0;border-radius:14px;padding:15px;background:#7c3aed;color:white;font-weight:900;font:inherit}.err{padding:12px;border-radius:12px;background:#fff1f0;color:#b42318}
    </style>
</head>
<body><main class="wrap"><section class="card">
    <h1>Assinar Studio Visagismo IA</h1>
    <p>Preencha seus dados para gerar o Pix. Depois da confirmação do Asaas, seu acesso libera por 30 dias.</p>
    <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
    <form method="post" action="api/asaas/checkout.php">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Nome completo</label><input name="name" required>
        <label>Nome do salão/barbearia</label><input name="business_name">
        <label>E-mail de acesso</label><input name="email" type="email" required>
        <label>Telefone/WhatsApp</label><input name="phone" required>
        <label>CPF ou CNPJ</label><input name="document">
        <label>Senha de acesso</label><input name="password" type="password" minlength="6" required>
        <button class="btn" type="submit">Gerar Pix de R$ <?= h(number_format((float)env_value('SUBSCRIPTION_PRICE', '97.00'), 2, ',', '.')) ?></button>
    </form>
</section></main></body></html>
