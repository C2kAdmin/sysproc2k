<?php
// config/session_config.php
// Arranque centralizado de sesión para TODO el sistema SysTec (multi-instancias)

// Detecta un nombre de sesión único por instalación (por carpeta del sistema)
function systec_session_name(): string
{
    // APP_BASE es el root físico del proyecto (lo define config.php normalmente),
    // pero aquí todavía no lo tenemos garantizado.
    $appBase = realpath(__DIR__ . '/..'); // root del sistema

    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $rel = '';

    if ($docRoot && $appBase && strpos($appBase, $docRoot) === 0) {
        $rel = str_replace('\\', '/', substr($appBase, strlen($docRoot)));
        $rel = '/' . ltrim($rel, '/');
        $rel = trim($rel, '/'); // master_SysTec_c2k/v1.1
    }

    // Fallback por si DOCUMENT_ROOT no calza (raro, pero pasa)
    if ($rel === '') {
        $rel = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? 'systec')), '/');
    }

    // Sanitizar para nombre de cookie de sesión
    $key = preg_replace('/[^a-zA-Z0-9_]/', '_', $rel);
    if ($key === '' || $key === '_') $key = 'SYSTEC';

    // Nombre final: distinto por instalación
    return 'SYSTEC_' . $key;
}

if (session_status() === PHP_SESSION_NONE) {

    // Nombre único por instalación
    $name = systec_session_name();
    session_name($name);

    // (Opcional recomendado) cookie segura según HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    // PHP 7.3+ soporta array en session_set_cookie_params
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',     // mismo dominio
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
