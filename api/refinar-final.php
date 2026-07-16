<?php
/**
 * POST /api/refinar-final.php
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
    enforce_rate_limit('final-refine', 3, 3600);
    require_active_client();

    $report_id = $_POST['report_id'] ?? null;
    authorize_report($report_id);

    $lock_path = report_data_folder($report_id) . '/workflow.lock';
    $lock_handle = fopen($lock_path, 'c');
    if (!$lock_handle || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
        throw new Exception('A imagem final ja esta sendo processada.');
    }

    $report = load_report($report_id);
    if (($report['workflow_status'] ?? '') === 'completed') {
        throw new Exception('Este visagismo ja foi concluido.');
    }
    if (($report['workflow_status'] ?? '') !== 'awaiting_final_refinement') {
        throw new Exception('Escolha uma previa antes de refinar a imagem final.');
    }

    $selected_url = $report['selected_image_url'] ?? $report['approved_image_url'] ?? null;
    if (!$selected_url) {
        throw new Exception('A imagem escolhida nao foi encontrada no relatorio.');
    }
    $selected_path = image_url_to_path($report_id, $selected_url);

    $user_request = mb_substr(trim((string)($_POST['prompt_refinamento'] ?? '')), 0, 4000);
    if ($user_request === '') {
        throw new Exception('Descreva o ajuste desejado antes de refinar a imagem final.');
    }

    $filename = final_refinement_filename();
    $output_path = report_static_folder($report_id) . '/' . $filename;

    $client = new OpenAIClient();
    debug_log('final_refine.openai_start', [
        'model' => IMAGE_MODEL,
        'report_id' => $report_id,
    ], 'INFO');
    $prompt_used = $client->refine_final_image($selected_path, $user_request, $output_path);

    $final_url = relative_static_url($report_id, $filename);
    $report['final_image_url'] = $final_url;
    $report['current_image_url'] = $final_url;
    $report['preview_url'] = $final_url;
    $report['workflow_status'] = 'completed';
    $report['final_refinement_prompt'] = $user_request;
    $report['final_refinement'] = [
        'id' => bin2hex(random_bytes(8)),
        'created_at' => date('c'),
        'source_url' => $selected_url,
        'url' => $final_url,
        'filename' => $filename,
        'user_request' => $user_request,
        'model' => IMAGE_MODEL,
        'prompt_used' => $prompt_used,
    ];
    $report['completed_at'] = date('c');
    save_report($report_id, $report);

    debug_log('final_refine.completed', ['report_id' => $report_id], 'INFO');
    finish_request_debug(303);
    header('Location: ../app.php?report_id=' . rawurlencode($report_id) . '#resultado-final', true, 303);
    exit;
} catch (Throwable $e) {
    debug_log('final_refine.failed', ['message' => $e->getMessage()], 'ERROR');
    $_SESSION['error'] = $e->getMessage();
    $location = '../app.php';
    if (is_string($report_id) && preg_match('/^[a-f0-9]{32}$/', $report_id)) {
        $location .= '?report_id=' . rawurlencode($report_id) . '#refinamento-final';
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
