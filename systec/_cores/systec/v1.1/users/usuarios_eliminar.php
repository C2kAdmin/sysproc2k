<?php
// users/usuarios_eliminar.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN siempre pasa)
require_role(['ADMIN']);

// ✅ Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// 1) Leer ID
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Usuario no válido.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// 2) Evitar que el usuario se desactive a sí mismo + definir permisos
$miId  = (int)($_SESSION['usuario_id'] ?? 0);
$miRol = strtoupper(trim((string)($_SESSION['usuario_rol'] ?? '')));

// ⚠️ ID del ADMIN dueño (Yeison) en ESTA BD
$OWNER_ADMIN_ID = 6;
$soyOwnerAdmin  = ($miId === $OWNER_ADMIN_ID);

if ($id === $miId) {
    $_SESSION['flash_error'] = 'No puedes desactivar tu propio usuario.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}
// 3) Verificar que exista + detectar SUPER_ADMIN (blindaje)
$stmt = $pdo->prepare("
    SELECT id, activo, rol, is_super_admin
    FROM usuarios
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    $_SESSION['flash_error'] = 'El usuario no existe.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// Si ya está inactivo, no hacemos drama
if ((int)($usuario['activo'] ?? 0) === 0) {
    $_SESSION['flash_ok'] = 'El usuario ya estaba desactivado.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// ✅ BLOQUEO DURO: no se puede desactivar al SUPER_ADMIN (ni por rol ni por flag)
$rolObjetivo = strtoupper(trim((string)($usuario['rol'] ?? '')));
$isSuperFlag = (int)($usuario['is_super_admin'] ?? 0) === 1;

if ($rolObjetivo === 'SUPER_ADMIN' || $isSuperFlag) {
    $_SESSION['flash_error'] = 'No puedes desactivar al SUPER_ADMIN.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// ✅ NUEVO: si el objetivo es ADMIN => solo SUPER_ADMIN o OwnerAdmin puede desactivar
$esAdminObjetivo = in_array($rolObjetivo, ['ADMIN','SUPER_ADMIN'], true);

if ($esAdminObjetivo) {
    if (!($miRol === 'SUPER_ADMIN' || $soyOwnerAdmin)) {
        $_SESSION['flash_error'] = 'No tienes permisos para desactivar administradores.';
        header('Location: ' . url('/users/usuarios.php'));
        exit;
    }
}
// ✅ BLOQUEO EXTRA: no permitir desactivar el ÚLTIMO ADMIN/SUPER_ADMIN activo
if (in_array($rolObjetivo, ['ADMIN', 'SUPER_ADMIN'], true)) {

    $stmt = $pdo->query("
        SELECT COUNT(*)
        FROM usuarios
        WHERE activo = 1
          AND (rol IN ('ADMIN','SUPER_ADMIN') OR is_super_admin = 1)
    ");
    $adminsActivos = (int)$stmt->fetchColumn();

    if ($adminsActivos <= 1) {
        $_SESSION['flash_error'] = 'No puedes desactivar el último administrador activo del sistema.';
        header('Location: ' . url('/users/usuarios.php'));
        exit;
    }
}

// 4) Desactivar (eliminación lógica)
$stmt = $pdo->prepare("
    UPDATE usuarios
    SET activo = 0
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);

$_SESSION['flash_ok'] = 'Usuario desactivado correctamente.';
header('Location: ' . url('/users/usuarios.php'));
exit;
