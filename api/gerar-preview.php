<?php
/**
 * POST /api/gerar-preview.php
 */

ini_set('display_errors', '0');
set_time_limit(360);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/openai.php';

start_secure_session();
start_request_debug();

$report_id = null;
$lock_handle = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Metodo nao permitido.');
    }

    validate_csrf_token($_POST['csrf_token'] ?? null);
    enforce_rate_limit('preview', 5, 3600);
    require_active_client();

    $report_id = $_POST['report_id'] ?? null;
    authorize_report($report_id);

    $lock_path = report_data_folder($report_id) . '/preview.lock';
    $lock_handle = fopen($lock_path, 'c');
    if (!$lock_handle || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
        throw new Exception('A previa deste relatorio ja esta sendo gerada.');
    }

    $report = load_report($report_id);
    $status = $report['workflow_status'] ?? 'analysis_ready';
    if ($status === 'awaiting_final_refinement') {
        throw new Exception('A versao final ja foi escolhida. Refine ou finalize a imagem selecionada.');
    }
    if ($status === 'completed') {
        throw new Exception('Este visagismo ja foi concluido. Faca uma nova analise para gerar outra imagem.');
    }

    $analysis = $report['analysis'] ?? [];
    if ($analysis['precisa_novas_fotos'] ?? true) {
        throw new Exception('Envie novas fotografias antes de gerar uma previa.');
    }

    $selected_haircut = mb_substr(trim((string)($_POST['corte_escolhido'] ?? '')), 0, 220);
    $additional_prompt = mb_substr(trim((string)($_POST['prompt_preview'] ?? '')), 0, 1200);

    $image_folder = report_static_folder($report_id);
    $frontal_path = find_image_path($image_folder, 'frontal');
    [$generation_number, $filename] = next_generation_filename($report);
    $preview_path = $image_folder . '/' . $filename;

    $client = new OpenAIClient();
    debug_log('preview.openai_start', [
        'model' => IMAGE_MODEL,
        'report_id' => $report_id,
        'generation' => $generation_number,
    ], 'INFO');
    $prompt_used = $client->generate_haircut_preview(
        $frontal_path,
        $analysis,
        $preview_path,
        $selected_haircut,
        $additional_prompt,
        $generation_number
    );

    $preview_url = relative_static_url($report_id, $filename);
    $report['generations'][] = [
        'id' => bin2hex(random_bytes(8)),
        'number' => $generation_number,
        'created_at' => date('c'),
        'url' => $preview_url,
        'filename' => $filename,
        'selected_haircut' => $selected_haircut ?: ($analysis['cortes_recomendados'][0] ?? ''),
        'additional_prompt' => $additional_prompt,
        'model' => IMAGE_MODEL,
        'prompt_used' => $prompt_used,
    ];
    $report['preview_url'] = $preview_url;
    $report['current_image_url'] = $preview_url;
    $report['selected_image_url'] = null;
    $report['approved_image_url'] = null;
    $report['final_image_url'] = null;
    $report['final_refinement'] = null;
    $report['final_refinement_prompt'] = '';
    $report['completed_at'] = null;
    $report['workflow_status'] = 'preview_ready';
    $report['preview_created_at'] = date('c');
    save_report($report_id, $report);

    debug_log('preview.completed', ['report_id' => $report_id], 'INFO');
    finish_request_debug(303);
    header('Location: ../app.php?report_id=' . rawurlencode($report_id) . '#preview', true, 303);
    exit;
} catch (Throwable $e) {
    log_step('ERRO PREVIEW', ['error' => $e->getMessage()]);
    debug_log('preview.failed', ['message' => $e->getMessage()], 'ERROR');
    $_SESSION['error'] = $e->getMessage();
    $location = '../app.php';
    if (is_string($report_id) && preg_match('/^[a-f0-9]{32}$/', $report_id)) {
        $location .= '?report_id=' . rawurlencode($report_id);
    }
    finish_request_debug(303);
    header('Location: ' . $location, true, 303);
    exit;
} finally {
    if (is_resource($lock_handle)) {
        flock($lock_handle, LOCK_UN);
        fclose($lock_handle);
    }
}
