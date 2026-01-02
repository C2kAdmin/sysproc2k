<?php
/**
 * Router CORE SysTec servido desde instancia.
 * Requiere:
 * - SYSTEC_CORE_PATH
 * - SYSTEC_INSTANCE_PATH
 * - SYSTEC_APP_URL
 */

$core = rtrim(SYSTEC_CORE_PATH, '/');

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$base = defined('SYSTEC_APP_URL') ? (string)SYSTEC_APP_URL : '';
$base = rtrim($base, '/');

if ($base !== '' && strpos($uriPath, $base) === 0) {
    $rel = substr($uriPath, strlen($base));
} else {
    $rel = $uriPath;
}
$rel = '/' . ltrim($rel, '/');

if ($rel === '/' || $rel === '/index.php') {
    $rel = '/login.php';
}

if (strpos($rel, '..') !== false) {
    http_response_code(400);
    exit('Ruta inválida');
}

$target = $core . $rel;

if (is_dir($target)) {
    $target = rtrim($target, '/') . '/index.php';
}

if (!is_file($target)) {
    http_response_code(404);
    exit('404 - No encontrado');
}

$ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));

if ($ext === 'php') {
    require $target;
    exit;
}

$map = [
    'css'   => 'text/css',
    'js'    => 'application/javascript',
    'png'   => 'image/png',
    'jpg'   => 'image/jpeg',
    'jpeg'  => 'image/jpeg',
    'webp'  => 'image/webp',
    'gif'   => 'image/gif',
    'svg'   => 'image/svg+xml',
    'ico'   => 'image/x-icon',
    'woff'  => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf'   => 'font/ttf',
    'json'  => 'application/json',
];

header('Content-Type: ' . ($map[$ext] ?? 'application/octet-stream'));
readfile($target);
exit;
