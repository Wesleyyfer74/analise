<?php
/**
 * POST /api/analisar.php
 */

ini_set('display_errors', '0');
set_time_limit(240);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/image.php';
require_once dirname(__DIR__) . '/includes/openai.php';

start_secure_session();
start_request_debug();

$report_id = null;
$report_folder = null;

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new Exception('Metodo nao permitido.');
    }

    validate_csrf_token($_POST['csrf_token'] ?? null);
    enforce_rate_limit('analysis', 5, 3600);
    [$current_user, $current_profile] = require_active_client();
    cleanup_expired_reports();

    $content_length = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($content_length > (MAX_UPLOAD_SIZE * 2) + (1024 * 1024)) {
        throw new Exception('O envio total excede o limite permitido.');
    }

    if (empty($_POST['consentimento'])) {
        throw new Exception('Confirme o consentimento para processar as fotografias.');
    }

    $textures = ['nao informado', 'liso', 'ondulado', 'cacheado', 'crespo'];
    $informed_texture = trim((string)($_POST['textura'] ?? 'nao informado'));
    if (!in_array($informed_texture, $textures, true)) {
        $informed_texture = 'nao informado';
    }
    $additional_text = mb_substr(trim((string)($_POST['observacoes'] ?? '')), 0, 1000);

    $frontal_file = $_FILES['foto_frontal'] ?? null;
    $angle_file = $_FILES['foto_45'] ?? null;
    ImageProcessor::validate_upload($frontal_file, 'a foto frontal');
    ImageProcessor::validate_upload($angle_file, 'a foto em 45 graus');

    $report_id = create_new_report_id();
    $report_folder = report_data_folder($report_id);
    $image_folder = report_static_folder($report_id);

    $frontal_path = ImageProcessor::save_uploaded_image(
        $frontal_file,
        $image_folder,
        'frontal',
        'a foto frontal'
    );
    $angle_path = ImageProcessor::save_uploaded_image(
        $angle_file,
        $image_folder,
        'angulo',
        'a foto em 45 graus'
    );

    $client = new OpenAIClient();
    debug_log('analysis.openai_start', [
        'model' => ANALYSIS_MODEL,
        'frontal_bytes' => filesize($frontal_path),
        'angle_bytes' => filesize($angle_path),
    ], 'INFO');
    $analysis = $client->analyze_images(
        $frontal_path,
        $angle_path,
        $informed_texture,
        $additional_text
    );

    $report = [
        'report_id' => $report_id,
        'created_at' => date('c'),
        'expires_at' => date('c', time() + REPORT_TTL),
        'analysis' => $analysis,
        'frontal_url' => relative_static_url($report_id, basename($frontal_path)),
        'angle_url' => relative_static_url($report_id, basename($angle_path)),
        'preview_url' => null,
    ];

    save_report($report_id, $report);
    register_report_for_session($report_id);
    consume_token($current_user['id'], $report_id);
    debug_log('analysis.completed', ['report_id' => $report_id], 'INFO');
    finish_request_debug(303);
    header('Location: ../app.php?report_id=' . rawurlencode($report_id), true, 303);
    exit;
} catch (Throwable $e) {
    if ($report_folder && is_dir($report_folder)) {
        remove_directory($report_folder);
    }
    log_step('ERRO ANALISAR', ['error' => $e->getMessage()]);
    debug_log('analysis.failed', ['message' => $e->getMessage()], 'ERROR');
    $_SESSION['error'] = $e->getMessage();
    finish_request_debug(303);
    header('Location: ../app.php', true, 303);
    exit;
}
