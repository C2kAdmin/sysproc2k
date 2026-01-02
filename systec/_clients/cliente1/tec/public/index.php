<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Puente INSTANCIA -> CORE
 * Cliente: cliente1
 * Sistema: SysTec <?php echo SYSTEC_VERSION; ?>

 * Versión: v1.1
 */

// ✅ Rutas correctas según tu estructura REAL
define('SYSTEC_CORE_PATH', realpath(__DIR__ . '/../../../../_cores/systec/v1.2'));
define('SYSTEC_INSTANCE_PATH', realpath(__DIR__ . '/../config/instance.php'));

// ✅ Validaciones
if (!SYSTEC_CORE_PATH || !is_dir(SYSTEC_CORE_PATH)) {
    http_response_code(500);
    exit('❌ CORE SysTec no encontrado');
}
if (!SYSTEC_INSTANCE_PATH || !is_file(SYSTEC_INSTANCE_PATH)) {
    http_response_code(500);
    exit('❌ instance.php no encontrado');
}

// ✅ APP_URL de ESTA instancia (ruta pública donde vive /public)
$base = rtrim(str_replace('\\','/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($base === '/' || $base === '') $base = '';
define('SYSTEC_APP_URL', $base);

// ✅ CORE_URL (ruta web al CORE) para cargar assets directo desde el CORE
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
$coreFs  = realpath(SYSTEC_CORE_PATH);

$coreRel = '';
if ($docRoot && $coreFs && strpos($coreFs, $docRoot) === 0) {
    $coreRel = str_replace('\\','/', substr($coreFs, strlen($docRoot)));
    $coreRel = '/' . ltrim($coreRel, '/');
}
$coreRel = rtrim($coreRel, '/');
define('SYSTEC_CORE_URL', $coreRel);

// ✅ Ejecutar router del CORE
require SYSTEC_CORE_PATH . '/router.php';
