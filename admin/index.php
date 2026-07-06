<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();
$stats = [
    'clients' => db_fetch('SELECT COUNT(*) total FROM users WHERE role = ?', ['client'])['total'] ?? 0,
    'active' => db_fetch('SELECT COUNT(*) total FROM client_profiles WHERE subscription_status = ? AND subscription_expires_at >= ?', ['active', date('Y-m-d H:i:s')])['total'] ?? 0,
    'paid' => db_fetch('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status = ?', ['paid'])['total'] ?? 0,
    'tokens' => db_fetch('SELECT COALESCE(SUM(tokens_remaining),0) total FROM client_profiles')['total'] ?? 0,
    'usage' => db_fetch('SELECT COALESCE(SUM(tokens_used),0) total FROM usage_events')['total'] ?? 0,
];
$recent = db_fetch_all('SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 8');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Dashboard Admin</title><link rel="stylesheet" href="style.css"></head><body><main class="wrap"><?php include __DIR__ . '/nav.php'; ?><h1>Dashboard</h1><section class="grid"><?php foreach($stats as $k=>$v): ?><div class="card"><p><?= h($k) ?></p><h2><?= $k==='paid' ? 'R$ '.h(number_format((float)$v,2,',','.')) : h($v) ?></h2></div><?php endforeach; ?></section><section class="card"><h2>Pagamentos recentes</h2><?php foreach($recent as $p): ?><p><?= h($p['name'] ?? 'Cliente') ?> - <?= h($p['status']) ?> - R$ <?= h(number_format((float)$p['amount'],2,',','.')) ?></p><?php endforeach; ?></section></main></body></html>
