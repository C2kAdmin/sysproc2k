<?php
// /_clients/GSC/tec/public/manifest.php
// Manifest PWA por instancia (usa CORE config_publico.php)

// 1) Rutas físicas (ajusta solo si tu CORE está en otra parte)
$CORE_PATH = realpath(__DIR__ . '/../../../../_cores/systec/v1.1'); 
// Desde: /_clients/GSC/tec/public -> subir 4 niveles hasta /SysTec y entrar a /_cores/...

$INSTANCE_PATH = realpath(__DIR__ . '/../config/instance.php');

if (!$CORE_PATH || !is_dir($CORE_PATH)) {
    http_response_code(500);
    exit('CORE_PATH inválido');
}
if (!$INSTANCE_PATH || !is_file($INSTANCE_PATH)) {
    http_response_code(500);
    exit('INSTANCE_PATH inválido');
}

// 2) Definir constantes que el CORE espera
if (!defined('SYSTEC_CORE_PATH'))     define('SYSTEC_CORE_PATH', $CORE_PATH);
if (!defined('SYSTEC_INSTANCE_PATH')) define('SYSTEC_INSTANCE_PATH', $INSTANCE_PATH);

// 3) APP_URL de la instancia (auto-detect)
// Ej: si el manifest está en /syspro/systec/_clients/GSC/tec/public/manifest.php
// entonces APP_URL debe quedar: /syspro/systec/_clients/GSC/tec/public
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$appUrl = rtrim($scriptDir, '/'); // termina sin slash
if ($appUrl === '/') $appUrl = '';

if (!defined('SYSTEC_APP_URL')) define('SYSTEC_APP_URL', $appUrl);

// 4) CORE_URL (no es crítico para manifest, pero lo dejamos correcto)
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
$coreUrl = $scheme . $host . '/syspro/systec/_cores/systec/v1.1';
if (!defined('SYSTEC_CORE_URL')) define('SYSTEC_CORE_URL', $coreUrl);

// 5) Cargar config público del CORE (aquí nace $pdo, url(), etc.)
require_once SYSTEC_CORE_PATH . '/config/config_publico.php';

// 6) Leer parámetros desde la BD
function getParametro($clave, $default = '')
{
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :c LIMIT 1");
        $s->execute([':c' => $clave]);
        $f = $s->fetch(PDO::FETCH_ASSOC);
        if ($f && $f['valor'] !== '') return $f['valor'];
    } catch (Exception $e) {}
    return $default;
}

$name  = getParametro('nombre_negocio', 'SysTec');
$short = getParametro('nombre_corto', 'GSC');
$theme = getParametro('theme_color', '#0b1f3a');
$bg    = getParametro('background_color', $theme);

// OJO: tú tienes gsc-192.png y gsc-512.png (no app-xxx)
$icon192 = url('/assets/icons/gsc-192.png');
$icon512 = url('/assets/icons/gsc-512.png');

$startUrl = url('/'); // abre la instancia
$scope    = url('/');

header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    "name" => $name,
    "short_name" => ($short !== '' ? $short : $name),
    "start_url" => $startUrl,
    "scope" => $scope,
    "display" => "standalone",
    "background_color" => $bg,
    "theme_color" => $theme,
    "icons" => [
        ["src" => $icon192, "sizes" => "192x192", "type" => "image/png"],
        ["src" => $icon512, "sizes" => "512x512", "type" => "image/png"],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
