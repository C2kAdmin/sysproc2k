<?php
// logout.php

require_once __DIR__ . '/config/config.php';

// Vaciar variables de sesión
$_SESSION = [];

// Eliminar cookie de sesión si existe
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        (bool)($params['secure'] ?? false),
        (bool)($params['httponly'] ?? true)
    );
}

// Destruir sesión
session_destroy();

// Redirigir al login (siempre con helper)
header('Location: ' . url('/login.php'));
exit;
