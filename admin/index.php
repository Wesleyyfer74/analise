<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$stats = [
    'clientes' => db_fetch('SELECT COUNT(*) total FROM users WHERE role = ?', ['client'])['total'] ?? 0,
    'ativos' => db_fetch('SELECT COUNT(*) total FROM client_profiles WHERE subscription_status = ? AND subscription_expires_at >= ?', ['active', date('Y-m-d H:i:s')])['total'] ?? 0,
    'vendas' => db_fetch('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status = ?', ['paid'])['total'] ?? 0,
    'saldo_dona' => db_fetch('SELECT COALESCE(SUM(owner_share_value),0) total FROM payments WHERE status = ?', ['paid'])['total'] ?? 0,
    'plataforma' => db_fetch('SELECT COALESCE(SUM(COALESCE(platform_share_value, amount)),0) total FROM payments WHERE status = ?', ['paid'])['total'] ?? 0,
    'tokens' => db_fetch('SELECT COALESCE(SUM(tokens_remaining),0) total FROM client_profiles')['total'] ?? 0,
    'uso' => db_fetch('SELECT COALESCE(SUM(tokens_used),0) total FROM usage_events')['total'] ?? 0,
];
$recent = db_fetch_all('SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 8');

function stat_value($key, $value) {
    if (in_array($key, ['vendas', 'saldo_dona', 'plataforma'], true)) {
        return 'R$ ' . h(number_format((float)$value, 2, ',', '.'));
    }
    return h($value);
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dashboard Admin</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="wrap">
    <?php include __DIR__ . '/nav.php'; ?>
    <h1>Dashboard</h1>
    <section class="grid">
        <?php foreach($stats as $k=>$v): ?>
            <div class="card"><p><?= h(str_replace('_', ' ', $k)) ?></p><h2><?= stat_value($k, $v) ?></h2></div>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <h2>Pagamentos recentes</h2>
        <table>
            <thead><tr><th>Cliente</th><th>Status</th><th>Total</th><th>Dona</th><th>Plataforma</th></tr></thead>
            <tbody>
            <?php foreach($recent as $p): ?>
                <tr>
                    <td><?= h($p['name'] ?? 'Cliente') ?></td>
                    <td><?= h($p['status']) ?></td>
                    <td>R$ <?= h(number_format((float)$p['amount'],2,',','.')) ?></td>
                    <td>R$ <?= h(number_format((float)($p['owner_share_value'] ?? 0),2,',','.')) ?></td>
                    <td>R$ <?= h(number_format((float)($p['platform_share_value'] ?? 0),2,',','.')) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
