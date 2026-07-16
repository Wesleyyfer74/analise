<?php
/**
 * POST /api/aprovar-preview.php
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

    $selected_url = $report['current_image_url'] ?? $report['preview_url'] ?? null;
    if (!$selected_url) {
        throw new Exception('Gere uma previa antes de escolher a imagem final.');
    }
    image_url_to_path($report_id, $selected_url);

    $report['selected_image_url'] = $selected_url;
    $report['approved_image_url'] = $selected_url;
    $report['workflow_status'] = 'awaiting_final_refinement';
    $report['selected_at'] = date('c');
    save_report($report_id, $report);

    debug_log('preview.approved', ['report_id' => $report_id], 'INFO');
    finish_request_debug(303);
    header('Location: ../app.php?report_id=' . rawurlencode($report_id) . '#refinamento-final', true, 303);
    exit;
} catch (Throwable $e) {
    debug_log('preview.approve_failed', ['message' => $e->getMessage()], 'ERROR');
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
