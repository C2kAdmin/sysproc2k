<?php
/**
 * Puente INSTANCIA -> CORE
 * Cliente: cliente1
 * Versión Core: v1.2
 */

define('SYSTEC_CORE_PATH', realpath(__DIR__ . '/../../../../_cores/systec/v1.2'));
define('SYSTEC_INSTANCE_PATH', realpath(__DIR__ . '/../config/instance.php'));

if (!SYSTEC_CORE_PATH || !is_dir(SYSTEC_CORE_PATH)) exit('CORE no encontrado');
if (!SYSTEC_INSTANCE_PATH || !is_file(SYSTEC_INSTANCE_PATH)) exit('instance.php no encontrado');

// APP_URL de ESTA instancia
$base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base === '/' || $base === '') $base = '';
define('SYSTEC_APP_URL', $base . '/');

// display_errors depende de ENV
$__cfg = require SYSTEC_INSTANCE_PATH;
$__env = strtolower((string)($__cfg['ENV'] ?? 'prod'));

if ($__env === 'dev') {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(E_ALL);
}

// CORE_URL para assets
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$coreFs  = realpath(SYSTEC_CORE_PATH);

$coreRel = '';
if ($docRoot && $coreFs && strpos($coreFs, $docRoot) === 0) {
    $coreRel = str_replace('\\','/', substr($coreFs, strlen($docRoot)));
    $coreRel = '/' . ltrim($coreRel, '/');
}
$coreRel = rtrim($coreRel, '/');
define('SYSTEC_CORE_URL', $coreRel);

require SYSTEC_CORE_PATH . '/router.php';