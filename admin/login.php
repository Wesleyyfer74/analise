<?php
require_once __DIR__ . '/../includes/auth.php';
start_secure_session();
$error = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        validate_csrf_token($_POST['csrf_token'] ?? null);
        if (login_user($_POST['email'] ?? '', $_POST['password'] ?? '', 'admin')) {
            header('Location: index.php', true, 303);
            exit;
        }
        $error = 'Login invalido. Confira o e-mail e a senha do admin.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin | Studio Visagismo IA</title>
    <style>
        :root{--bg:#f8f4ec;--text:#171412;--muted:#6e625a;--brand:#0f766e;--brand-dark:#0b4f4a;--line:rgba(23,20,18,.12);--danger:#b42318;--danger-bg:#fff1f0}
        *{box-sizing:border-box}
        body{min-height:100vh;margin:0;display:grid;place-items:center;color:var(--text);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;background:radial-gradient(circle at 84% 12%,rgba(15,118,110,.22),transparent 26rem),radial-gradient(circle at 12% 18%,rgba(217,163,95,.2),transparent 24rem),linear-gradient(135deg,#fffaf3,var(--bg))}
        .shell{width:min(960px,calc(100% - 28px));display:grid;grid-template-columns:.95fr 1.05fr;align-items:stretch;border:1px solid var(--line);border-radius:34px;overflow:hidden;background:rgba(255,255,255,.72);box-shadow:0 28px 90px rgba(43,30,20,.14);backdrop-filter:blur(12px)}
        .panel{padding:clamp(26px,5vw,46px)}
        .brand{display:flex;align-items:center;gap:12px;font-weight:950;letter-spacing:-.035em}
        .mark{width:44px;height:44px;border-radius:16px;background:linear-gradient(135deg,var(--brand-dark),var(--brand));box-shadow:0 16px 34px rgba(15,118,110,.22)}
        .visual{position:relative;min-height:520px;color:#fff;background:radial-gradient(circle at 72% 20%,rgba(255,255,255,.32),transparent 18rem),linear-gradient(135deg,rgba(11,79,74,.98),rgba(15,118,110,.9))}
        .visual:before{content:"";position:absolute;right:-110px;top:-80px;width:360px;height:360px;border-radius:999px;background:rgba(255,255,255,.18)}
        .visual:after{content:"";position:absolute;left:-120px;bottom:-130px;width:360px;height:360px;border-radius:999px;background:rgba(217,163,95,.3)}
        .visual-content{position:absolute;left:34px;right:34px;bottom:34px;z-index:1;padding:22px;border:1px solid rgba(255,255,255,.18);border-radius:26px;background:rgba(255,255,255,.12);backdrop-filter:blur(14px)}
        .visual h2{margin:0 0 10px;font-size:clamp(2rem,4vw,3.15rem);line-height:1;letter-spacing:-.06em}
        .visual p{margin:0;color:rgba(255,255,255,.84);line-height:1.65}
        h1{margin:42px 0 10px;font-size:clamp(2rem,5vw,3rem);line-height:1;letter-spacing:-.06em}
        p{color:var(--muted);line-height:1.65}
        form{margin-top:24px}
        label{display:block;margin:16px 0 7px;font-weight:850}
        input{width:100%;padding:15px 16px;border:1px solid #d8dedf;border-radius:16px;background:#fff;font:inherit;outline:none;transition:border-color .18s ease,box-shadow .18s ease}
        input:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(15,118,110,.12)}
        .btn{width:100%;margin-top:20px;padding:16px;border:0;border-radius:17px;color:#fff;background:linear-gradient(135deg,var(--brand-dark),var(--brand));box-shadow:0 18px 38px rgba(15,118,110,.24);font:inherit;font-weight:950;cursor:pointer}
        .err{padding:12px 14px;border-radius:14px;background:var(--danger-bg);color:var(--danger);font-weight:780}
        .hint{margin-top:16px;font-size:.92rem}
        @media(max-width:820px){.shell{grid-template-columns:1fr}.visual{display:none}.panel{padding:26px}.brand{justify-content:center}h1{text-align:center;margin-top:30px}p{text-align:center}}
    </style>
</head>
<body>
<main class="shell">
    <section class="panel">
        <div class="brand"><span class="mark"></span><span>Studio Visagismo IA</span></div>
        <h1>Acesso administrativo</h1>
        <p>Entre para gerenciar clientes, vendas, assinaturas, tokens e o split financeiro da dona do sistema.</p>
        <?php if($error): ?><div class="err"><?= h($error) ?></div><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label for="email">E-mail do admin</label>
            <input id="email" name="email" type="email" autocomplete="username" placeholder="admin@gerador.spacered.com.br" required>
            <label for="password">Senha</label>
            <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Digite sua senha" required>
            <button class="btn" type="submit">Entrar no painel</button>
        </form>
        <p class="hint">Use o e-mail e a senha salvos nas variaveis ADMIN_EMAIL e ADMIN_PASSWORD do Railway.</p>
    </section>
    <section class="visual">
        <div class="visual-content">
            <h2>Painel da operacao</h2>
            <p>Controle clientes, acompanhe pagamentos e configure a subconta Asaas para dividir automaticamente cada assinatura.</p>
        </div>
    </section>
</main>
</body>
</html>
