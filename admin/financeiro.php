<?php
require_once __DIR__ . '/../includes/asaas.php';
require_admin();
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
$payments = db_fetch_all('SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 30');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Financeiro</title><link rel="stylesheet" href="style.css"></head><body><main class="wrap"><?php include __DIR__.'/nav.php'; ?><h1>Financeiro</h1>
<section class="grid"><div class="card"><p>Recebido no sistema</p><h2>R$ <?= h(number_format((float)$totals['paid_total'],2,',','.')) ?></h2><p><?= h($totals['paid_count']) ?> pagamentos</p></div><div class="card"><p>Pendente</p><h2>R$ <?= h(number_format((float)$pending['pending_total'],2,',','.')) ?></h2><p><?= h($pending['pending_count']) ?> cobranças</p></div><div class="card"><p>Saldo Asaas</p><?php if($balance): ?><h2>R$ <?= h(number_format((float)($balance['balance'] ?? 0),2,',','.')) ?></h2><?php elseif($balance_error): ?><p><?= h($balance_error) ?></p><?php else: ?><p>Configure ASAAS_API_KEY.</p><?php endif; ?></div></section>
<section class="card"><h2>Resgate</h2><p>O saldo recebido pelo split fica na carteira Asaas configurada. Para transferir para banco ou validar saque, use a área financeira do Asaas com as regras de segurança da conta.</p><a class="btn btn-primary" href="https://www.asaas.com/" target="_blank" rel="noopener">Abrir Asaas</a></section>
<section class="card"><h2>Movimentações</h2><table><thead><tr><th>Cliente</th><th>Status</th><th>Valor</th><th>Pago em</th></tr></thead><tbody><?php foreach($payments as $p): ?><tr><td><?= h($p['name'] ?? '-') ?></td><td><?= h($p['status']) ?></td><td>R$ <?= h(number_format((float)$p['amount'],2,',','.')) ?></td><td><?= h($p['paid_at'] ?? '-') ?></td></tr><?php endforeach; ?></tbody></table></section>
</main></body></html>
