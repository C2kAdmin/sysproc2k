<?php
// users/usuarios_guardar.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN siempre pasa por auth.php)
require_role(['ADMIN']);

// ✅ Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// Leer y normalizar datos
$nombre   = trim($_POST['nombre']   ?? '');
$email    = trim($_POST['email']    ?? '');
$usuario  = trim($_POST['usuario']  ?? '');
$password = (string)($_POST['password'] ?? '');
$rol      = strtoupper(trim($_POST['rol'] ?? ''));
$activo   = isset($_POST['activo']) ? 1 : 0;

// ✅ Roles permitidos (NO se puede crear SUPER_ADMIN desde el formulario)
$rolesPermitidos = ['ADMIN', 'TECNICO', 'RECEPCION'];

// Validaciones básicas
if ($nombre === '' || $usuario === '' || $password === '' || $rol === '') {
    $_SESSION['flash_error'] = 'Complete todos los campos obligatorios.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

if (!in_array($rol, $rolesPermitidos, true)) {
    $_SESSION['flash_error'] = 'Rol no permitido.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// ✅ Password mínimo (ajústalo si quieres)
if (strlen($password) < 6) {
    $_SESSION['flash_error'] = 'La contraseña debe tener al menos 6 caracteres.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

try {
    // Verificar que no exista otro usuario con el mismo "usuario"
    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :usuario LIMIT 1");
    $stmt->execute([':usuario' => $usuario]);
    $yaExiste = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($yaExiste) {
        $_SESSION['flash_error'] = 'El usuario ya existe. Elija otro nombre de usuario.';
        header('Location: ' . url('/users/usuarios.php'));
        exit;
    }

    // ✅ Si viene email, validar que no esté repetido (recomendado si login permite email)
    if ($email !== '') {
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $emailExiste = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($emailExiste) {
            $_SESSION['flash_error'] = 'El email ya está en uso por otro usuario.';
            header('Location: ' . url('/users/usuarios.php'));
            exit;
        }
    }

    // Hash de contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Insertar (is_super_admin siempre 0 desde UI)
    $stmt = $pdo->prepare("
        INSERT INTO usuarios (nombre, email, usuario, password_hash, rol, activo, is_super_admin)
        VALUES (:nombre, :email, :usuario, :password_hash, :rol, :activo, 0)
    ");

    $stmt->execute([
        ':nombre'        => $nombre,
        ':email'         => ($email !== '' ? $email : null),
        ':usuario'       => $usuario,
        ':password_hash' => $password_hash,
        ':rol'           => $rol,
        ':activo'        => $activo,
    ]);

    $_SESSION['flash_ok'] = 'Usuario creado correctamente.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;

} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error al crear usuario. Intente nuevamente.';
    // Si quieres ver el error real (solo en desarrollo):
    // $_SESSION['flash_error'] = 'Error al crear usuario: ' . $e->getMessage();
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}
