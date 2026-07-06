<?php
require_once __DIR__ . '/includes/asaas.php';
start_secure_session();
$error = $_SESSION['error'] ?? null;
$old = $_SESSION['checkout_old'] ?? [];
unset($_SESSION['error'], $_SESSION['checkout_old']);

function old_value($old, $key) {
    return h((string)($old[$key] ?? ''));
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Assinar por Pix</title>
    <style>
        :root{--bg:#f6f7fb;--card:#fff;--text:#111827;--muted:#64748b;--line:#dbe3ef;--brand:#0f766e;--brand-dark:#0b4f4a;--danger:#b42318;--danger-bg:#fff1f0}
        *{box-sizing:border-box}
        body{margin:0;background:radial-gradient(circle at top right,rgba(15,118,110,.12),transparent 30rem),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
        .wrap{width:min(820px,calc(100% - 28px));margin:0 auto;padding:32px 0}
        .card{background:var(--card);border:1px solid #e7ebf2;border-radius:28px;padding:clamp(22px,4vw,34px);box-shadow:0 22px 70px rgba(15,23,42,.09)}
        .top{display:flex;justify-content:space-between;gap:14px;align-items:flex-start;margin-bottom:22px}
        .pill{display:inline-flex;padding:8px 12px;border-radius:999px;background:#d9f1ed;color:var(--brand-dark);font-size:.76rem;font-weight:900;text-transform:uppercase;letter-spacing:.08em}
        h1{margin:12px 0 10px;font-size:clamp(2rem,5vw,3rem);letter-spacing:-.05em;line-height:1}
        p{color:var(--muted);line-height:1.65}
        .price{padding:14px 16px;border-radius:18px;background:#f8fafc;border:1px solid var(--line);text-align:right;white-space:nowrap}
        .price strong{display:block;font-size:1.35rem;color:var(--brand-dark)}
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .field{margin-top:14px}
        .field.full{grid-column:1/-1}
        label{display:block;font-weight:850;margin:0 0 7px}
        input{width:100%;padding:15px;border:1px solid var(--line);border-radius:14px;font:inherit;background:#fff;outline:none;transition:border-color .18s ease,box-shadow .18s ease}
        input:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(15,118,110,.12)}
        .hint{margin:7px 0 0;color:var(--muted);font-size:.88rem}
        .btn{margin-top:20px;width:100%;border:0;border-radius:16px;padding:16px;background:linear-gradient(135deg,var(--brand-dark),var(--brand));color:white;font-weight:950;font:inherit;cursor:pointer;box-shadow:0 18px 38px rgba(15,118,110,.22)}
        .err{padding:13px 14px;border-radius:14px;background:var(--danger-bg);color:var(--danger);font-weight:750;margin:14px 0}
        .secure{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}
        .secure span{padding:10px 12px;border-radius:14px;background:#f8fafc;border:1px solid var(--line);color:var(--muted);font-size:.9rem}
        @media(max-width:720px){.top,.grid{grid-template-columns:1fr;display:grid}.price{text-align:left}.secure{grid-template-columns:1fr}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <div class="top">
            <div>
                <span class="pill">Assinatura mensal</span>
                <h1>Assinar Studio Visagismo IA</h1>
                <p>Preencha seus dados para gerar o Pix. Depois da confirmacao do Asaas, seu acesso libera por 30 dias.</p>
            </div>
            <div class="price">
                <span>Total</span>
                <strong>R$ <?= h(number_format((float)env_value('SUBSCRIPTION_PRICE', '97.00'), 2, ',', '.')) ?></strong>
            </div>
        </div>

        <?php if ($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>

        <form method="post" action="api/asaas/checkout.php" novalidate>
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <div class="grid">
                <div class="field full">
                    <label for="name">Nome completo</label>
                    <input id="name" name="name" autocomplete="name" value="<?= old_value($old, 'name') ?>" required>
                </div>
                <div class="field">
                    <label for="business_name">Nome do salao/barbearia</label>
                    <input id="business_name" name="business_name" value="<?= old_value($old, 'business_name') ?>" placeholder="Ex: Barbearia Central">
                </div>
                <div class="field">
                    <label for="phone">Telefone/WhatsApp</label>
                    <input id="phone" name="phone" inputmode="numeric" autocomplete="tel" value="<?= old_value($old, 'phone') ?>" placeholder="(65) 99999-9999" required>
                    <p class="hint">Use DDD + numero. Exemplo: (65) 99999-9999.</p>
                </div>
                <div class="field">
                    <label for="email">E-mail de acesso</label>
                    <input id="email" name="email" type="email" autocomplete="email" value="<?= old_value($old, 'email') ?>" placeholder="voce@email.com" required>
                </div>
                <div class="field">
                    <label for="document">CPF ou CNPJ</label>
                    <input id="document" name="document" inputmode="numeric" autocomplete="off" value="<?= old_value($old, 'document') ?>" placeholder="000.000.000-00 ou 00.000.000/0000-00" required>
                    <p class="hint">Digite um CPF ou CNPJ real. Numeros repetidos ou incompletos nao sao aceitos pelo Asaas.</p>
                </div>
                <div class="field full">
                    <label for="password">Senha de acesso</label>
                    <input id="password" name="password" type="password" minlength="6" autocomplete="new-password" placeholder="Minimo de 6 caracteres" required>
                </div>
            </div>
            <button class="btn" type="submit">Gerar Pix de R$ <?= h(number_format((float)env_value('SUBSCRIPTION_PRICE', '97.00'), 2, ',', '.')) ?></button>
            <div class="secure">
                <span>Pagamento por Pix</span>
                <span>Confirmacao automatica</span>
                <span>Acesso por 30 dias</span>
            </div>
        </form>
    </section>
</main>
<script>
const onlyDigits = (value) => value.replace(/\D+/g, '');

function maskPhone(value) {
    const digits = onlyDigits(value).slice(0, 11);
    if (digits.length <= 10) {
        return digits
            .replace(/^(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{4})(\d)/, '$1-$2');
    }
    return digits
        .replace(/^(\d{2})(\d)/, '($1) $2')
        .replace(/(\d{5})(\d)/, '$1-$2');
}

function maskDocument(value) {
    const digits = onlyDigits(value).slice(0, 14);
    if (digits.length <= 11) {
        return digits
            .replace(/^(\d{3})(\d)/, '$1.$2')
            .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
            .replace(/\.(\d{3})(\d)/, '.$1-$2');
    }
    return digits
        .replace(/^(\d{2})(\d)/, '$1.$2')
        .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
        .replace(/\.(\d{3})(\d)/, '.$1/$2')
        .replace(/(\d{4})(\d)/, '$1-$2');
}

const phone = document.querySelector('#phone');
const documentInput = document.querySelector('#document');

phone.addEventListener('input', () => {
    phone.value = maskPhone(phone.value);
});
documentInput.addEventListener('input', () => {
    documentInput.value = maskDocument(documentInput.value);
});

phone.value = maskPhone(phone.value);
documentInput.value = maskDocument(documentInput.value);
</script>
</body>
</html>
