<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
$user = require_login();
if ($user['role'] !== 'client') { header('Location: ../admin/index.php', true, 303); exit; }
$profile = client_profile($user['id']);
$active = subscription_active($profile);
$days = days_until_expiration($profile);
$payments = db_fetch_all('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$user['id']]);
$error = $_SESSION['error'] ?? null; unset($_SESSION['error']);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Painel do cliente</title>
<style>body{margin:0;background:#f5f7fb;font-family:Inter,system-ui,sans-serif;color:#172033}.wrap{width:min(980px,calc(100% - 28px));margin:auto;padding:28px 0}.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}.card{background:white;border:1px solid #e7ebf2;border-radius:20px;padding:20px}.btn{display:inline-flex;padding:12px 15px;border-radius:12px;background:#7c3aed;color:white;text-decoration:none;font-weight:900}.muted{color:#667085}.err{background:#fff1f0;color:#b42318;padding:12px;border-radius:12px}@media(max-width:760px){.grid{grid-template-columns:1fr}}</style></head>
<body><main class="wrap"><p><a href="../index.php">Início</a> | <a href="logout.php">Sair</a></p><h1>Olá, <?= h($user['name']) ?></h1><?php if($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
<section class="grid"><div class="card"><p class="muted">Assinatura</p><h2><?= $active ? 'Ativa' : 'Inativa' ?></h2><?php if($days !== null): ?><p><?= $days ?> dias restantes</p><?php endif; ?><?php if($active && $days !== null && $days <= 3): ?><p class="err">Sua assinatura expira em até 3 dias. Renove para evitar bloqueio.</p><?php endif; ?></div><div class="card"><p class="muted">Tokens disponíveis</p><h2><?= h($profile['tokens_remaining'] ?? 0) ?></h2><p>Plano: <?= h($profile['monthly_token_quota'] ?? env_value('MONTHLY_TOKEN_QUOTA', 30)) ?>/mês</p></div><div class="card"><p class="muted">Ação</p><?php if($active): ?><a class="btn" href="../app.php">Abrir analisador</a><?php else: ?><a class="btn" href="../comprar.php">Renovar por Pix</a><?php endif; ?></div></section>
<section class="card" style="margin-top:16px"><h2>Últimos pagamentos</h2><?php foreach($payments as $p): ?><p>#<?= h($p['id']) ?> - <?= h($p['status']) ?> - R$ <?= h(number_format((float)$p['amount'],2,',','.')) ?></p><?php endforeach; ?></section>
</main></body></html>
