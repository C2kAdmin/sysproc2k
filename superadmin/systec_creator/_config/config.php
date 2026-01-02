<?php
declare(strict_types=1);

// superadmin/systec_creator/_config/config.php
// Config propia del creador SysTec (NO CORE)

// Debug (cuando esté estable, baja a 0)
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Sesión propia del superadmin (separada del SysTec)
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('SYS_SUPERADMIN_SYSTEC_CREATOR');
    session_start();
}

// Base URL del módulo (autodetect)
// Ej: https://c2k.cl/syspro/superadmin/systec_creator
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dir    = str_replace('\\', '/', rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/'));

define('SA_BASE_URL', $scheme . $host . $dir);

// Helper de URLs del módulo
if (!function_exists('sa_url')) {
    function sa_url(string $path = ''): string {
        $base = rtrim((string)SA_BASE_URL, '/');
        $path = '/' . ltrim($path, '/');
        return $base . $path;
    }
}

// Rutas físicas útiles para provisioning
define('SYS_ROOT', realpath(__DIR__ . '/../../../') ?: ''); // -> /syspro
define('SYSTEC_ROOT', SYS_ROOT ? (SYS_ROOT . '/systec') : '');
define('SYSTEC_CLIENTS_ROOT', SYSTEC_ROOT ? (SYSTEC_ROOT . '/_clients') : '');
define('SYSTEC_CORES_ROOT', SYSTEC_ROOT ? (SYSTEC_ROOT . '/_cores') : '');

// -------------------------------
// ✅ BD MASTER (REGISTRY CLIENTES)
// -------------------------------
// ⚠️ AJUSTA ESTOS 4 DATOS (una sola vez)
define('MASTER_DB_HOST', 'localhost');
define('MASTER_DB_NAME', 'ckcl_sys_systec_master');
define('MASTER_DB_USER', 'CHANGE_ME');
define('MASTER_DB_PASS', 'CHANGE_ME');

// PDO master (registry)
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

// -------------------------------
// ✅ Flash messages (session)
// -------------------------------
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

// -------------------------------
// ✅ Utils
// -------------------------------
if (!function_exists('sa_post')) {
    function sa_post(string $key, string $default = ''): string
    {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }
}

