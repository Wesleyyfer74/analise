<?php
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    try {
        validate_csrf_token($_POST['csrf_token'] ?? null);
        if (login_user($_POST['email'] ?? '', $_POST['password'] ?? '', 'client')) {
            header('Location: painel.php', true, 303);
            exit;
        }
        $error = 'E-mail ou senha inválidos.';
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
    <title>Área do Cliente | Studio Visagismo IA</title>
    <style>
        :root {
            --bg: #f8f4ec;
            --surface: #fffdf8;
            --text: #171412;
            --muted: #70655d;
            --brand: #0f766e;
            --brand-dark: #0b4f4a;
            --brand-soft: #d9f1ed;
            --gold: #d9a35f;
            --line: rgba(23,20,18,.12);
            --danger: #b42318;
            --danger-bg: #fff1f0;
            --shadow: 0 28px 80px rgba(43,30,20,.13);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at 92% 0%, rgba(15,118,110,.16), transparent 28rem),
                radial-gradient(circle at 8% 10%, rgba(217,163,95,.22), transparent 25rem),
                var(--bg);
        }

        a { color: inherit; }

        .shell {
            width: min(1080px, calc(100% - 32px));
            min-height: 100vh;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 440px;
            gap: 34px;
            align-items: center;
            padding: 42px 0;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 36px;
            font-weight: 950;
            letter-spacing: -.035em;
        }

        .brand img {
            width: 54px;
            height: 54px;
            object-fit: contain;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            padding: 9px 14px;
            border-radius: 999px;
            color: var(--brand-dark);
            background: var(--brand-soft);
            font-size: .76rem;
            font-weight: 950;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        h1 {
            margin: 22px 0 18px;
            max-width: 620px;
            font-size: clamp(2.8rem, 7vw, 5.2rem);
            line-height: .94;
            letter-spacing: -.065em;
        }

        .accent { color: var(--brand); }

        p {
            color: var(--muted);
            line-height: 1.68;
            font-size: 1.04rem;
        }

        .lead {
            max-width: 570px;
            margin-bottom: 28px;
            font-size: clamp(1.05rem, 1.8vw, 1.2rem);
        }

        .quick-list {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            max-width: 620px;
        }

        .quick {
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 20px;
            background: rgba(255,255,255,.72);
            box-shadow: 0 16px 40px rgba(43,30,20,.06);
        }

        .quick strong {
            display: block;
            margin-bottom: 5px;
            font-size: .92rem;
        }

        .quick span {
            color: var(--muted);
            font-size: .86rem;
            line-height: 1.45;
        }

        .panel {
            position: relative;
            overflow: hidden;
            padding: 30px;
            border: 1px solid var(--line);
            border-radius: 32px;
            background: rgba(255,255,255,.88);
            box-shadow: var(--shadow);
            backdrop-filter: blur(14px);
        }

        .panel:before {
            content: "";
            position: absolute;
            right: -54px;
            top: -54px;
            width: 150px;
            height: 150px;
            border-radius: 999px;
            background: rgba(15,118,110,.11);
        }

        .panel h2 {
            position: relative;
            margin: 0 0 8px;
            font-size: 2rem;
            letter-spacing: -.045em;
        }

        .panel .subtitle {
            position: relative;
            margin: 0 0 22px;
            font-size: .98rem;
        }

        .err {
            position: relative;
            margin: 0 0 16px;
            padding: 12px 14px;
            border-radius: 16px;
            color: var(--danger);
            background: var(--danger-bg);
            font-weight: 800;
        }

        form {
            position: relative;
            display: grid;
            gap: 14px;
        }

        label {
            display: grid;
            gap: 7px;
            color: var(--text);
            font-weight: 850;
        }

        input {
            width: 100%;
            padding: 15px 15px;
            border: 1px solid #d8dedf;
            border-radius: 16px;
            background: #fff;
            color: var(--text);
            font: inherit;
            outline: none;
        }

        input:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 4px rgba(15,118,110,.11);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 52px;
            width: 100%;
            margin-top: 8px;
            padding: 14px 18px;
            border: 0;
            border-radius: 17px;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            box-shadow: 0 18px 38px rgba(15,118,110,.24);
            font: inherit;
            font-weight: 950;
            cursor: pointer;
        }

        .signup {
            position: relative;
            margin: 20px 0 0;
            padding: 16px;
            border-radius: 20px;
            background: #f8f4ec;
            text-align: center;
        }

        .signup p {
            margin: 0 0 10px;
            font-size: .94rem;
        }

        .signup a {
            color: var(--brand-dark);
            font-weight: 950;
            text-decoration: none;
        }

        @media (max-width: 860px) {
            .shell {
                grid-template-columns: 1fr;
                gap: 24px;
            }

            .intro {
                text-align: center;
            }

            .brand {
                justify-content: center;
                margin-bottom: 24px;
            }

            .lead {
                margin-left: auto;
                margin-right: auto;
            }

            .quick-list {
                grid-template-columns: 1fr;
                max-width: 440px;
                margin: 0 auto;
                text-align: left;
            }
        }

        @media (max-width: 520px) {
            .shell {
                width: min(100% - 24px, 1080px);
                padding: 24px 0;
            }

            .panel {
                padding: 24px;
                border-radius: 26px;
            }
        }
    </style>
</head>
<body>
<main class="shell">
    <section class="intro">
        <div class="brand">
            <img src="../assets/ai.png" alt="Studio Visagismo IA">
            <span>Studio Visagismo IA</span>
        </div>
        <span class="eyebrow">Área do cliente</span>
        <h1>Entre no painel e comece sua <span class="accent">análise visual</span>.</h1>
        <p class="lead">Acesse sua assinatura para enviar as fotos do cliente, informar as preferências de corte e gerar uma recomendação profissional com inteligência artificial.</p>
        <div class="quick-list">
            <div class="quick"><strong>Fotos do cliente</strong><span>Envie duas imagens para orientar a análise.</span></div>
            <div class="quick"><strong>Preferências</strong><span>Informe o estilo desejado antes de gerar a prévia.</span></div>
            <div class="quick"><strong>Resultado visual</strong><span>Apresente uma sugestão mais clara no atendimento.</span></div>
        </div>
    </section>

    <section class="panel">
        <h2>Acessar painel</h2>
        <p class="subtitle">Use o e-mail e a senha cadastrados na assinatura.</p>

        <?php if ($error): ?>
            <p class="err"><?= h($error) ?></p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
            <label>
                E-mail de acesso
                <input name="email" type="email" autocomplete="email" placeholder="seu@email.com" required>
            </label>
            <label>
                Senha
                <input name="password" type="password" autocomplete="current-password" placeholder="Digite sua senha" required>
            </label>
            <button class="btn" type="submit">Entrar no painel</button>
        </form>

        <div class="signup">
            <p>Ainda não tem assinatura ativa?</p>
            <a href="../comprar.php">Assinar por Pix</a>
        </div>
    </section>
</main>
</body>
</html>
