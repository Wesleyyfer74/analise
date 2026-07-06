<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
start_secure_session();
$user = current_user();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Visagismo IA para barbeiros e visagistas</title>
    <style>
        :root{--bg:#0f172a;--card:#111c33;--text:#f8fafc;--muted:#b6c2d6;--primary:#8b5cf6;--line:rgba(255,255,255,.12)}
        *{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at top right,rgba(139,92,246,.28),transparent 34rem),var(--bg);color:var(--text);font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
        a{color:inherit}.shell{width:min(1120px,calc(100% - 32px));margin:0 auto;padding:28px 0 64px}
        .nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:56px}.brand{font-weight:900;letter-spacing:-.03em}.nav a{padding:10px 14px;text-decoration:none;border:1px solid var(--line);border-radius:12px}
        .hero{display:grid;grid-template-columns:1.15fr .85fr;gap:28px;align-items:center}.card{border:1px solid var(--line);border-radius:28px;background:rgba(255,255,255,.06);box-shadow:0 24px 70px rgba(0,0,0,.24)}
        .copy{padding:36px}.eyebrow{color:#c4b5fd;font-size:.8rem;font-weight:800;text-transform:uppercase;letter-spacing:.14em}h1{font-size:clamp(2.4rem,7vw,4.8rem);line-height:.98;letter-spacing:-.07em;margin:14px 0}p{color:var(--muted);line-height:1.7;font-size:1.03rem}
        .actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}.btn{display:inline-flex;align-items:center;justify-content:center;min-height:52px;padding:14px 18px;border-radius:14px;text-decoration:none;font-weight:850}.btn-primary{background:linear-gradient(135deg,var(--primary),#a78bfa);color:white}.btn-soft{background:rgba(255,255,255,.09);border:1px solid var(--line)}
        .price{padding:28px}.price h2{font-size:2.4rem;margin:0 0 4px}.list{display:grid;gap:12px;margin:22px 0}.item{padding:12px 14px;border:1px solid var(--line);border-radius:14px;color:var(--muted)}
        .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:28px}.feature{padding:20px}.feature h3{margin:0 0 8px}
        @media(max-width:800px){.hero,.grid{grid-template-columns:1fr}.nav{align-items:flex-start;gap:10px;flex-direction:column}.copy,.price{padding:24px}}
    </style>
</head>
<body>
<main class="shell">
    <nav class="nav">
        <div class="brand">Studio Visagismo IA</div>
        <div>
            <?php if ($user): ?>
                <a href="<?= $user['role'] === 'admin' ? 'admin/index.php' : 'cliente/painel.php' ?>">Meu painel</a>
            <?php else: ?>
                <a href="cliente/login.php">Entrar</a>
            <?php endif; ?>
        </div>
    </nav>

    <section class="hero">
        <div class="card copy">
            <div class="eyebrow">Sistema para barbeiros e visagistas</div>
            <h1>Mostre o corte ideal antes da tesoura.</h1>
            <p>Seu cliente envia duas fotos, informa preferências e a IA gera uma análise visual com sugestões de corte, cores e uma prévia realista do novo visual.</p>
            <div class="actions">
                <a class="btn btn-primary" href="comprar.php">Assinar por Pix</a>
                <a class="btn btn-soft" href="cliente/login.php">Acessar minha conta</a>
            </div>
        </div>
        <aside class="card price">
            <p>Assinatura mensal</p>
            <h2>R$ <?= h(number_format((float)env_value('SUBSCRIPTION_PRICE', '97.00'), 2, ',', '.')) ?></h2>
            <p>Liberação por 30 dias após confirmação do Pix via Asaas.</p>
            <div class="list">
                <div class="item"><?= h(env_value('MONTHLY_TOKEN_QUOTA', '30')) ?> análises por ciclo</div>
                <div class="item">Alerta 3 dias antes de expirar</div>
                <div class="item">Bloqueio automático após vencimento</div>
            </div>
            <a class="btn btn-primary" href="comprar.php" style="width:100%">Começar agora</a>
        </aside>
    </section>

    <section class="grid">
        <div class="card feature"><h3>Análise facial</h3><p>Formato de rosto, textura de cabelo, subtom aparente e recomendações práticas.</p></div>
        <div class="card feature"><h3>Prévia visual</h3><p>Imagem gerada preservando a identidade da pessoa e alterando principalmente o cabelo.</p></div>
        <div class="card feature"><h3>Gestão simples</h3><p>Cliente acessa seu painel, acompanha tokens e renova a assinatura por Pix.</p></div>
    </section>
</main>
</body>
</html>
