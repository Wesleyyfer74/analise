<?php
require_once __DIR__ . '/../includes/auth.php';
$admin = require_admin();

$stats = [
    [
        'label' => 'Clientes cadastrados',
        'value' => db_fetch('SELECT COUNT(*) total FROM users WHERE role = ?', ['client'])['total'] ?? 0,
        'help' => 'barbeiros e profissionais',
        'icon' => '01',
    ],
    [
        'label' => 'Clientes ativos',
        'value' => db_fetch('SELECT COUNT(*) total FROM client_profiles WHERE subscription_status = ? AND subscription_expires_at >= ?', ['active', date('Y-m-d H:i:s')])['total'] ?? 0,
        'help' => 'com acesso liberado',
        'icon' => '02',
    ],
    [
        'label' => 'Vendas recebidas',
        'value' => db_fetch('SELECT COALESCE(SUM(amount),0) total FROM payments WHERE status = ?', ['paid'])['total'] ?? 0,
        'help' => 'pagamentos confirmados',
        'icon' => 'R$',
        'money' => true,
    ],
    [
        'label' => 'Seu saldo',
        'value' => db_fetch('SELECT COALESCE(SUM(owner_share_value),0) total FROM payments WHERE status = ?', ['paid'])['total'] ?? 0,
        'help' => 'valor para resgate',
        'icon' => '03',
        'money' => true,
    ],
    [
        'label' => 'Analises geradas',
        'value' => db_fetch('SELECT COALESCE(SUM(tokens_used),0) total FROM usage_events')['total'] ?? 0,
        'help' => 'relatorios criados',
        'icon' => 'IA',
    ],
];
$recent = db_fetch_all('SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id=p.user_id ORDER BY p.id DESC LIMIT 8');
$paid_total = $stats[2]['value'];
$active_clients = $stats[1]['value'];

function admin_money($value) {
    return 'R$ ' . h(number_format((float)$value, 2, ',', '.'));
}

function stat_display($stat) {
    return !empty($stat['money']) ? admin_money($stat['value']) : h($stat['value']);
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

    <section class="hero-admin">
        <div>
            <span class="eyebrow">Painel administrativo</span>
            <h1>Resumo do seu sistema</h1>
            <p>Acompanhe seus clientes, assinaturas ativas, vendas recebidas, saldo para resgate e quantidade de analises feitas.</p>
        </div>
        <div class="hero-metric">
            <span>Vendas recebidas</span>
            <strong><?= admin_money($paid_total) ?></strong>
            <span><?= h($active_clients) ?> cliente(s) ativo(s)</span>
        </div>
    </section>

    <section class="grid">
        <?php foreach($stats as $stat): ?>
            <article class="card stat-card">
                <span class="stat-icon"><?= h($stat['icon']) ?></span>
                <p class="stat-label"><?= h($stat['label']) ?></p>
                <div class="stat-value"><?= stat_display($stat) ?></div>
                <p class="stat-help"><?= h($stat['help']) ?></p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Pagamentos recentes</h2>
        <?php if (!$recent): ?>
            <div class="empty">Nenhum pagamento registrado ainda. Quando uma assinatura for paga, ela aparece aqui.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Cliente</th><th>Status</th><th>Valor pago</th><th>Seu saldo</th></tr></thead>
                    <tbody>
                    <?php foreach($recent as $p): ?>
                        <?php $status = strtolower((string)$p['status']); ?>
                        <tr>
                            <td><?= h($p['name'] ?? 'Cliente') ?></td>
                            <td><span class="status-pill <?= h($status) ?>"><?= h($p['status']) ?></span></td>
                            <td><?= admin_money($p['amount']) ?></td>
                            <td><?= admin_money($p['owner_share_value'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
