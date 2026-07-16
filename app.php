<?php
/**
 * Studio Visagismo IA - Frontend PHP mobile-first.
 */

declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

start_request_debug();
start_secure_session();
cleanup_expired_reports();
[$logged_user, $client_profile] = require_active_client();

$error = $_SESSION['error'] ?? null;
unset($_SESSION['error']);

$report_id = $_GET['report_id'] ?? $_SESSION['report_id'] ?? null;
$result = null;
$frontal_url = null;
$angle_url = null;
$preview_url = null;
$current_image_url = null;
$selected_image_url = null;
$final_image_url = null;
$generations = [];
$workflow_status = 'analysis_ready';

if (!empty($report_id) && is_string($report_id)) {
    try {
        authorize_report($report_id);
        $report = load_report($report_id);
        $result = $report['analysis'] ?? null;
        $frontal_url = $report['frontal_url'] ?? null;
        $angle_url = $report['angle_url'] ?? null;
        $preview_url = $report['preview_url'] ?? null;
        $current_image_url = $report['current_image_url'] ?? $preview_url;
        $selected_image_url = $report['selected_image_url'] ?? null;
        $final_image_url = $report['final_image_url'] ?? null;
        $generations = is_array($report['generations'] ?? null) ? $report['generations'] : [];
        $workflow_status = $report['workflow_status'] ?? 'analysis_ready';
    } catch (Throwable $e) {
        debug_log('ERRO CARREGANDO RELATORIO NO INDEX', ['error' => $e->getMessage()], 'ERROR');
        $error = $e->getMessage();
        $report_id = null;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0f766e">
    <title>Studio Visagismo IA</title>
    <style>
        :root {
            --bg: #f8f4ec;
            --surface: #fffdf8;
            --surface-soft: #faf7f1;
            --text: #171412;
            --muted: #70655d;
            --line: rgba(23,20,18,.11);
            --primary: #0f766e;
            --primary-dark: #0b4f4a;
            --primary-soft: #d9f1ed;
            --gold: #d9a35f;
            --brown: #7b4a2a;
            --danger: #b42318;
            --danger-soft: #fff1f0;
            --shadow: 0 24px 76px rgba(43,30,20,.11);
            --radius-lg: 30px;
            --radius-md: 20px;
            --radius-sm: 15px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; min-height: 100vh; color: var(--text);
            background:
                radial-gradient(circle at 92% 0%, rgba(15,118,110,.14), transparent 28rem),
                radial-gradient(circle at 8% 10%, rgba(217,163,95,.20), transparent 24rem),
                var(--bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        button, input, select, textarea { font: inherit; }
        .page-shell { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 28px 0 56px; }
        .hero, .card {
            border: 1px solid var(--line);
            border-radius: var(--radius-lg);
            background: rgba(255,255,255,.88);
            box-shadow: var(--shadow);
        }
        .hero {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0,1fr) auto;
            gap: 24px;
            align-items: center;
            margin-bottom: 24px;
            padding: 30px;
            overflow: hidden;
            background:
                radial-gradient(circle at 85% 16%, rgba(217,163,95,.30), transparent 20rem),
                linear-gradient(135deg, rgba(255,255,255,.96), rgba(255,253,248,.84));
        }
        .hero:before {
            content: "";
            position: absolute;
            inset: auto -80px -110px auto;
            width: 260px;
            height: 260px;
            border-radius: 999px;
            background: rgba(15,118,110,.10);
        }
        .hero > * { position: relative; }
        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            margin-bottom: 14px;
            padding: 8px 12px;
            border-radius: 999px;
            color: var(--primary-dark);
            background: var(--primary-soft);
            font-size: .76rem;
            font-weight: 950;
            letter-spacing: .1em;
            text-transform: uppercase;
        }
        .eyebrow-dot { width: 9px; height: 9px; border-radius: 50%; background: var(--primary); box-shadow: 0 0 0 6px rgba(15,118,110,.12); }
        h1, h2, h3, p { margin-top: 0; }
        h1 { max-width: 800px; margin-bottom: 12px; font-size: clamp(2.1rem,5vw,3.65rem); line-height: .98; letter-spacing: -.06em; }
        .hero p { max-width: 760px; margin-bottom: 0; color: var(--muted); font-size: 1.03rem; line-height: 1.65; }
        .hero-mark { display: grid; width: 106px; height: 106px; place-items: center; border-radius: 32px; background: #fff; box-shadow: 0 22px 48px rgba(15,118,110,.22); }
        .hero-mark img { width: 74px; height: 74px; object-fit: contain; }
        .card { margin-bottom: 20px; }
        .card-body { padding: 26px; }
        .card-heading { display: flex; gap: 14px; align-items: flex-start; margin-bottom: 20px; }
        .step { display: grid; flex: 0 0 auto; width: 36px; height: 36px; place-items: center; border-radius: 16px; background: linear-gradient(135deg,var(--primary),#56b8ae); color: #fff; font-weight: 950; box-shadow: 0 12px 24px rgba(15,118,110,.18); }
        .card-heading h2 { margin-bottom: 5px; font-size: 1.35rem; letter-spacing: -.035em; }
        .card-heading p { margin-bottom: 0; color: var(--muted); font-size: .94rem; line-height: 1.55; }
        .form-grid, .photo-grid, .report-grid, .recommendation-grid { display: grid; gap: 16px; grid-template-columns: repeat(2,minmax(0,1fr)); }
        .field { min-width: 0; }
        label { display: block; margin-bottom: 8px; color: var(--text); font-size: .9rem; font-weight: 850; }
        input[type="file"], select, textarea {
            width: 100%;
            border: 1px solid rgba(23,20,18,.14);
            border-radius: var(--radius-sm);
            background: #fff;
            color: var(--text);
            outline: none;
            transition: border-color 160ms ease, box-shadow 160ms ease, background 160ms ease;
        }
        input[type="file"] { padding: 10px; }
        input[type="file"]::file-selector-button { margin-right: 10px; padding: 10px 13px; border: 0; border-radius: 11px; background: var(--primary-soft); color: var(--primary-dark); cursor: pointer; font-weight: 950; }
        select, textarea { padding: 14px 15px; }
        textarea { min-height: 104px; resize: vertical; line-height: 1.5; }
        input:focus, select:focus, textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(15,118,110,.11); }
        .helper { margin-top: 7px; color: var(--muted); font-size: .8rem; line-height: 1.45; }
        .consent { display: flex; gap: 10px; align-items: flex-start; margin-top: 18px; color: #4d4640; font-size: .86rem; line-height: 1.5; }
        .consent input { flex: 0 0 auto; width: 18px; height: 18px; margin-top: 2px; accent-color: var(--primary); }
        .btn { display: inline-flex; min-height: 48px; align-items: center; justify-content: center; gap: 8px; border: 0; border-radius: 999px; padding: 12px 20px; cursor: pointer; text-decoration: none; font-weight: 950; transition: transform 150ms ease, box-shadow 150ms ease, background 150ms ease; }
        .btn:hover { transform: translateY(-1px); }
        .btn-primary { background: linear-gradient(135deg,var(--primary-dark),var(--primary)); color: white; box-shadow: 0 16px 34px rgba(15,118,110,.22); }
        .btn-primary:hover { background: linear-gradient(135deg,#083f3b,var(--primary)); }
        .btn-secondary { border: 1px solid var(--line); background: #fff; color: var(--primary-dark); }
        .btn-lg { min-height: 56px; padding: 15px 22px; font-size: 1rem; }
        .form-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 18px; }
        .alert { margin-bottom: 20px; border-radius: var(--radius-md); padding: 14px 16px; line-height: 1.55; }
        .alert-error { border: 1px solid #fecdca; background: var(--danger-soft); color: var(--danger); }
        .restore-card { display: none; margin-bottom: 20px; border: 1px solid rgba(15,118,110,.18); border-radius: var(--radius-md); padding: 18px; background: rgba(217,241,237,.72); }
        .restore-card.visible { display: block; }
        .restore-card p { margin: 0 0 10px; color: #36534f; line-height: 1.55; }
        .photo-card { overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius-md); background: var(--surface-soft); }
        .photo-card h3 { margin: 0; padding: 13px 15px; color: #4d4640; font-size: .87rem; font-weight: 900; }
        .photo-card img { display: block; width: 100%; max-height: 360px; object-fit: cover; background: #efe8dc; }
        .badge-row { display: flex; flex-wrap: wrap; gap: 9px; margin-bottom: 18px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; border-radius: 999px; padding: 8px 12px; background: var(--primary-soft); color: var(--primary-dark); font-size: .82rem; font-weight: 900; }
        .report-section { padding: 18px; border: 1px solid var(--line); border-radius: var(--radius-md); background: var(--surface-soft); margin-top: 16px; }
        .report-section h3 { margin-bottom: 8px; color: var(--text); font-size: 1rem; letter-spacing: -.015em; }
        .report-section p { margin-bottom: 0; color: var(--muted); line-height: 1.65; }
        .report-section ul { margin: 0; padding-left: 18px; color: var(--muted); line-height: 1.7; }
        .preview-callout { margin-top: 22px; overflow: hidden; border: 1px solid rgba(15,118,110,.18); border-radius: var(--radius-lg); background: linear-gradient(135deg,rgba(217,241,237,.78),rgba(255,255,255,.94)); }
        .preview-callout-inner { display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 18px; align-items: center; padding: 22px; }
        .preview-callout h3 { margin-bottom: 7px; font-size: 1.25rem; letter-spacing: -.025em; }
        .preview-callout p { margin-bottom: 0; color: var(--muted); line-height: 1.55; }
        .preview-stage { overflow: hidden; border: 1px solid var(--line); border-radius: var(--radius-lg); background: #171412; box-shadow: var(--shadow); margin-top: 18px; }
        .preview-stage img { display: block; width: 100%; max-height: 1000px; object-fit: contain; background: #171412; }
        .preview-caption { padding: 15px 18px; background: white; color: var(--muted); font-size: .92rem; line-height: 1.55; }
        .preview-form { display: grid; gap: 14px; margin-top: 16px; }
        .preview-form-grid { display: grid; gap: 14px; grid-template-columns: minmax(0,.85fr) minmax(0,1.15fr); }
        .choice-panel { margin-top: 18px; border: 1px solid rgba(15,118,110,.18); border-radius: var(--radius-md); padding: 18px; background: rgba(217,241,237,.52); }
        .choice-panel h3 { margin-bottom: 7px; font-size: 1.05rem; }
        .choice-panel p { margin-bottom: 0; color: var(--muted); line-height: 1.55; }
        .generation-list { display: grid; gap: 10px; margin-top: 16px; }
        .generation-item { display: flex; justify-content: space-between; gap: 12px; border: 1px solid var(--line); border-radius: var(--radius-sm); padding: 12px 14px; background: var(--surface-soft); color: var(--muted); font-size: .88rem; }
        .generation-item strong { color: var(--text); }
        .stack-actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 16px; }
        .stack-actions form { margin: 0; }
        .loading-overlay { position: fixed; z-index: 999; inset: 0; display: none; place-items: center; padding: 20px; background: rgba(23,20,18,.62); backdrop-filter: blur(8px); }
        .loading-overlay.active { display: grid; }
        .loading-card { width: min(390px,100%); padding: 26px; border-radius: 24px; background: white; text-align: center; box-shadow: var(--shadow); }
        .spinner { width: 48px; height: 48px; margin: 0 auto 16px; border: 5px solid #d9f1ed; border-top-color: var(--primary); border-radius: 50%; animation: spin 850ms linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        @media (max-width: 760px) {
            .page-shell { width: min(100% - 24px,680px); padding: 14px 0 34px; }
            .hero { grid-template-columns: 1fr; padding: 22px; }
            .hero-mark { display: none; }
            h1 { font-size: clamp(2rem,11vw,2.85rem); }
            .card-body { padding: 18px; }
            .form-grid, .photo-grid, .report-grid, .recommendation-grid { grid-template-columns: 1fr; }
            .preview-callout-inner { grid-template-columns: 1fr; padding: 18px; }
            .preview-form-grid { grid-template-columns: 1fr; }
            .generation-item { display: block; }
            .stack-actions, .stack-actions form { width: 100%; }
            .btn, .form-actions .btn { width: 100%; }
            .photo-card img { max-height: 300px; }
            .preview-stage img { max-height: none; }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay" aria-live="polite">
        <div class="loading-card">
            <div class="spinner"></div>
            <h3 id="loadingTitle">Processando...</h3>
            <p id="loadingText">Isso pode levar alguns instantes.</p>
        </div>
    </div>

    <main class="page-shell">
        <section class="hero">
            <div>
                <div class="eyebrow"><span class="eyebrow-dot"></span>Studio Visagismo IA</div>
                <h1>Analise o rosto do cliente e apresente o corte ideal.</h1>
                <p>Envie duas fotos do cliente para receber uma análise personalizada do formato do rosto, cortes recomendados e sugestões de coloração. Depois do relatório, gere uma simulação visual do corte indicado.</p>
            </div>
            <div class="hero-mark" aria-hidden="true"><img src="assets/ai.png" alt=""></div>
        </section>

        <?php if ($error): ?>
            <div class="alert alert-error"><strong>Não foi possível concluir:</strong> <?= h($error) ?></div>
        <?php endif; ?>

        <?php if (!$result): ?>
            <div class="restore-card" id="restoreCard">
                <p><strong>Existe uma análise salva neste aparelho.</strong><br>Você pode reabrir o último relatório sem enviar as fotos do cliente novamente.</p>
                <a class="btn btn-primary" id="restoreLink" href="#">Abrir último relatório</a>
            </div>

            <section class="card">
                <div class="card-body">
                    <div class="card-heading">
                        <span class="step">1</span>
                        <div>
                            <h2>Envie as fotografias do cliente</h2>
                            <p>Use imagens nítidas, sem filtros fortes e com iluminação uniforme. A foto frontal deve mostrar claramente o contorno do rosto.</p>
                        </div>
                    </div>

                    <form action="api/analisar.php" method="POST" enctype="multipart/form-data" class="js-loading-form" data-loading-title="Analisando as fotos do cliente..." data-loading-text="Estamos preparando o relatório personalizado.">
                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                        <input type="hidden" name="MAX_FILE_SIZE" value="<?= MAX_UPLOAD_SIZE ?>">

                        <div class="form-grid">
                            <div class="field">
                                <label for="foto_frontal">Foto frontal</label>
                                <input type="file" name="foto_frontal" id="foto_frontal" accept="image/jpeg,image/png,image/webp,image/bmp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.bmp,.gif,.avif,.heic,.heif" required>
                                <div class="helper">Toque para tirar uma foto ou enviar uma imagem da galeria. Rosto reto para a câmera.</div>
                            </div>

                            <div class="field">
                                <label for="foto_45">Foto em aproximadamente 45°</label>
                                <input type="file" name="foto_45" id="foto_45" accept="image/jpeg,image/png,image/webp,image/bmp,image/gif,image/avif,image/heic,image/heif,.jpg,.jpeg,.png,.webp,.bmp,.gif,.avif,.heic,.heif" required>
                                <div class="helper">Toque para tirar uma foto ou enviar uma imagem da galeria. Vire levemente o rosto para mostrar mandíbula e perfil.</div>
                            </div>

                            <div class="field">
                                <label for="textura">Textura do cabelo</label>
                                <select name="textura" id="textura">
                                    <option value="não informado">Não informado</option>
                                    <option value="liso">Liso</option>
                                    <option value="ondulado">Ondulado</option>
                                    <option value="cacheado">Cacheado</option>
                                    <option value="crespo">Crespo</option>
                                </select>
                            </div>

                            <div class="field">
                                <label for="observacoes">Preferências pessoais</label>
                                <textarea name="observacoes" id="observacoes" maxlength="1000" placeholder="Ex.: prefiro cabelo médio, quero algo moderno, não quero cortar muito curto..."></textarea>
                            </div>
                        </div>

                        <label class="consent">
                            <input type="checkbox" name="consentimento" value="1" required>
                            <span>Confirmo que tenho autorização para usar as fotos do cliente nesta análise. Os arquivos são processados temporariamente e expiram automaticamente.</span>
                        </label>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">Analisar cliente</button>
                        </div>
                    </form>
                </div>
            </section>
        <?php else: ?>
            <section class="card" id="relatorio">
                <div class="card-body">
                    <div class="card-heading">
                        <span class="step">2</span>
                        <div>
                            <h2>Seu relatório personalizado</h2>
                            <p>As recomendações servem como orientação estética. O resultado pode ser ajustado conforme o estilo do cliente e a avaliação de um profissional.</p>
                        </div>
                    </div>

                    <div class="badge-row">
                        <span class="badge">Formato: <?= h(friendly_face_shape((string)($result['formato_rosto'] ?? 'indeterminado'))) ?></span>
                        <span class="badge">Possível variação: <?= h(friendly_face_shape((string)($result['formato_secundario'] ?? 'indeterminado'))) ?></span>
                        <span class="badge">Confiança: <?= h((string)($result['confianca_formato'] ?? '0')) ?>%</span>
                        <span class="badge">Tom aparente: <?= h(friendly_value((string)($result['tom_pele_aparente'] ?? 'indeterminado'))) ?></span>
                        <span class="badge">Subtom aparente: <?= h(friendly_value((string)($result['subtom_aparente'] ?? 'indeterminado'))) ?></span>
                        <span class="badge">Textura: <?= h(friendly_value((string)($result['textura_cabelo_aparente'] ?? 'indeterminado'))) ?></span>
                    </div>

                    <div class="photo-grid">
                        <article class="photo-card"><h3>Foto frontal enviada</h3><img src="<?= h($frontal_url) ?>" alt="Foto frontal enviada"></article>
                        <article class="photo-card"><h3>Foto em 45 graus enviada</h3><img src="<?= h($angle_url) ?>" alt="Foto em 45 graus enviada"></article>
                    </div>

                    <?php if (!empty($result['precisa_novas_fotos'])): ?>
                        <div class="alert alert-error" style="margin-top: 18px; margin-bottom: 0;"><strong>Precisamos de novas fotos.</strong><br><?= h((string)($result['orientacao_nova_foto'] ?? '')) ?></div>
                    <?php else: ?>
                        <div class="report-grid" style="margin-top: 18px;">
                            <article class="report-section" style="margin-top:0;"><h3>Leitura do formato do rosto</h3><p><?= h((string)($result['justificativa_formato'] ?? '')) ?></p></article>
                            <article class="report-section" style="margin-top:0;"><h3>Objetivo visual</h3><p><?= h((string)($result['objetivo_visual'] ?? '')) ?></p></article>
                        </div>

                        <div class="recommendation-grid" style="margin-top: 16px;">
                            <article class="report-section" style="margin-top:0;"><h3>Cortes recomendados</h3><ul><?php foreach (($result['cortes_recomendados'] ?? []) as $item): ?><li><?= h((string)$item) ?></li><?php endforeach; ?></ul></article>
                            <article class="report-section" style="margin-top:0;"><h3>Cortes para evitar ou adaptar</h3><ul><?php foreach (($result['cortes_a_evitar'] ?? []) as $item): ?><li><?= h((string)$item) ?></li><?php endforeach; ?></ul></article>
                            <article class="report-section" style="margin-top:0;"><h3>Cores recomendadas</h3><ul><?php foreach (($result['cores_cabelo_recomendadas'] ?? []) as $item): ?><li><?= h((string)$item) ?></li><?php endforeach; ?></ul></article>
                            <article class="report-section" style="margin-top:0;"><h3>Cores para testar com cautela</h3><ul><?php foreach (($result['cores_a_evitar_ou_testar_com_cautela'] ?? []) as $item): ?><li><?= h((string)$item) ?></li><?php endforeach; ?></ul></article>
                        </div>

                        <div class="report-section"><h3>Observação sobre o subtom aparente</h3><p><?= h((string)($result['observacao_subtom'] ?? '')) ?></p></div>
                        <div class="report-section"><h3>Resumo da recomendação</h3><p><?= h((string)($result['resumo_final'] ?? '')) ?></p></div>

                        <?php if (!$preview_url): ?>
                            <div class="preview-callout">
                                <div class="preview-callout-inner">
                                    <div><h3>Quer visualizar o corte recomendado?</h3><p>O relatório já está salvo. Gere uma primeira versão realista usando a foto frontal do cliente como referência.</p></div>
                                    <form action="api/gerar-preview.php" method="POST" class="js-loading-form" data-loading-title="Gerando a simulação..." data-loading-text="A prévia visual costuma demorar mais que a análise.">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="report_id" value="<?= h((string)$report_id) ?>">
                                        <div class="preview-form">
                                            <div class="preview-form-grid">
                                                <div class="field">
                                                    <label for="corte_escolhido">Corte base</label>
                                                    <select name="corte_escolhido" id="corte_escolhido">
                                                        <?php foreach (($result['cortes_recomendados'] ?? []) as $item): ?>
                                                            <option value="<?= h((string)$item) ?>"><?= h((string)$item) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="field">
                                                    <label for="prompt_preview">Observação para a imagem</label>
                                                    <textarea name="prompt_preview" id="prompt_preview" maxlength="1200" placeholder="Ex.: deixar mais social, manter franja discreta, simular laterais mais baixas..."></textarea>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary btn-lg">Gerar primeira versão</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </section>

            <?php if ($current_image_url): ?>
                <section class="card" id="preview">
                    <div class="card-body">
                        <div class="card-heading"><span class="step">3</span><div><h2><?= $workflow_status === 'completed' ? 'Resultado final do cliente' : 'Prévia visual do corte recomendado' ?></h2><p><?= $workflow_status === 'completed' ? 'Imagem final escolhida para apresentar no atendimento.' : 'Compare as versões, gere outra opção se precisar e aprove a imagem que mais combina com o cliente.' ?></p></div></div>
                        <div class="preview-stage"><img src="<?= h($current_image_url) ?>" alt="Simulação visual do corte recomendado"><div class="preview-caption"><?= $workflow_status === 'completed' ? 'Resultado final salvo neste relatório.' : 'Versão atual gerada a partir da fotografia frontal enviada.' ?></div></div>

                        <?php if (!empty($generations)): ?>
                            <div class="generation-list" aria-label="Versões geradas">
                                <?php foreach (array_reverse($generations) as $generation): ?>
                                    <div class="generation-item">
                                        <span><strong>Versão <?= h((string)($generation['number'] ?? '')) ?></strong> <?= !empty($generation['selected_haircut']) ? ' · ' . h((string)$generation['selected_haircut']) : '' ?></span>
                                        <span><?= h(date('d/m/Y H:i', strtotime((string)($generation['created_at'] ?? 'now')))) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($workflow_status === 'preview_ready'): ?>
                            <div class="choice-panel">
                                <h3>Aprovar ou gerar outra opção</h3>
                                <p>Se esta versão ficou boa, aprove para seguir ao acabamento final. Se quiser testar outra leitura do corte, gere uma nova versão com uma orientação diferente.</p>
                                <div class="stack-actions">
                                    <form action="api/aprovar-preview.php" method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                        <input type="hidden" name="report_id" value="<?= h((string)$report_id) ?>">
                                        <button type="submit" class="btn btn-primary">Gostei desta imagem</button>
                                    </form>
                                </div>
                                <form action="api/gerar-preview.php" method="POST" class="preview-form js-loading-form" data-loading-title="Gerando outra versão..." data-loading-text="A nova prévia vai manter o cliente como referência e variar o corte.">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="report_id" value="<?= h((string)$report_id) ?>">
                                    <div class="preview-form-grid">
                                        <div class="field">
                                            <label for="novo_corte_escolhido">Corte base</label>
                                            <select name="corte_escolhido" id="novo_corte_escolhido">
                                                <?php foreach (($result['cortes_recomendados'] ?? []) as $item): ?>
                                                    <option value="<?= h((string)$item) ?>"><?= h((string)$item) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="field">
                                            <label for="novo_prompt_preview">Orientação para a nova versão</label>
                                            <textarea name="prompt_preview" id="novo_prompt_preview" maxlength="1200" placeholder="Ex.: testar algo mais discreto, deixar mais moderno, manter volume no topo..."></textarea>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-secondary">Gerar outra versão</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <?php if ($workflow_status === 'awaiting_final_refinement'): ?>
                            <div class="choice-panel" id="refinamento-final">
                                <h3>Imagem escolhida</h3>
                                <p>Finalize como está ou peça um último ajuste para preparar a imagem de apresentação.</p>
                                <form action="api/refinar-final.php" method="POST" class="preview-form js-loading-form" data-loading-title="Refinando imagem final..." data-loading-text="Estamos aplicando o ajuste solicitado na versão aprovada.">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="report_id" value="<?= h((string)$report_id) ?>">
                                    <div class="field">
                                        <label for="prompt_refinamento">Ajuste final</label>
                                        <textarea name="prompt_refinamento" id="prompt_refinamento" maxlength="4000" placeholder="Ex.: suavizar o volume da franja, deixar as laterais um pouco mais baixas, manter o acabamento natural..."></textarea>
                                    </div>
                                    <div class="stack-actions">
                                        <button type="submit" class="btn btn-primary">Refinar imagem final</button>
                                    </div>
                                </form>
                                <form action="api/finalizar-sem-refino.php" method="POST" class="stack-actions">
                                    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
                                    <input type="hidden" name="report_id" value="<?= h((string)$report_id) ?>">
                                    <button type="submit" class="btn btn-secondary">Finalizar sem refino</button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="form-actions" id="<?= $workflow_status === 'completed' ? 'resultado-final' : '' ?>">
                            <a class="btn btn-primary" href="<?= h($current_image_url) ?>" download><?= $workflow_status === 'completed' ? 'Baixar imagem final' : 'Baixar imagem atual' ?></a>
                            <a class="btn btn-secondary" href="app.php">Fazer nova análise</a>
                        </div>
                    </div>
                </section>
            <?php else: ?>
                <div class="form-actions"><a class="btn btn-secondary" href="app.php">Fazer nova análise</a></div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script>
        const overlay = document.getElementById('loadingOverlay');
        const loadingTitle = document.getElementById('loadingTitle');
        const loadingText = document.getElementById('loadingText');

        window.addEventListener('pageshow', () => overlay?.classList.remove('active'));

        document.querySelectorAll('.js-loading-form').forEach((form) => {
            form.addEventListener('submit', () => {
                loadingTitle.textContent = form.dataset.loadingTitle || 'Processando...';
                loadingText.textContent = form.dataset.loadingText || 'Isso pode levar alguns instantes.';
                overlay.classList.add('active');
            });
        });

        const currentReportUrl = <?= $report_id ? json_encode('app.php?report_id=' . $report_id, JSON_UNESCAPED_SLASHES) : 'null' ?>;
        try {
            if (currentReportUrl) {
                localStorage.setItem('studio-visagismo:last-report-url', currentReportUrl);
            } else {
                const savedUrl = localStorage.getItem('studio-visagismo:last-report-url');
                const restoreCard = document.getElementById('restoreCard');
                const restoreLink = document.getElementById('restoreLink');
                if (savedUrl && restoreCard && restoreLink) {
                    restoreLink.href = savedUrl;
                    restoreCard.classList.add('visible');
                }
            }
        } catch (e) {
            console.warn('localStorage indisponível:', e);
        }

        const preview = document.getElementById('preview');
        const relatorio = document.getElementById('relatorio');
        if (preview) setTimeout(() => preview.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
        else if (relatorio) setTimeout(() => relatorio.scrollIntoView({ behavior: 'smooth', block: 'start' }), 150);
    </script>
</body>
</html>
<?php finish_request_debug(http_response_code() ?: 200); ?>
