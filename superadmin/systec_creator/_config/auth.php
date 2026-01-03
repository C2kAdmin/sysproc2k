<?php
declare(strict_types=1);

// superadmin/systec_creator/_config/auth.php

require_once __DIR__ . '/config.php';

if (!function_exists('require_super_admin')) {
    function require_super_admin(): void
    {
        $ok = isset($_SESSION['super_admin'], $_SESSION['sa_user_id'])
            && $_SESSION['super_admin'] === true
            && (int)$_SESSION['sa_user_id'] > 0;

        if (!$ok) {
            header('Location: ' . sa_url('/login.php'), true, 302);
            exit;
        }
    }
}

if (!function_exists('sa_current_user_email')) {
    function sa_current_user_email(): string
    {
        return (string)($_SESSION['sa_user_email'] ?? '');
    }
}

if (!function_exists('sa_current_user_username')) {
    function sa_current_user_username(): string
    {
        return (string)($_SESSION['sa_user_username'] ?? '');
    }
}
