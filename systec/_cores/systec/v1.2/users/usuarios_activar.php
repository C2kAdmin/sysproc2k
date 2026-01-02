<?php
// users/usuarios_activar.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN pasa)
require_role(['ADMIN']);

// ✅ Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Usuario no válido.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

$miRol = strtoupper(trim((string)($_SESSION['usuario_rol'] ?? '')));
$miId  = (int)($_SESSION['usuario_id'] ?? 0);

// ⚠️ ID del ADMIN dueño (Yeison) en ESTA BD
$OWNER_ADMIN_ID = 6;
$soyOwnerAdmin  = ($miId === $OWNER_ADMIN_ID);

try {
    $stmt = $pdo->prepare("
        SELECT id, rol, is_super_admin
        FROM usuarios
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
        $_SESSION['flash_error'] = 'El usuario no existe.';
        header('Location: ' . url('/users/usuarios.php'));
        exit;
    }

    $uRol = strtoupper(trim((string)($u['rol'] ?? '')));
    $uIsSuper = ((int)($u['is_super_admin'] ?? 0) === 1) || ($uRol === 'SUPER_ADMIN');

    // No tocar SUPER_ADMIN si no eres SUPER_ADMIN
    if ($uIsSuper && !is_super_admin()) {
        http_response_code(403);
        echo "<h3 style='font-family:Arial'>403 · Sin permisos</h3>";
        exit;
    }

    // Si el objetivo es ADMIN => solo SUPER_ADMIN o OwnerAdmin puede activarlo
    $esAdminObjetivo = in_array($uRol, ['ADMIN','SUPER_ADMIN'], true) || $uIsSuper;
    if ($esAdminObjetivo && !($miRol === 'SUPER_ADMIN' || $soyOwnerAdmin)) {
        http_response_code(403);
        echo "<h3 style='font-family:Arial'>403 · Sin permisos</h3>";
        exit;
    }

    // Activar
    $stmt = $pdo->prepare("UPDATE usuarios SET activo = 1 WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);

    $_SESSION['flash_ok'] = 'Usuario activado correctamente.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;

} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error al activar usuario.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}
