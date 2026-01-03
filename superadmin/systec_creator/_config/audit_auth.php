<?php
declare(strict_types=1);

// superadmin/systec_creator/_config/audit_auth.php
// Wrapper para auditorías: NO duplicar lógica.

require_once __DIR__ . '/auth.php';

// (auth.php ya trae require_super_admin + current_user_email + current_user_username)
