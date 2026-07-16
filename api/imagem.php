<?php
/**
 * Entrega privada das imagens pertencentes a sessao atual.
 */

ini_set('display_errors', '0');

require_once dirname(__DIR__) . '/includes/config.php';
start_secure_session();

try {
    $report_id = $_GET['report_id'] ?? null;
    $filename = basename((string)($_GET['file'] ?? ''));

    authorize_report($report_id);
    $is_allowed = in_array($filename, ['frontal.jpg', 'angulo.jpg', 'preview.jpg'], true)
        || preg_match('/^generation_[0-9]{3}_[a-f0-9]{8}\.jpg$/', $filename)
        || preg_match('/^final_refinement_[a-f0-9]{8}\.jpg$/', $filename);

    if (!$is_allowed) {
        throw new Exception('Imagem invalida.');
    }

    $path = report_static_folder($report_id) . '/' . $filename;
    if (!is_file($path) || !is_readable($path)) {
        throw new Exception('Imagem nao encontrada.');
    }

    header('Content-Type: image/jpeg');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Cache-Control: private, no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
} catch (Throwable $e) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Imagem nao encontrada.';
}
