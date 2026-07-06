<?php
/**
 * Diagnostico temporario de deploy.
 * Excluir este arquivo apos a validacao.
 */

ini_set('display_errors', '0');
require_once __DIR__ . '/includes/config.php';

$expected_token = env_value('DEPLOY_CHECK_TOKEN');
$provided_token = $_GET['token'] ?? '';
if (!$expected_token || !is_string($provided_token) || !hash_equals($expected_token, $provided_token)) {
    http_response_code(404);
    exit('Not Found');
}

$checks = [];
function add_check(&$checks, $name, $ok, $detail) {
    $checks[] = ['name' => $name, 'ok' => (bool)$ok, 'detail' => $detail];
}

add_check($checks, 'PHP', version_compare(PHP_VERSION, '8.1', '>='), PHP_VERSION);
foreach (['curl', 'gd', 'json', 'fileinfo', 'mbstring', 'openssl'] as $extension) {
    add_check(
        $checks,
        "Extensao $extension",
        extension_loaded($extension),
        extension_loaded($extension) ? 'disponivel' : 'ausente'
    );
}

$key_ok = false;
try {
    $key_ok = strlen(get_api_key()) > 20;
} catch (Throwable $e) {
    $key_ok = false;
}
add_check($checks, 'OPENAI_API_KEY', $key_ok, $key_ok ? 'carregada' : 'ausente ou invalida');
add_check($checks, 'Modelo de analise', ANALYSIS_MODEL !== '', ANALYSIS_MODEL);
add_check($checks, 'Modelo de imagem', IMAGE_MODEL !== '', IMAGE_MODEL);

foreach ([DATA_DIR, CACHE_DIR] as $directory) {
    $exists = is_dir($directory);
    $writable = $exists && is_writable($directory);
    add_check(
        $checks,
        basename($directory),
        $writable,
        $exists ? ($writable ? 'gravacao OK' : 'sem permissao de escrita') : 'nao existe'
    );
}

$required_files = [
    'index.php',
    'api/analisar.php',
    'api/gerar-preview.php',
    'api/imagem.php',
    'includes/config.php',
    'includes/image.php',
    'includes/openai.php',
    '.htaccess',
    '.user.ini',
    'data/.htaccess',
    'logs/.htaccess',
];
foreach ($required_files as $file) {
    add_check($checks, $file, is_file(BASE_DIR . '/' . $file), is_file(BASE_DIR . '/' . $file) ? 'OK' : 'ausente');
}

$upload_limit = ini_get('upload_max_filesize');
$post_limit = ini_get('post_max_size');
add_check($checks, 'upload_max_filesize', true, $upload_limit);
add_check($checks, 'post_max_size', true, $post_limit);

$gd_info = function_exists('gd_info') ? gd_info() : [];
$formats = [
    'JPEG' => !empty($gd_info['JPEG Support']),
    'PNG' => !empty($gd_info['PNG Support']),
    'WebP' => !empty($gd_info['WebP Support']),
    'AVIF' => !empty($gd_info['AVIF Support']),
    'HEIC via Imagick' => class_exists('Imagick'),
];
foreach ($formats as $format => $available) {
    $required = $format !== 'HEIC via Imagick';
    add_check(
        $checks,
        $format,
        $required ? $available : true,
        $available ? 'suportado' : ($required ? 'nao suportado' : 'opcional; solicitar ativacao para HEIC')
    );
}

$all_ok = !in_array(false, array_column($checks, 'ok'), true);
header('Content-Type: text/html; charset=UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Verificacao de producao</title>
    <style>
        body{font-family:system-ui,sans-serif;max-width:850px;margin:30px auto;padding:0 16px;color:#172033}
        table{width:100%;border-collapse:collapse}th,td{padding:10px;border-bottom:1px solid #ddd;text-align:left}
        .ok{color:#067647}.erro{color:#b42318}.box{padding:16px;border-radius:12px;background:#f5f7fb}
    </style>
</head>
<body>
    <h1>Verificacao de producao</h1>
    <p class="box <?= $all_ok ? 'ok' : 'erro' ?>">
        <strong><?= $all_ok ? 'Ambiente aprovado.' : 'Existem itens obrigatorios para corrigir.' ?></strong>
    </p>
    <table>
        <thead><tr><th>Item</th><th>Status</th><th>Detalhe</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td><?= htmlspecialchars($check['name']) ?></td>
                <td class="<?= $check['ok'] ? 'ok' : 'erro' ?>"><?= $check['ok'] ? 'OK' : 'ERRO' ?></td>
                <td><?= htmlspecialchars($check['detail']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p><strong>Depois da verificacao, exclua este arquivo do servidor.</strong></p>
</body>
</html>
