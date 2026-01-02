<?php
// config/auth.php

require_once __DIR__ . '/config.php';

// ✅ asegurar sesión SIEMPRE antes de leer $_SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * ✅ Valida que el usuario logueado siga existiendo y esté habilitado.
 * - Si no existe o está inactivo: destruye sesión y redirige a login.
 * - (Opcional) refresca nombre/rol desde BD para evitar privilegios “pegados”.
 */
function enforce_user_session(): void {
    if (!isset($_SESSION['usuario_id'])) return;

    $uid = (int)($_SESSION['usuario_id'] ?? 0);
    if ($uid <= 0) {
        session_destroy();
        header('Location: ' . url('/login.php'));
        exit;
    }

    global $pdo;

    try {
        // 👇 Ajusta nombres si tu tabla usa otros campos:
        // activo / estado / habilitado, etc.
        $stmt = $pdo->prepare("
            SELECT id, usuario, nombre, rol, activo
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $uid]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si no existe o no está activo -> fuera
        if (!$u || (isset($u['activo']) && (int)$u['activo'] !== 1)) {
            // matar sesión
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();

            header('Location: ' . url('/login.php'));
            exit;
        }

        // ✅ refrescar datos de sesión (evita “rol pegado” si cambiaste en BD)
        if (!empty($u['nombre']))  $_SESSION['usuario_nombre'] = (string)$u['nombre'];
        if (!empty($u['rol']))     $_SESSION['usuario_rol']    = (string)$u['rol'];
        if (!empty($u['usuario'])) $_SESSION['usuario_user']   = (string)$u['usuario'];

    } catch (Exception $e) {
        // Si falla BD, no cortamos el sistema (pero logueamos)
        error_log('[AUTH] enforce_user_session error: ' . $e->getMessage());
    }
}

function require_login(): void {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: ' . url('/login.php'));
        exit;
    }

    // ✅ aquí está la magia
    enforce_user_session();
}

function current_user_id(): int {
    return (int)($_SESSION['usuario_id'] ?? 0);
}

function current_role(): string {
    return strtoupper(trim((string)($_SESSION['usuario_rol'] ?? '')));
}

/**
 * ✅ SUPER ADMIN real:
 * - Si rol en sesión es SUPER_ADMIN -> OK
 * - O si en BD usuarios.is_super_admin = 1 -> OK
 */
function is_super_admin(): bool {
    require_login();

    // 1) Por rol en sesión (rápido)
    if (current_role() === 'SUPER_ADMIN') {
        return true;
    }

    // 2) Por flag en BD
    $uid = current_user_id();
    if ($uid <= 0) return false;

    static $cache = [];
    if (array_key_exists($uid, $cache)) {
        return (bool)$cache[$uid];
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT is_super_admin FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $uid]);
        $cache[$uid] = ((int)$stmt->fetchColumn() === 1);
        return (bool)$cache[$uid];
    } catch (Exception $e) {
        $cache[$uid] = false;
        return false;
    }
}

function require_role(array $allowedRoles): void {
    require_login();

    if (is_super_admin()) return;

    $role = current_role();
    $allowed = array_map(fn($r) => strtoupper(trim((string)$r)), $allowedRoles);

    if (!in_array($role, $allowed, true)) {
        http_response_code(403);
        echo "<h3 style='font-family:Arial'>403 · Sin permisos</h3>";
        exit;
    }
}

function require_super_admin(): void {
    require_login();

    if (!is_super_admin()) {
        http_response_code(403);
        echo "<h3 style='font-family:Arial'>403 · Solo SUPER_ADMIN</h3>";
        exit;
    }
}
