<?php
// config/config_publico.php
// SysTec Multi-Cliente (PUBLICO)
// - Sin sesión obligatoria
// - Lee instance.php si viene desde puente (SYSTEC_INSTANCE_PATH)
// - Respeta SYSTEC_APP_URL (ruta pública de la instancia)
// - Permite core_url() para assets/logo del CORE
// - NO setea Content-Type acá (para no romper endpoints que sirven imagen/PDF)

// UTF-8 interno (sin headers)
mb_internal_encoding('UTF-8');

if (!defined('APP_BASE')) {
    define('APP_BASE', realpath(__DIR__ . '/..')); // root del CORE v1.1
}

/**
 * ✅ Cargar instance.php:
 * - En modo instancia: viene desde el puente (SYSTEC_INSTANCE_PATH)
 * - En modo CORE directo: cae a /config/instance.php si existe
 */
$INSTANCE_PATH = defined('SYSTEC_INSTANCE_PATH')
    ? SYSTEC_INSTANCE_PATH
    : (__DIR__ . '/instance.php');

$INSTANCE = [];

if (is_file($INSTANCE_PATH) && is_readable($INSTANCE_PATH)) {
    $tmp = require $INSTANCE_PATH;
    if (is_array($tmp)) $INSTANCE = $tmp;
    unset($tmp);
}

/**
 * ✅ APP_URL (ruta web base, relativa)
 * Prioridad:
 * 1) Puente: SYSTEC_APP_URL (instancia)
 * 2) instance.php: APP_URL
 * 3) Auto-detect
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
        // Auto-detect (si entras directo a core)
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
 * ✅ BASE_URL absoluta (para WhatsApp/links externos)
 */
if (!defined('BASE_URL')) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $scheme . $host . (APP_URL ? APP_URL : ''));
}

/**
 * ✅ url() (siempre a la instancia)
 */
if (!function_exists('url')) {
    function url(string $path = ''): string {
        $base = defined('APP_URL') ? (string)APP_URL : '';
        $base = rtrim($base, '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

/**
 * ✅ CORE_URL + core_url() (para assets/logo del CORE)
 * Ideal: puente define SYSTEC_CORE_URL.
 */
if (!defined('CORE_URL')) {
    if (defined('SYSTEC_CORE_URL') && (string)SYSTEC_CORE_URL !== '') {
        define('CORE_URL', rtrim((string)SYSTEC_CORE_URL, '/'));
    } else {
        // fallback si entras directo al CORE (sin puente)
        define('CORE_URL', rtrim((string)APP_URL, '/'));
    }
}
if (!function_exists('core_url')) {
    function core_url(string $path = ''): string {
        $base = rtrim((string)CORE_URL, '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

/**
 * ✅ DB (desde instance.php si existe, si no defaults)
 */
$DB_HOST = $INSTANCE['DB_HOST'] ?? 'localhost';
$DB_NAME = $INSTANCE['DB_NAME'] ?? 'ckcl_systec_c2k';
$DB_USER = $INSTANCE['DB_USER'] ?? 'ckcl_systec_c2k';
$DB_PASS = $INSTANCE['DB_PASS'] ?? '112233Kdoki.';

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
    http_response_code(500);
    exit("❌ Error de conexión a la base de datos.");
}
