<?php
declare(strict_types=1);

// superadmin/systec_creator/_config/config.php
// Config propia del SysTec Creator (NO CORE)

/**
 * Bloqueo si alguien intenta abrir este archivo directo en navegador.
 */
if (PHP_SAPI !== 'cli') {
    $self = realpath(__FILE__);
    $req  = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($self && $req && $self === $req) {
        http_response_code(403);
        exit('Forbidden');
    }
}

/**
 * Helpers ENV (para no hardcodear secretos si no quieres)
 */
if (!function_exists('sa_env')) {
    function sa_env(string $key, string $default = ''): string
    {
        $v = getenv($key);
        if ($v === false || $v === null) return $default;
        $v = (string)$v;
        return $v === '' ? $default : $v;
    }
}

/**
 * Debug controlado por ENV (SA_DEBUG=1)
 * Cuando esté estable => SA_DEBUG=0
 */
$SA_DEBUG = sa_env('SA_DEBUG', '1') === '1';

if ($SA_DEBUG) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

/**
 * Sesión propia del superadmin (separada del SysTec cliente)
 */
if (session_status() !== PHP_SESSION_ACTIVE) {

    // Cookie settings más seguros
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
    $params = [
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ];

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($params);
    } else {
        // Compat viejo
        session_set_cookie_params(
            $params['lifetime'],
            $params['path'] . '; samesite=' . $params['samesite'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_name('SYS_SUPERADMIN_SYSTEC_CREATOR');
    session_start();
}

/**
 * Base URL del módulo (autodetect)
 * Ej: https://c2k.cl/sysproc2k/superadmin/systec_creator
 */
$https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
$scheme = $https ? 'https://' : 'http://';
$host   = (string)($_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost'));
$dir    = str_replace('\\', '/', rtrim(dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

define('SA_BASE_URL', $scheme . $host . $dir);

/**
 * Helper de URLs del módulo
 */
if (!function_exists('sa_url')) {
    function sa_url(string $path = ''): string
    {
        $base = rtrim((string)SA_BASE_URL, '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

/**
 * Rutas físicas útiles para provisioning
 * config.php está en: /sysproc2k/superadmin/systec_creator/_config/
 * subir 3 => /sysproc2k
 */
define('SYS_ROOT', realpath(__DIR__ . '/../../../') ?: ''); // -> /sysproc2k
define('SYSTEC_ROOT', SYS_ROOT ? (SYS_ROOT . '/systec') : '');
define('SYSTEC_CLIENTS_ROOT', SYSTEC_ROOT ? (SYSTEC_ROOT . '/_clients') : '');
define('SYSTEC_CORES_ROOT', SYSTEC_ROOT ? (SYSTEC_ROOT . '/_cores') : '');

/**
 * ✅ BD CENTRAL DEL CREATOR (Auth + Registry)
 * Ideal: usar ENV en producción para no dejar pass hardcodeado.
 */
define('MASTER_DB_HOST', sa_env('MASTER_DB_HOST', 'localhost'));
define('MASTER_DB_NAME', sa_env('MASTER_DB_NAME', 'ckcl_superadmin'));
define('MASTER_DB_USER', sa_env('MASTER_DB_USER', 'ckcl_superadmin'));
define('MASTER_DB_PASS', sa_env('MASTER_DB_PASS', '112233Kdoki.'));

/**
 * Token para habilitar seed_superamin.php (SIEMPRE usar ENV)
 * export SA_SEED_TOKEN="algo-largo"
 */
define('SA_SEED_TOKEN', sa_env('SA_SEED_TOKEN', ''));

/**
 * PDO master (auth + registry)
 */
if (!function_exists('sa_pdo')) {
    function sa_pdo(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = 'mysql:host=' . MASTER_DB_HOST . ';dbname=' . MASTER_DB_NAME . ';charset=utf8mb4';

        $pdo = new PDO($dsn, MASTER_DB_USER, MASTER_DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);

        return $pdo;
    }
}

/**
 * ✅ Flash messages (session)
 */
if (!function_exists('sa_flash_set')) {
    function sa_flash_set(string $key, string $msg, string $type = 'success'): void
    {
        $_SESSION['_sa_flash'][$key] = ['msg' => $msg, 'type' => $type];
    }
}

if (!function_exists('sa_flash_get')) {
    function sa_flash_get(string $key): ?array
    {
        if (!isset($_SESSION['_sa_flash'][$key])) return null;
        $data = $_SESSION['_sa_flash'][$key];
        unset($_SESSION['_sa_flash'][$key]);
        return $data;
    }
}

/**
 * ✅ Utils
 */
if (!function_exists('sa_post')) {
    function sa_post(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }
}
