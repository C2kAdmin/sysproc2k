<?php
// config/session_config.php
// SysTec v1.2 — Arranque centralizado de sesión (multi-instancia)
//
// Objetivo:
// - Aislar sesión por instancia (evitar logins cruzados entre clientes)
// - Cookie path basado en el PATH de SYSTEC_APP_URL (URL pública real de la instancia)
//
// Requisito:
// - El puente de la instancia (public/index.php) debe definir SYSTEC_APP_URL.

if (!function_exists('systec_session_boot')) {
    function systec_session_boot(): void
    {
        if (session_status() !== PHP_SESSION_NONE) return;

        $appUrl = defined('SYSTEC_APP_URL') ? (string) SYSTEC_APP_URL : '';
        $appUrl = trim($appUrl);

        // Cookie path = solo el PATH de la URL (no la URL completa)
        $cookiePath = '/';
        if ($appUrl !== '') {
            $parsedPath = parse_url($appUrl, PHP_URL_PATH);
            if (is_string($parsedPath) && $parsedPath !== '') {
                $cookiePath = $parsedPath;
            }
        }

        // Normalizar path
        if ($cookiePath[0] !== '/') $cookiePath = '/' . $cookiePath;
        $cookiePath = preg_replace('#/+#', '/', $cookiePath);
        if (substr($cookiePath, -1) !== '/') $cookiePath .= '/';

        // Nombre de sesión único por instancia (o por instalación si no hay APP_URL)
        $salt = ($appUrl !== '') ? $appUrl : (realpath(__DIR__ . '/..') ?: 'systec');
        $hash = substr(sha1($salt), 0, 12);
        session_name('SYSTECSESS_' . strtoupper($hash));

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        // PHP 7.3+ soporta array en session_set_cookie_params
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }
}
