<?php
// config/config.php
// SysTec Multi-Cliente (CORE)
// - Requiere sesión
// - Lee instance.php desde puente (SYSTEC_INSTANCE_PATH) o fallback
// - Respeta SYSTEC_APP_URL y SYSTEC_CORE_URL
// - Logging por ENV y LOG_PATH
// - NO setea Content-Type acá (para no romper endpoints que sirven imagen/PDF)

mb_internal_encoding('UTF-8');

// ✅ Fallback de log temprano (por si session_config.php revienta)
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('display_errors', '0'); // seguro por defecto
ini_set('error_log', sys_get_temp_dir() . '/systec_boot.log');

require_once __DIR__ . '/session_config.php';

// ✅ v1.2: sesión consistente y aislada por instancia
if (function_exists('systec_session_boot')) {
    systec_session_boot();
}

// ✅ Ruta física al root del CORE
if (!defined('APP_BASE')) {
    define('APP_BASE', realpath(__DIR__ . '/..'));
}

/**
 * ✅ Cargar instance.php:
 * - Si viene desde puente: SYSTEC_INSTANCE_PATH
 * - Si no: fallback local o guess (single-cliente)
 */
if (defined('SYSTEC_INSTANCE_PATH')) {
    $INSTANCE_PATH = SYSTEC_INSTANCE_PATH;
} else {
    // ✅ v1.2: sin hardcode a cliente1
    // Si el puente no define SYSTEC_INSTANCE_PATH, caemos al instance.php local del core (modo dev)
    $INSTANCE_PATH = (__DIR__ . '/instance.php');
}

$INSTANCE = [];

if (is_file($INSTANCE_PATH) && is_readable($INSTANCE_PATH)) {
    $tmp = require $INSTANCE_PATH;
    if (is_array($tmp)) $INSTANCE = $tmp;
    unset($tmp);
}

// ✅ Entorno por instancia (prod/dev) + logging
$ENV = strtolower(trim((string)($INSTANCE['ENV'] ?? 'prod')));

// Reporte completo siempre, pero el display depende del ENV
error_reporting(E_ALL);
ini_set('log_errors', '1');

if ($ENV === 'dev') {
    ini_set('display_errors', '1');
} else {
    ini_set('display_errors', '0');
}

// Log a storage del cliente si existe, si no a /tmp
$logDir = $INSTANCE['LOG_PATH'] ?? '';
if ($logDir !== '') {
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    $logFile = rtrim($logDir, '/\\') . '/php_errors.log';
    ini_set('error_log', $logFile);
} else {
    ini_set('error_log', sys_get_temp_dir() . '/systec_php_errors.log');
}

/**
 * ✅ APP_URL = BASE de la INSTANCIA (donde vive /public)
 * Prioridad:
 * 1) Puente: SYSTEC_APP_URL
 * 2) instance.php: APP_URL
 * 3) autodetect (solo si entras al CORE directo)
 */
if (!defined('APP_URL')) {

    if (defined('SYSTEC_APP_URL')) {
        $rel = rtrim((string)SYSTEC_APP_URL, '/');
        $rel = ($rel === '/') ? '' : $rel;
        define('APP_URL', $rel);

    } elseif (!empty($INSTANCE['APP_URL'])) {
        $rel = rtrim((string)$INSTANCE['APP_URL'], '/');
        $rel = ($rel === '/') ? '' : $rel;
        define('APP_URL', $rel);

    } else {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $appBase = realpath(APP_BASE);

        $rel = '';
        if ($docRoot && $appBase && strpos($appBase, $docRoot) === 0) {
            $rel = str_replace('\\', '/', substr($appBase, strlen($docRoot)));
            $rel = '/' . ltrim($rel, '/');
            $rel = rtrim($rel, '/');
        }

        if ($rel === '' || $rel === '/') {
            $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $rel = rtrim(preg_replace('#/(order|users|config)$#', '', $scriptDir), '/');
            $rel = ($rel === '/') ? '' : $rel;
        }

        define('APP_URL', $rel);
    }
}

/**
 * ✅ BASE_URL absoluta (para links externos)
 */
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $scheme . $host . (APP_URL ? APP_URL : ''));
}

/**
 * ✅ url() = SIEMPRE apunta a la INSTANCIA (navegación interna)
 */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        $base = defined('SYSTEC_APP_URL')
            ? (string)SYSTEC_APP_URL
            : (defined('APP_URL') ? (string)APP_URL : '');

        $base = rtrim($base, '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

/**
 * ✅ core_url() = SIEMPRE apunta al CORE (assets + rutas del core)
 */
if (!function_exists('core_url')) {
    function core_url(string $path = ''): string {
        $base = defined('SYSTEC_CORE_URL') ? (string)SYSTEC_CORE_URL : '';
        $base = rtrim($base, '/');

        // fallback si entras directo al CORE (sin puente)
        if ($base === '') {
            $base = rtrim((string)APP_URL, '/');
        }

        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

/* ==========================================================
   ✅ VERSIÓN GLOBAL DEL SISTEMA (cambiar aquí para v1.2, v1.3…)
   ========================================================== */
if (!defined('SYSTEC_VERSION')) {
    // Prioridad: instance.php puede sobre-escribir si quieres por cliente
    $ver = trim((string)($INSTANCE['SYSTEC_VERSION'] ?? 'v1.1'));
    if ($ver === '') $ver = 'v1.1';
    define('SYSTEC_VERSION', $ver);
}

/**
 * ✅ DB (desde instance.php o defaults)
 */
$DB_HOST = $INSTANCE['DB_HOST'] ?? 'localhost';
$DB_NAME = $INSTANCE['DB_NAME'] ?? 'ckcl_systec_c2k';
$DB_USER = $INSTANCE['DB_USER'] ?? 'ckcl_systec_c2k';
$DB_PASS = $INSTANCE['DB_PASS'] ?? '112233Kdoki.';

if (!defined('DB_HOST')) define('DB_HOST', $DB_HOST);
if (!defined('DB_NAME')) define('DB_NAME', $DB_NAME);
if (!defined('DB_USER')) define('DB_USER', $DB_USER);
if (!defined('DB_PASS')) define('DB_PASS', $DB_PASS);

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // ✅ Forzar conexión a UTF8MB4 a nivel sesión
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

} catch (PDOException $e) {
    error_log('[DB] ' . $e->getMessage());
    http_response_code(500);
    exit('❌ Error de conexión a la base de datos.');
}
