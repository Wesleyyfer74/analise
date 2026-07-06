<?php
require_once __DIR__ . '/includes/asaas.php';
start_secure_session();
$id = (int)($_GET['id'] ?? $_SESSION['checkout_payment_id'] ?? 0);
$payment = $id ? db_fetch('SELECT * FROM payments WHERE id = ?', [$id]) : null;
if (!$payment) { http_response_code(404); exit('Pagamento nao encontrado.'); }
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pix gerado</title>
<style>body{font-family:Inter,system-ui,sans-serif;background:#f5f7fb;margin:0}.wrap{width:min(720px,calc(100% - 28px));margin:auto;padding:28px 0}.card{background:#fff;border:1px solid #e7ebf2;border-radius:24px;padding:24px;text-align:center}textarea{width:100%;min-height:120px}.btn{display:inline-flex;margin-top:14px;padding:13px 16px;border-radius:12px;background:#7c3aed;color:white;text-decoration:none;font-weight:900}</style></head>
<body><main class="wrap"><section class="card">
<h1>Pix gerado</h1><p>Assim que o Asaas confirmar o pagamento, seu acesso sera liberado automaticamente.</p>
<?php if ($payment['pix_qr_code']): ?><img alt="QR Code Pix" style="max-width:260px;width:100%" src="data:image/png;base64,<?= h($payment['pix_qr_code']) ?>"><?php endif; ?>
<?php if ($payment['pix_payload']): ?><p>Pix copia e cola:</p><textarea readonly><?= h($payment['pix_payload']) ?></textarea><?php endif; ?>
<?php if ($payment['invoice_url']): ?><a class="btn" href="<?= h($payment['invoice_url']) ?>" target="_blank" rel="noopener">Abrir cobrança no Asaas</a><?php endif; ?>
<p><a href="cliente/login.php">Já paguei, acessar login</a></p>
</section></main></body></html>
