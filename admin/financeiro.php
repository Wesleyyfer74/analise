<?php
require_once __DIR__ . '/../includes/asaas.php';
require_admin();

function br_money_admin($value) {
    return 'R$ ' . h(number_format((float)$value, 2, ',', '.'));
}

$balance = null;
$balance_error = null;
try {
    if (env_value('ASAAS_API_KEY')) {
        $balance = asaas_balance();
    }
} catch (Throwable $e) {
    $balance_error = $e->getMessage();
}

$totals = db_fetch('SELECT COALESCE(SUM(amount),0) paid_total, COUNT(*) paid_count FROM payments WHERE status = ?', ['paid']);
$pending = db_fetch('SELECT COALESCE(SUM(amount),0) pending_total, COUNT(*) pending_count FROM payments WHERE status NOT IN (?, ?)', ['paid', 'deleted']);
$owner = db_fetch('SELECT COALESCE(SUM(owner_share_value),0) paid_total FROM payments WHERE status = ?', ['paid']);
$platform = db_fetch('SELECT COALESCE(SUM(COALESCE(platform_share_value, amount)),0) paid_total FROM payments WHERE status = ?', ['paid']);
$payments = db_fetch_all('SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 30');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Financeiro</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="wrap">
    <?php include __DIR__ . '/nav.php'; ?>
    <section class="hero-admin">
        <div>
            <span class="eyebrow">Financeiro</span>
            <h1>Vendas, split e saldos</h1>
            <p>Veja o que foi recebido, o que ficou para a dona, a comissao da plataforma e o saldo da conta principal Asaas.</p>
        </div>
        <div class="hero-metric">
            <span>Total pago</span>
            <strong><?= br_money_admin($totals['paid_total'] ?? 0) ?></strong>
            <span><?= h($totals['paid_count'] ?? 0) ?> pagamento(s)</span>
        </div>
    </section>
    <section class="grid">
        <div class="card stat-card"><span class="stat-icon">R$</span><p class="stat-label">Recebido</p><div class="stat-value"><?= br_money_admin($totals['paid_total'] ?? 0) ?></div><p class="stat-help"><?= h($totals['paid_count'] ?? 0) ?> pagamentos</p></div>
        <div class="card stat-card"><span class="stat-icon">70</span><p class="stat-label">Saldo dona</p><div class="stat-value"><?= br_money_admin($owner['paid_total'] ?? 0) ?></div><p class="stat-help">parte enviada por split</p></div>
        <div class="card stat-card"><span class="stat-icon">30</span><p class="stat-label">Plataforma</p><div class="stat-value"><?= br_money_admin($platform['paid_total'] ?? 0) ?></div><p class="stat-help">conta principal</p></div>
        <div class="card stat-card"><span class="stat-icon">PX</span><p class="stat-label">Pendente</p><div class="stat-value"><?= br_money_admin($pending['pending_total'] ?? 0) ?></div><p class="stat-help"><?= h($pending['pending_count'] ?? 0) ?> cobrancas</p></div>
        <div class="card stat-card"><span class="stat-icon">AS</span><p class="stat-label">Asaas principal</p><?php if($balance): ?><div class="stat-value"><?= br_money_admin($balance['balance'] ?? 0) ?></div><?php elseif($balance_error): ?><p><?= h($balance_error) ?></p><?php else: ?><p>Configure ASAAS_API_KEY.</p><?php endif; ?></div>
    </section>

    <section class="card">
        <h2>Resgate</h2>
        <p>O saldo da dona fica na subconta Asaas configurada em Dona/Split. A comissao da plataforma fica na conta principal. Transferencia, saque e chave Pix sao configurados dentro do Asaas, nao no banco do sistema.</p>
        <a class="btn btn-primary" href="dona.php">Configurar dona/split</a>
        <a class="btn" href="https://www.asaas.com/" target="_blank" rel="noopener">Abrir Asaas</a>
    </section>

    <section class="card">
        <h2>Movimentacoes</h2>
        <div class="table-wrap"><table>
            <thead><tr><th>Cliente</th><th>Status</th><th>Valor</th><th>Dona</th><th>Plataforma</th><th>Pago em</th></tr></thead>
            <tbody>
            <?php foreach($payments as $p): ?>
                <?php $status = strtolower((string)$p['status']); ?>
                <tr>
                    <td><?= h($p['name'] ?? '-') ?></td>
                    <td><span class="status-pill <?= h($status) ?>"><?= h($p['status']) ?></span></td>
                    <td><?= br_money_admin($p['amount']) ?></td>
                    <td><?= br_money_admin($p['owner_share_value'] ?? 0) ?></td>
                    <td><?= br_money_admin($p['platform_share_value'] ?? 0) ?></td>
                    <td><?= h($p['paid_at'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    </section>
</main>
</body>
</html>
