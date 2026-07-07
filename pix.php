<?php
require_once __DIR__ . '/includes/asaas.php';

start_secure_session();

$id = (int)($_GET['id'] ?? $_SESSION['checkout_payment_id'] ?? 0);
$payment = $id ? db_fetch('SELECT * FROM payments WHERE id = ?', [$id]) : null;

if (!$payment) {
    http_response_code(404);
    exit('Pagamento não encontrado.');
}

$pix_error = null;
if ((empty($payment['pix_qr_code']) || empty($payment['pix_payload'])) && !empty($payment['asaas_payment_id'])) {
    try {
        $pix = asaas_request('GET', 'payments/' . rawurlencode($payment['asaas_payment_id']) . '/pixQrCode');
        $pix_payload = $pix['payload'] ?? $payment['pix_payload'];
        $pix_qr = $pix['encodedImage'] ?? $payment['pix_qr_code'];

        if ($pix_payload || $pix_qr) {
            db_execute(
                'UPDATE payments SET pix_payload = ?, pix_qr_code = ?, updated_at = ? WHERE id = ?',
                [$pix_payload, $pix_qr, date('Y-m-d H:i:s'), $id]
            );
            $payment = db_fetch('SELECT * FROM payments WHERE id = ?', [$id]);
        }
    } catch (Throwable $e) {
        $pix_error = 'Não foi possível carregar o QR Code agora. Use o botão da cobrança ou atualize a página em alguns segundos.';
        debug_log('pix_page.qr_fetch_failed', [
            'payment_id' => $payment['asaas_payment_id'],
            'message' => $e->getMessage(),
        ], 'WARNING');
    }
}

$amount = number_format((float)$payment['amount'], 2, ',', '.');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pix gerado</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #0b1b34;
            --muted: #5d6b82;
            --brand: #0f766e;
            --brand-dark: #0b4f4a;
            --purple: #7c3aed;
            --line: #dfe6f0;
            --soft: #e9f7f5;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at 15% 5%, rgba(15,118,110,.12), transparent 26rem),
                radial-gradient(circle at 85% 10%, rgba(124,58,237,.10), transparent 24rem),
                var(--bg);
        }

        .wrap {
            width: min(760px, calc(100% - 28px));
            margin: 0 auto;
            padding: 42px 0;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 28px;
            padding: clamp(24px, 5vw, 42px);
            text-align: center;
            box-shadow: 0 24px 70px rgba(11,27,52,.08);
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 8px 14px;
            border-radius: 999px;
            color: var(--brand-dark);
            background: var(--soft);
            font-size: .78rem;
            font-weight: 900;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        h1 {
            margin: 18px 0 10px;
            font-size: clamp(2rem, 6vw, 3rem);
            letter-spacing: -.045em;
        }

        p {
            margin: 0 auto;
            max-width: 590px;
            color: var(--muted);
            line-height: 1.65;
            font-size: 1rem;
        }

        .amount {
            margin: 20px auto 0;
            font-size: 1.15rem;
            font-weight: 900;
        }

        .qr-box {
            width: min(310px, 100%);
            margin: 28px auto 18px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: #fff;
            box-shadow: 0 16px 34px rgba(11,27,52,.08);
        }

        .qr-box img {
            display: block;
            width: 100%;
            height: auto;
            border-radius: 14px;
        }

        .empty-qr {
            margin: 24px auto;
            padding: 18px;
            border-radius: 18px;
            color: #8a4b10;
            background: #fff5e6;
            border: 1px solid #f5d8aa;
        }

        .copy-area {
            margin-top: 24px;
            text-align: left;
        }

        .copy-area label {
            display: block;
            margin-bottom: 9px;
            font-weight: 900;
        }

        textarea {
            width: 100%;
            min-height: 126px;
            resize: vertical;
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 16px;
            color: var(--text);
            background: #fbfdff;
            font: 600 .92rem/1.45 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        .actions {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 13px 18px;
            border: 0;
            border-radius: 999px;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            text-decoration: none;
            font-weight: 900;
            cursor: pointer;
        }

        .btn.secondary {
            color: var(--text);
            background: #fff;
            border: 1px solid var(--line);
        }

        .hint {
            margin-top: 18px;
            font-size: .93rem;
        }

        .login {
            display: inline-block;
            margin-top: 22px;
            color: var(--brand-dark);
            font-weight: 900;
        }
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <span class="pill">Pagamento por Pix</span>
        <h1>Pix gerado</h1>
        <p>Escaneie o QR Code pelo aplicativo do seu banco ou copie o código Pix abaixo para pagar em qualquer instituição.</p>
        <div class="amount">Valor: R$ <?= h($amount) ?></div>

        <?php if (!empty($payment['pix_qr_code'])): ?>
            <div class="qr-box">
                <img alt="QR Code Pix" src="data:image/png;base64,<?= h($payment['pix_qr_code']) ?>">
            </div>
        <?php else: ?>
            <div class="empty-qr"><?= h($pix_error ?: 'O QR Code ainda está sendo gerado. Atualize a página em alguns segundos.') ?></div>
        <?php endif; ?>

        <?php if (!empty($payment['pix_payload'])): ?>
            <div class="copy-area">
                <label for="pixPayload">Código Pix copia e cola</label>
                <textarea id="pixPayload" readonly><?= h($payment['pix_payload']) ?></textarea>
            </div>
            <div class="actions">
                <button class="btn" type="button" id="copyPix">Copiar código Pix</button>
                <?php if (!empty($payment['invoice_url'])): ?>
                    <a class="btn secondary" href="<?= h($payment['invoice_url']) ?>" target="_blank" rel="noopener">Abrir cobrança</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="actions">
                <a class="btn" href="">Atualizar página</a>
                <?php if (!empty($payment['invoice_url'])): ?>
                    <a class="btn secondary" href="<?= h($payment['invoice_url']) ?>" target="_blank" rel="noopener">Abrir cobrança</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <p class="hint">Assim que o pagamento for confirmado, seu acesso será liberado automaticamente.</p>
        <a class="login" href="cliente/login.php">Já paguei, acessar login</a>
    </section>
</main>

<script>
    const copyButton = document.getElementById('copyPix');
    const payload = document.getElementById('pixPayload');

    if (copyButton && payload) {
        copyButton.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(payload.value);
                copyButton.textContent = 'Código copiado';
            } catch (error) {
                payload.focus();
                payload.select();
                document.execCommand('copy');
                copyButton.textContent = 'Código copiado';
            }

            setTimeout(() => {
                copyButton.textContent = 'Copiar código Pix';
            }, 2500);
        });
    }
</script>
</body>
</html>
