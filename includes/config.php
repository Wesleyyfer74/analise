<?php
/**
 * Configuracao central e utilitarios de seguranca.
 */

define('BASE_DIR', dirname(__DIR__));

function load_env_file($path) {
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) || getenv($name) !== false) {
            continue;
        }

        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}

function env_value($name, $default = null) {
    $value = getenv($name);
    return $value === false || $value === '' ? $default : $value;
}

function env_int($name, $default, $min, $max) {
    $value = filter_var(env_value($name, $default), FILTER_VALIDATE_INT);
    if ($value === false) {
        return $default;
    }
    return max($min, min($max, $value));
}

load_env_file(BASE_DIR . '/.env');

function storage_path($env_name, $default_relative) {
    $value = env_value($env_name);
    if ($value) {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $value) || strpos($value, '/') === 0) {
            return rtrim($value, '/\\');
        }
        return BASE_DIR . '/' . trim($value, '/\\');
    }

    $volume = env_value('RAILWAY_VOLUME_MOUNT_PATH');
    if ($volume) {
        $candidate = rtrim($volume, '/\\') . '/' . trim($default_relative, '/\\');
        if ((is_dir($candidate) || @mkdir($candidate, 0755, true)) && is_writable($candidate)) {
            return $candidate;
        }
    }

    return BASE_DIR . '/' . trim($default_relative, '/\\');
}

define('DATA_DIR', storage_path('DATA_DIR', 'data/reports'));
define('CACHE_DIR', storage_path('CACHE_DIR', 'data/cache'));
define('LOG_DIR', storage_path('LOG_DIR', 'data/logs'));
define('ANALYSIS_MODEL', env_value('ANALYSIS_MODEL', 'gpt-5-mini'));
define('IMAGE_MODEL', env_value('IMAGE_MODEL', 'gpt-image-2'));
define('MAX_IMAGE_SIDE', env_int('MAX_IMAGE_SIDE', 1600, 512, 4096));
define('JPEG_QUALITY', env_int('JPEG_QUALITY', 90, 60, 100));
define('MAX_UPLOAD_SIZE', env_int('MAX_UPLOAD_SIZE', 30 * 1024 * 1024, 1024 * 1024, 50 * 1024 * 1024));
define('REPORT_TTL', env_int('REPORT_TTL', 86400, 3600, 604800));
define('SESSION_TIMEOUT', env_int('SESSION_TIMEOUT', 3600, 900, 86400));
define('IMAGE_QUALITY', env_value('IMAGE_QUALITY', 'medium'));
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp', 'bmp', 'gif', 'avif', 'heic', 'heif']);

foreach ([DATA_DIR, CACHE_DIR, LOG_DIR] as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Nao foi possivel criar o diretorio: $dir");
    }
}

function start_secure_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['last_activity']) && $now - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_unset();
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = $now;
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new Exception('Sua sessao expirou. Atualize a pagina e tente novamente.');
    }
}

function log_step($message, $extra = []) {
    if (env_value('LOG_LEVEL', 'ERROR') === 'DEBUG') {
        error_log('[' . date('c') . "] $message " . json_encode($extra, JSON_UNESCAPED_UNICODE));
    }
}

function request_id() {
    static $id = null;
    if ($id === null) {
        $id = substr(bin2hex(random_bytes(8)), 0, 8);
    }
    return $id;
}

function debug_log($message, $context = [], $level = 'DEBUG') {
    if (env_value('VISAGISMO_LOG_FILE', '0') !== '1') {
        return;
    }

    $safe_context = [];
    foreach ($context as $key => $value) {
        if (preg_match('/key|secret|token|authorization/i', (string)$key)) {
            $safe_context[$key] = '[redacted]';
        } elseif (preg_match('/image|photo|foto|base64|payload/i', (string)$key)) {
            $safe_context[$key] = is_string($value) ? '[image-data]' : $value;
        } else {
            $safe_context[$key] = $value;
        }
    }

    $line = sprintf(
        "%s | %-7s | [%s] %s",
        date('c'),
        strtoupper((string)$level),
        request_id(),
        (string)$message
    );
    if ($safe_context) {
        $line .= ' | ' . json_encode(
            $safe_context,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
    }
    @file_put_contents(LOG_DIR . '/visagismo-debug.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function start_request_debug() {
    $GLOBALS['visagismo_request_started_at'] = microtime(true);
    debug_log('request.start', [
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? null,
    ], 'INFO');
}

function finish_request_debug($status_code = 200) {
    $started = $GLOBALS['visagismo_request_started_at'] ?? microtime(true);
    debug_log('request.finish', [
        'status' => (int)$status_code,
        'duration_ms' => round((microtime(true) - $started) * 1000, 1),
    ], 'INFO');
}

function create_new_report_id() {
    return bin2hex(random_bytes(16));
}

function validate_report_id($report_id) {
    if (!is_string($report_id) || !preg_match('/^[a-f0-9]{32}$/', $report_id)) {
        throw new Exception('Identificador do relatorio invalido.');
    }
    return $report_id;
}

function report_data_folder($report_id) {
    return DATA_DIR . '/' . validate_report_id($report_id);
}

function report_static_folder($report_id) {
    return report_data_folder($report_id) . '/images';
}

function report_json_path($report_id) {
    return report_data_folder($report_id) . '/report.json';
}

function extension_from_filename($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function allowed_file($filename) {
    return in_array(extension_from_filename($filename), ALLOWED_EXTENSIONS, true);
}

function friendly_face_shape($value) {
    $names = [
        'redondo' => 'Redondo',
        'quadrado' => 'Quadrado',
        'oval' => 'Oval',
        'coracao_triangulo_invertido' => 'Coracao / triangulo invertido',
        'retangular' => 'Retangular',
        'diamante' => 'Diamante',
        'triangular' => 'Triangular',
        'indeterminado' => 'Indeterminado',
    ];
    return $names[$value] ?? $value;
}

function friendly_value($value) {
    return ucfirst(str_replace('_', ' ', (string)$value));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function write_json_atomic($path, $content) {
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new Exception('Nao foi possivel criar a pasta do relatorio.');
    }

    $json = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    $temp_path = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($temp_path, $json, LOCK_EX) === false || !rename($temp_path, $path)) {
        @unlink($temp_path);
        throw new Exception('Nao foi possivel salvar o relatorio.');
    }
}

function save_report($report_id, $report) {
    write_json_atomic(report_json_path($report_id), $report);
}

function load_report($report_id) {
    $path = report_json_path($report_id);
    if (!is_file($path)) {
        throw new Exception('O relatorio nao foi encontrado. Faca uma nova analise.');
    }

    $report = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($report)) {
        throw new Exception('O relatorio salvo esta invalido.');
    }
    $created_at = strtotime($report['created_at'] ?? '') ?: filemtime($path);
    if ($created_at < time() - REPORT_TTL) {
        remove_directory(report_data_folder($report_id));
        throw new Exception('Este relatorio expirou. Faca uma nova analise.');
    }
    return $report;
}

function authorize_report($report_id) {
    validate_report_id($report_id);
    if (!in_array($report_id, $_SESSION['report_ids'] ?? [], true)) {
        throw new Exception('Este relatorio nao pertence a sua sessao.');
    }
}

function register_report_for_session($report_id) {
    $_SESSION['report_ids'] = array_values(array_unique(array_merge(
        $_SESSION['report_ids'] ?? [],
        [$report_id]
    )));
    $_SESSION['report_id'] = $report_id;
}

function relative_static_url($report_id, $filename) {
    return 'api/imagem.php?report_id=' . rawurlencode($report_id)
        . '&file=' . rawurlencode(basename($filename));
}

function find_image_path($folder, $stem) {
    $files = glob($folder . '/' . $stem . '.*');
    if (empty($files)) {
        throw new Exception('A fotografia salva nao foi encontrada.');
    }
    return $files[0];
}

function remove_directory($directory) {
    if (!is_dir($directory)) {
        return;
    }
    foreach (glob($directory . '/*') ?: [] as $item) {
        is_dir($item) ? remove_directory($item) : @unlink($item);
    }
    @rmdir($directory);
}

function cleanup_expired_reports() {
    $marker = DATA_DIR . '/.last_cleanup';
    if (is_file($marker) && (filemtime($marker) ?: 0) > time() - 3600) {
        return;
    }
    @touch($marker);
    $cutoff = time() - REPORT_TTL;
    foreach (glob(DATA_DIR . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
        if ((filemtime($directory) ?: time()) < $cutoff) {
            remove_directory($directory);
        }
    }
}

function enforce_rate_limit($action, $limit, $window_seconds) {
    $now = time();
    $events = array_values(array_filter(
        $_SESSION['rate_limits'][$action] ?? [],
        static fn($timestamp) => $timestamp > $now - $window_seconds
    ));
    if (count($events) >= $limit) {
        throw new Exception('Muitas tentativas em pouco tempo. Aguarde alguns minutos.');
    }
    $events[] = $now;
    $_SESSION['rate_limits'][$action] = $events;
}

function get_api_key() {
    $key = env_value('OPENAI_API_KEY');
    if (!$key || !preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key)) {
        throw new Exception('OPENAI_API_KEY nao configurada corretamente no arquivo .env.');
    }
    return $key;
}

function client_reference() {
    return hash('sha256', session_id());
}
