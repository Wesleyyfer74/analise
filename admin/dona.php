<?php
require_once __DIR__ . '/../includes/asaas.php';
$admin = require_admin();

$message = $_SESSION['owner_message'] ?? null;
$error = $_SESSION['owner_error'] ?? null;
unset($_SESSION['owner_message'], $_SESSION['owner_error']);

function digits_only_admin($value) {
    return preg_replace('/\D+/', '', (string)$value);
}

function money_br($value) {
    return 'R$ ' . h(number_format((float)$value, 2, ',', '.'));
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        validate_csrf_token($_POST['csrf_token'] ?? null);
        $action = $_POST['action'] ?? 'save_wallet';
        $split_percent = (float)str_replace(',', '.', (string)($_POST['split_percent'] ?? '70'));
        if ($split_percent <= 0 || $split_percent >= 100) {
            throw new Exception('Informe um percentual entre 1 e 99.');
        }

        $data = [
            'name' => trim((string)($_POST['name'] ?? '')),
            'email' => strtolower(trim((string)($_POST['email'] ?? ''))),
            'cpf_cnpj' => digits_only_admin($_POST['cpf_cnpj'] ?? ''),
            'birth_date' => trim((string)($_POST['birth_date'] ?? '')) ?: null,
            'company_type' => trim((string)($_POST['company_type'] ?? '')) ?: null,
            'phone' => digits_only_admin($_POST['phone'] ?? ''),
            'mobile_phone' => digits_only_admin($_POST['mobile_phone'] ?? ''),
            'income_value' => (float)str_replace(',', '.', (string)($_POST['income_value'] ?? '0')),
            'address' => trim((string)($_POST['address'] ?? '')),
            'address_number' => trim((string)($_POST['address_number'] ?? '')),
            'complement' => trim((string)($_POST['complement'] ?? '')),
            'province' => trim((string)($_POST['province'] ?? '')),
            'postal_code' => digits_only_admin($_POST['postal_code'] ?? ''),
            'split_percent' => $split_percent,
        ];

        if ($action === 'create_subaccount') {
            asaas_create_owner_subaccount($data);
            $_SESSION['owner_message'] = 'Subconta criada no Asaas e split ativado com sucesso.';
        } else {
            $wallet_id = trim((string)($_POST['wallet_id'] ?? ''));
            if ($wallet_id === '') {
                throw new Exception('Informe o walletId da subconta.');
            }
            $current = owner_settings() ?: [];
            save_owner_settings(array_merge($current, [
                'wallet_id' => $wallet_id,
                'api_key_encrypted' => $current['api_key_encrypted'] ?? null,
                'split_percent' => $split_percent,
                'status' => 'active',
                'last_error' => null,
                'raw_payload' => $current['raw_payload'] ?? null,
            ]));
            $_SESSION['owner_message'] = 'Wallet da dona salva e split ativado.';
        }
    } catch (Throwable $e) {
        $current = owner_settings() ?: [];
        save_owner_settings(array_merge($current, [
            'status' => 'error',
            'last_error' => $e->getMessage(),
            'split_percent' => (float)str_replace(',', '.', (string)($_POST['split_percent'] ?? '70')),
        ]));
        $_SESSION['owner_error'] = $e->getMessage();
    }
    header('Location: dona.php', true, 303);
    exit;
}

$settings = owner_settings() ?: [];
$owner_paid = db_fetch('SELECT COALESCE(SUM(owner_share_value),0) total, COUNT(*) count_paid FROM payments WHERE status = ?', ['paid']);
$owner_pending = db_fetch('SELECT COALESCE(SUM(owner_share_value),0) total, COUNT(*) count_pending FROM payments WHERE status NOT IN (?, ?)', ['paid', 'deleted']);
$platform_paid = db_fetch('SELECT COALESCE(SUM(platform_share_value),0) total FROM payments WHERE status = ?', ['paid']);
$recent = db_fetch_all('SELECT p.*, u.name FROM payments p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 12');

$sub_balance = null;
$sub_balance_error = null;
try {
    if (!empty($settings['api_key_encrypted'])) {
        $sub_key = decrypt_secret($settings['api_key_encrypted']);
        if ($sub_key) {
            $sub_balance = asaas_request_with_key('GET', 'finance/balance', $sub_key);
        }
    }
} catch (Throwable $e) {
    $sub_balance_error = $e->getMessage();
}

function owner_field($settings, $key, $default = '') {
    return h((string)($settings[$key] ?? $default));
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dona do sistema e split</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main class="wrap">
    <?php include __DIR__ . '/nav.php'; ?>
    <h1>Dona do sistema e split Asaas</h1>
    <p>Configure a subconta da dona. Quando a wallet estiver ativa, cada assinatura envia <?= h((string)($settings['split_percent'] ?? '70')) ?>% para ela e o restante fica na conta principal.</p>

    <?php if ($message): ?><section class="notice ok"><?= h($message) ?></section><?php endif; ?>
    <?php if ($error): ?><section class="notice err"><?= h($error) ?></section><?php endif; ?>

    <section class="grid grid-4">
        <div class="card"><p>Status</p><h2><?= h($settings['status'] ?? 'not_configured') ?></h2></div>
        <div class="card"><p>Saldo da dona no sistema</p><h2><?= money_br($owner_paid['total'] ?? 0) ?></h2><p><?= h($owner_paid['count_paid'] ?? 0) ?> pagamentos pagos</p></div>
        <div class="card"><p>Pendente para dona</p><h2><?= money_br($owner_pending['total'] ?? 0) ?></h2><p><?= h($owner_pending['count_pending'] ?? 0) ?> cobrancas</p></div>
        <div class="card"><p>Comissao plataforma</p><h2><?= money_br($platform_paid['total'] ?? 0) ?></h2><p>30% quando split esta em 70%</p></div>
    </section>

    <section class="card">
        <h2>Saldo real da subconta Asaas</h2>
        <?php if ($sub_balance): ?>
            <p class="big"><?= money_br($sub_balance['balance'] ?? 0) ?></p>
        <?php elseif ($sub_balance_error): ?>
            <p class="err-text"><?= h($sub_balance_error) ?></p>
        <?php else: ?>
            <p>Crie a subconta por aqui para consultar o saldo real pela API. Se voce salvar apenas o walletId manualmente, o painel mostra o saldo estimado pelo historico de pagamentos.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Criar subconta automaticamente</h2>
        <p>O Asaas exige dados completos de cadastro. A conta raiz que cria subcontas via API precisa estar habilitada para esse tipo de operacao.</p>
        <?php if (!empty($settings['last_error'])): ?><p class="err-text">Ultimo erro: <?= h($settings['last_error']) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="form-grid">
                <label>Nome/Razao social<input name="name" required value="<?= owner_field($settings, 'name') ?>"></label>
                <label>E-mail<input name="email" type="email" required value="<?= owner_field($settings, 'email') ?>"></label>
                <label>CPF ou CNPJ<input name="cpf_cnpj" required value="<?= owner_field($settings, 'cpf_cnpj') ?>"></label>
                <label>Data de nascimento, se CPF<input name="birth_date" type="date" value="<?= owner_field($settings, 'birth_date') ?>"></label>
                <label>Tipo da empresa
                    <select name="company_type">
                        <?php $company = $settings['company_type'] ?? 'MEI'; ?>
                        <?php foreach (['MEI', 'LIMITED', 'INDIVIDUAL', 'ASSOCIATION'] as $type): ?>
                            <option value="<?= h($type) ?>" <?= $company === $type ? 'selected' : '' ?>><?= h($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Telefone fixo<input name="phone" value="<?= owner_field($settings, 'phone') ?>"></label>
                <label>Celular/WhatsApp<input name="mobile_phone" required value="<?= owner_field($settings, 'mobile_phone') ?>"></label>
                <label>Faturamento mensal<input name="income_value" required value="<?= owner_field($settings, 'income_value', '5000.00') ?>"></label>
                <label>CEP<input name="postal_code" required value="<?= owner_field($settings, 'postal_code') ?>"></label>
                <label>Bairro<input name="province" required value="<?= owner_field($settings, 'province') ?>"></label>
                <label>Endereco<input name="address" required value="<?= owner_field($settings, 'address') ?>"></label>
                <label>Numero<input name="address_number" required value="<?= owner_field($settings, 'address_number') ?>"></label>
                <label>Complemento<input name="complement" value="<?= owner_field($settings, 'complement') ?>"></label>
                <label>Percentual da dona<input name="split_percent" value="<?= owner_field($settings, 'split_percent', '70') ?>"></label>
            </div>
            <button class="btn btn-primary" name="action" value="create_subaccount" type="submit">Criar subconta e ativar split</button>
        </form>
    </section>

    <section class="card">
        <h2>Usar wallet existente</h2>
        <p>Se a subconta ja foi criada no Asaas, cole o walletId dela aqui. O saldo real nao sera consultado sem a API key da subconta, mas o split funcionara.</p>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label>Wallet ID<input name="wallet_id" value="<?= owner_field($settings, 'wallet_id') ?>"></label>
            <label>Percentual da dona<input name="split_percent" value="<?= owner_field($settings, 'split_percent', '70') ?>"></label>
            <button class="btn btn-primary" name="action" value="save_wallet" type="submit">Salvar wallet e ativar split</button>
        </form>
    </section>

    <section class="card">
        <h2>Ultimas cobrancas com split</h2>
        <table>
            <thead><tr><th>Cliente</th><th>Status</th><th>Total</th><th>Dona</th><th>Plataforma</th><th>Wallet</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $p): ?>
                <tr>
                    <td><?= h($p['name'] ?? '-') ?></td>
                    <td><?= h($p['status']) ?></td>
                    <td><?= money_br($p['amount']) ?></td>
                    <td><?= money_br($p['owner_share_value'] ?? 0) ?></td>
                    <td><?= money_br($p['platform_share_value'] ?? 0) ?></td>
                    <td><?= h($p['split_wallet_id'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>
</body>
</html>
