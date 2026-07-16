<?php
/**
 * POST /api/finalizar-sem-refino.php
 */

ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';

start_secure_session();
start_request_debug();

$report_id = null;
$lock_handle = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Metodo nao permitido.');
    }

    validate_csrf_token($_POST['csrf_token'] ?? null);
    require_active_client();

    $report_id = $_POST['report_id'] ?? null;
    authorize_report($report_id);

    $lock_path = report_data_folder($report_id) . '/workflow.lock';
    $lock_handle = fopen($lock_path, 'c');
    if (!$lock_handle || !flock($lock_handle, LOCK_EX | LOCK_NB)) {
        throw new Exception('Este relatorio esta sendo atualizado. Tente novamente em instantes.');
    }

    $report = load_report($report_id);
    if (($report['workflow_status'] ?? '') === 'completed') {
        throw new Exception('Este visagismo ja foi concluido.');
    }
    if (($report['workflow_status'] ?? '') !== 'awaiting_final_refinement') {
        throw new Exception('Escolha uma previa antes de finalizar.');
    }

    $selected_url = $report['selected_image_url'] ?? $report['approved_image_url'] ?? null;
    if (!$selected_url) {
        throw new Exception('A imagem escolhida nao foi encontrada no relatorio.');
    }
    image_url_to_path($report_id, $selected_url);

    $report['final_image_url'] = $selected_url;
    $report['current_image_url'] = $selected_url;
    $report['preview_url'] = $selected_url;
    $report['workflow_status'] = 'completed';
    $report['final_refinement'] = [
        'skipped' => true,
        'created_at' => date('c'),
        'source_url' => $selected_url,
        'url' => $selected_url,
    ];
    $report['completed_at'] = date('c');
    save_report($report_id, $report);

    debug_log('final_without_refine.completed', ['report_id' => $report_id], 'INFO');
    finish_request_debug(303);
    header('Location: ../app.php?report_id=' . rawurlencode($report_id) . '#resultado-final', true, 303);
    exit;
} catch (Throwable $e) {
    debug_log('final_without_refine.failed', ['message' => $e->getMessage()], 'ERROR');
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
