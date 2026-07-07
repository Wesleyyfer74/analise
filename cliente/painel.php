<?php
require_once __DIR__ . '/../includes/auth.php';

start_secure_session();

$user = require_login();
if ($user['role'] !== 'client') {
    header('Location: ../admin/index.php', true, 303);
    exit;
}

$profile = client_profile($user['id']);
$active = subscription_active($profile);
$days = days_until_expiration($profile);
$tokens = (int)($profile['tokens_remaining'] ?? 0);
$quota = (int)($profile['monthly_token_quota'] ?? env_value('MONTHLY_TOKEN_QUOTA', 30));
$payments = db_fetch_all('SELECT * FROM payments WHERE user_id = ? ORDER BY id DESC LIMIT 5', [$user['id']]);
$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

function payment_status_label($status) {
    $status = strtolower((string)$status);
    return [
        'paid' => 'Pago',
        'received' => 'Pago',
        'confirmed' => 'Confirmado',
        'pending' => 'Aguardando pagamento',
        'overdue' => 'Vencido',
        'canceled' => 'Cancelado',
        'cancelled' => 'Cancelado',
        'refunded' => 'Estornado',
    ][$status] ?? ucfirst($status ?: 'Pendente');
}

function payment_status_class($status) {
    $status = strtolower((string)$status);
    if (in_array($status, ['paid', 'received', 'confirmed'], true)) {
        return 'ok';
    }
    if (in_array($status, ['overdue', 'canceled', 'cancelled', 'refunded'], true)) {
        return 'bad';
    }
    return 'wait';
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Painel do Cliente | Studio Visagismo IA</title>
    <style>
        :root {
            --bg: #f8f4ec;
            --surface: #fffdf8;
            --card: #ffffff;
            --text: #171412;
            --muted: #70655d;
            --brand: #0f766e;
            --brand-dark: #0b4f4a;
            --brand-soft: #d9f1ed;
            --gold: #d9a35f;
            --line: rgba(23,20,18,.11);
            --danger: #b42318;
            --danger-bg: #fff1f0;
            --warning: #a15c07;
            --warning-bg: #fff8e6;
            --ok: #027a48;
            --ok-bg: #ecfdf3;
            --shadow: 0 24px 76px rgba(43,30,20,.11);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
            background:
                radial-gradient(circle at 92% 0%, rgba(15,118,110,.14), transparent 28rem),
                radial-gradient(circle at 8% 10%, rgba(217,163,95,.20), transparent 24rem),
                var(--bg);
        }

        a { color: inherit; }

        .wrap {
            width: min(1160px, calc(100% - 32px));
            margin: 0 auto;
            padding: 22px 0 56px;
        }

        .topbar {
            position: sticky;
            top: 14px;
            z-index: 10;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
            padding: 10px;
            border: 1px solid var(--line);
            border-radius: 24px;
            background: rgba(255,255,255,.80);
            box-shadow: 0 16px 46px rgba(43,30,20,.08);
            backdrop-filter: blur(14px);
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-right: auto;
            padding: 7px 13px 7px 8px;
            border-radius: 16px;
            background: #fff;
            text-decoration: none;
            font-weight: 950;
            letter-spacing: -.035em;
        }

        .brand img {
            width: 34px;
            height: 34px;
            object-fit: contain;
        }

        .nav-link,
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 14px;
            border: 1px solid var(--line);
            border-radius: 15px;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            font-weight: 850;
            cursor: pointer;
        }

        .btn-primary {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            box-shadow: 0 18px 38px rgba(15,118,110,.20);
        }

        .hero {
            display: grid;
            grid-template-columns: minmax(0,1fr) auto;
            gap: 22px;
            align-items: center;
            margin-bottom: 18px;
            padding: 28px;
            border: 1px solid var(--line);
            border-radius: 32px;
            background:
                radial-gradient(circle at 86% 18%, rgba(217,163,95,.30), transparent 22rem),
                linear-gradient(135deg, rgba(255,255,255,.94), rgba(255,253,248,.82));
            box-shadow: var(--shadow);
        }

        .eyebrow {
            display: inline-flex;
            padding: 8px 12px;
            border-radius: 999px;
            color: var(--brand-dark);
            background: var(--brand-soft);
            font-size: .76rem;
            font-weight: 950;
            text-transform: uppercase;
            letter-spacing: .09em;
        }

        h1, h2, h3 { letter-spacing: -.045em; }

        h1 {
            margin: 14px 0 10px;
            font-size: clamp(2.25rem, 5.6vw, 4rem);
            line-height: .98;
        }

        h2 {
            margin: 0 0 14px;
            font-size: clamp(1.35rem, 3vw, 2rem);
            line-height: 1.08;
        }

        p {
            color: var(--muted);
            line-height: 1.62;
        }

        .hero p {
            max-width: 680px;
            margin: 0;
            font-size: 1.04rem;
        }

        .hero-badge {
            min-width: 220px;
            padding: 22px;
            border-radius: 26px;
            color: #fff;
            background: linear-gradient(135deg, var(--brand-dark), var(--brand));
            box-shadow: 0 22px 52px rgba(15,118,110,.20);
        }

        .hero-badge span {
            display: block;
            color: rgba(255,255,255,.78);
            font-weight: 800;
        }

        .hero-badge strong {
            display: block;
            margin-top: 6px;
            font-size: 2.3rem;
            line-height: 1;
            letter-spacing: -.06em;
        }

        .notice {
            margin-bottom: 16px;
            padding: 14px 16px;
            border-radius: 18px;
            font-weight: 850;
            line-height: 1.55;
        }

        .notice.err { color: var(--danger); background: var(--danger-bg); }
        .notice.warn { color: var(--warning); background: var(--warning-bg); }

        .grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .card {
            position: relative;
            overflow: hidden;
            padding: 22px;
            border: 1px solid var(--line);
            border-radius: 26px;
            background: rgba(255,255,255,.86);
            box-shadow: 0 16px 46px rgba(43,30,20,.07);
        }

        .stat:after {
            content: "";
            position: absolute;
            right: -42px;
            top: -42px;
            width: 110px;
            height: 110px;
            border-radius: 999px;
            background: rgba(15,118,110,.10);
        }

        .stat-label {
            margin: 0;
            color: var(--muted);
            font-size: .86rem;
            text-transform: uppercase;
            letter-spacing: .08em;
            font-weight: 900;
        }

        .stat-value {
            margin: 10px 0 0;
            font-size: clamp(2rem, 4vw, 2.6rem);
            line-height: 1;
            font-weight: 950;
            letter-spacing: -.06em;
        }

        .stat-help {
            margin: 9px 0 0;
            font-size: .92rem;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 7px 11px;
            border-radius: 999px;
            font-size: .83rem;
            font-weight: 900;
        }

        .status-pill.ok { color: var(--ok); background: var(--ok-bg); }
        .status-pill.wait { color: var(--warning); background: var(--warning-bg); }
        .status-pill.bad { color: var(--danger); background: var(--danger-bg); }

        .cta-card {
            display: grid;
            grid-template-columns: minmax(0,1fr) auto;
            gap: 18px;
            align-items: center;
            margin-bottom: 16px;
            padding: 24px;
            border-radius: 30px;
            color: #fff;
            background: linear-gradient(135deg, #0b4f4a, #0f766e);
            box-shadow: 0 24px 70px rgba(15,118,110,.20);
        }

        .cta-card h2 {
            margin-bottom: 8px;
            color: #fff;
        }

        .cta-card p {
            margin: 0;
            color: rgba(255,255,255,.82);
        }

        .cta-card .btn {
            min-width: 190px;
            color: var(--brand-dark);
            background: #fff;
        }

        .payments {
            margin-top: 0;
        }

        .payment-row {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 14px;
            align-items: center;
            padding: 14px 0;
            border-top: 1px solid rgba(23,20,18,.08);
        }

        .payment-row:first-of-type {
            border-top: 0;
            padding-top: 0;
        }

        .payment-title {
            margin: 0;
            color: var(--text);
            font-weight: 900;
        }

        .payment-date {
            display: block;
            margin-top: 3px;
            color: var(--muted);
            font-size: .88rem;
        }

        .amount {
            color: var(--text);
            font-weight: 950;
        }

        .empty {
            padding: 20px;
            border-radius: 18px;
            color: var(--muted);
            background: #faf7f1;
            text-align: center;
        }

        @media (max-width: 860px) {
            .topbar {
                position: static;
                flex-wrap: wrap;
            }

            .brand {
                width: 100%;
                justify-content: center;
                margin-right: 0;
            }

            .nav-link {
                flex: 1 1 auto;
            }

            .hero,
            .cta-card {
                grid-template-columns: 1fr;
            }

            .hero-badge {
                min-width: 0;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 640px) {
            .wrap {
                width: min(100% - 24px, 1160px);
                padding-top: 14px;
            }

            .hero,
            .card,
            .cta-card {
                padding: 20px;
                border-radius: 24px;
            }

            .payment-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .cta-card .btn,
            .btn-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<main class="wrap">
    <nav class="topbar">
        <a class="brand" href="painel.php">
            <img src="../assets/ai.png" alt="Studio Visagismo IA">
            <span>Studio IA</span>
        </a>
        <a class="nav-link" href="../app.php">Analisador</a>
        <a class="nav-link" href="../comprar.php">Renovar</a>
        <a class="nav-link" href="logout.php">Sair</a>
    </nav>

    <section class="hero">
        <div>
            <span class="eyebrow">Painel do cliente</span>
            <h1>Olá, <?= h($user['name']) ?>.</h1>
            <p>Acompanhe sua assinatura, veja quantas análises ainda estão disponíveis e acesse o analisador para atender seus clientes.</p>
        </div>
        <div class="hero-badge">
            <span>Análises disponíveis</span>
            <strong><?= h($tokens) ?></strong>
        </div>
    </section>

    <?php if ($error): ?>
        <div class="notice err"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($active && $days !== null && $days <= 3): ?>
        <div class="notice warn">Sua assinatura expira em <?= h($days) ?> dia(s). Renove para evitar o bloqueio automático após o vencimento.</div>
    <?php endif; ?>

    <?php if (!$active): ?>
        <div class="notice err">Sua assinatura está inativa ou expirada. Renove para liberar o analisador.</div>
    <?php elseif ($tokens <= 0): ?>
        <div class="notice warn">Suas análises disponíveis acabaram. Renove a assinatura para continuar usando o sistema.</div>
    <?php endif; ?>

    <section class="grid">
        <article class="card stat">
            <p class="stat-label">Assinatura</p>
            <div class="stat-value"><?= $active ? 'Ativa' : 'Inativa' ?></div>
            <p class="stat-help"><?= $days !== null ? h($days) . ' dia(s) restantes' : 'Aguardando confirmação do pagamento' ?></p>
        </article>

        <article class="card stat">
            <p class="stat-label">Análises disponíveis</p>
            <div class="stat-value"><?= h($tokens) ?></div>
            <p class="stat-help">Plano mensal com <?= h($quota) ?> análise(s).</p>
        </article>

        <article class="card stat">
            <p class="stat-label">Status de uso</p>
            <div class="stat-value"><?= ($active && $tokens > 0) ? 'Liberado' : 'Bloqueado' ?></div>
            <p class="stat-help"><?= ($active && $tokens > 0) ? 'Você já pode iniciar uma nova análise.' : 'Renove para voltar a usar.' ?></p>
        </article>
    </section>

    <section class="cta-card">
        <div>
            <?php if ($active && $tokens > 0): ?>
                <h2>Pronto para uma nova análise?</h2>
                <p>Envie duas fotos, informe as preferências do cliente e gere uma recomendação visual para apresentar no atendimento.</p>
            <?php else: ?>
                <h2>Renove sua assinatura para continuar.</h2>
                <p>Depois da confirmação do Pix, o sistema libera automaticamente seu acesso por mais 30 dias.</p>
            <?php endif; ?>
        </div>
        <?php if ($active && $tokens > 0): ?>
            <a class="btn" href="../app.php">Abrir analisador</a>
        <?php else: ?>
            <a class="btn" href="../comprar.php">Renovar por Pix</a>
        <?php endif; ?>
    </section>

    <section class="card payments">
        <h2>Últimos pagamentos</h2>
        <?php if (!$payments): ?>
            <div class="empty">Nenhum pagamento encontrado ainda.</div>
        <?php else: ?>
            <?php foreach ($payments as $payment): ?>
                <div class="payment-row">
                    <div>
                        <p class="payment-title">Assinatura Studio Visagismo IA</p>
                        <span class="payment-date"><?= h(!empty($payment['created_at']) ? date('d/m/Y H:i', strtotime($payment['created_at'])) : '-') ?></span>
                    </div>
                    <span class="status-pill <?= h(payment_status_class($payment['status'] ?? '')) ?>"><?= h(payment_status_label($payment['status'] ?? '')) ?></span>
                    <span class="amount">R$ <?= h(number_format((float)$payment['amount'], 2, ',', '.')) ?></span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
