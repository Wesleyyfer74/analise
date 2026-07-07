<?php
require_once __DIR__ . '/../includes/asaas.php';
require_admin();

function br_money_admin($value) {
    return 'R$ ' . h(number_format((float)$value, 2, ',', '.'));
}

$settings = owner_settings() ?: [];
$sub_balance = null;
$sub_balance_error = null;
$wallet_id = $settings['wallet_id'] ?? null;
$has_subaccount_key = !empty($settings['api_key_encrypted']);

try {
    if ($has_subaccount_key) {
        $sub_key = decrypt_secret($settings['api_key_encrypted']);
        if (!$sub_key) {
            throw new Exception('Nao foi possivel ler a chave da subconta.');
        }
        $sub_balance = asaas_request_with_key('GET', 'finance/balance', $sub_key);
    }
} catch (Throwable $e) {
    $sub_balance_error = $e->getMessage();
}

$available_balance = $sub_balance['balance'] ?? null;
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
            <h1>Saldo disponivel para resgate</h1>
            <p>Este painel mostra somente o valor disponivel na subconta Asaas da dona do sistema.</p>
        </div>
        <div class="hero-metric">
            <span>Disponivel na subconta</span>
            <strong><?= $available_balance !== null ? br_money_admin($available_balance) : '---' ?></strong>
            <span><?= $wallet_id ? 'Subconta conectada' : 'Subconta nao configurada' ?></span>
        </div>
    </section>

    <section class="card">
        <span class="eyebrow">Resgate</span>
        <h2>Valor na subconta Asaas</h2>
        <?php if ($available_balance !== null): ?>
            <p class="big"><?= br_money_admin($available_balance) ?></p>
            <p>Para transferir esse saldo para banco ou chave Pix, acesse o painel do Asaas da subconta.</p>
            <a class="btn btn-primary" href="https://www.asaas.com/" target="_blank" rel="noopener">Abrir Asaas</a>
        <?php elseif ($sub_balance_error): ?>
            <p class="err-text"><?= h($sub_balance_error) ?></p>
            <p>O saldo real so aparece quando a subconta foi criada pelo sistema e a chave da subconta esta salva.</p>
        <?php elseif ($wallet_id): ?>
            <p class="err-text">A wallet da subconta esta salva, mas nao existe chave da subconta para consultar saldo real.</p>
            <p>O split funciona com o walletId, mas o saldo para resgate precisa ser visto diretamente no Asaas.</p>
            <a class="btn btn-primary" href="https://www.asaas.com/" target="_blank" rel="noopener">Abrir Asaas</a>
        <?php else: ?>
            <p class="err-text">Subconta Asaas ainda nao configurada.</p>
            <p>Configure a subconta internamente para que o saldo da dona apareca aqui.</p>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
