<?php
// users/usuarios_actualizar.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN pasa)
require_role(['ADMIN']);

// ✅ Solo aceptamos POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// 1) Leer y normalizar datos
$id       = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$nombre   = trim($_POST['nombre']   ?? '');
$email    = trim($_POST['email']    ?? '');
$usuario  = trim($_POST['usuario']  ?? '');
$rol      = strtoupper(trim($_POST['rol'] ?? ''));

// ✅ OJO: activo viene como checkbox o hidden -> tomamos valor real
$activoRaw = $_POST['activo'] ?? null;
$activo    = ($activoRaw === null) ? 0 : (int)$activoRaw; // si no viene, 0; si viene "1" o "0", respetar

$password  = (string)($_POST['password']  ?? '');
$password2 = (string)($_POST['password2'] ?? '');

if ($id <= 0) {
    $_SESSION['flash_error'] = 'Usuario no válido.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// 2) Validaciones básicas
if ($nombre === '' || $usuario === '' || $rol === '') {
    $_SESSION['flash_error'] = 'Complete todos los campos obligatorios.';
    header('Location: ' . url('/users/usuarios_editar.php?id=' . $id));
    exit;
}

// ✅ Roles permitidos (NO permitir asignar SUPER_ADMIN desde formulario)
$rolesPermitidos = ['ADMIN', 'TECNICO', 'RECEPCION'];

/*
  OJO: si el usuario objetivo es SUPER_ADMIN, el formulario manda rol=SUPER_ADMIN (hidden)
  y eso NO debe bloquear el update porque NO estamos cambiando rol, solo preservándolo.
  La validación real se hace DESPUÉS de cargar el usuario objetivo.
*/

// Validamos rol SOLO si NO viene como SUPER_ADMIN (caso normal)
if ($rol !== 'SUPER_ADMIN' && !in_array($rol, $rolesPermitidos, true)) {
    $_SESSION['flash_error'] = 'Rol no permitido.';
    header('Location: ' . url('/users/usuarios_editar.php?id=' . $id));
    exit;
}
// 3) Si se va a cambiar la contraseña, validar coincidencia
$tieneNuevaPassword = ($password !== '' || $password2 !== '');
if ($tieneNuevaPassword) {
    if ($password === '' || $password2 === '') {
        $_SESSION['flash_error'] = 'Debe completar ambos campos de contraseña.';
        header('Location: ' . url('/users/usuarios_editar.php?id=' . $id));
        exit;
    }
    if ($password !== $password2) {
        $_SESSION['flash_error'] = 'Las contraseñas no coinciden.';
        header('Location: ' . url('/users/usuarios_editar.php?id=' . $id));
        exit;
    }
}

// 4) Cargar usuario objetivo (incluye blindaje super admin)
$stmt = $pdo->prepare("
    SELECT id, nombre, rol, is_super_admin
    FROM usuarios
    WHERE id = :id
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$usuarioObjetivo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuarioObjetivo) {
    $_SESSION['flash_error'] = 'El usuario no existe.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

$rolObjetivoActual = strtoupper(trim((string)($usuarioObjetivo['rol'] ?? '')));
$objetivoEsSuper = ((int)($usuarioObjetivo['is_super_admin'] ?? 0) === 1)
    || ($rolObjetivoActual === 'SUPER_ADMIN');

// ✅ BLINDAJE CLAVE:
// Si el objetivo es SUPER_ADMIN, solo un SUPER_ADMIN puede editarlo.
if ($objetivoEsSuper && !is_super_admin()) {
    http_response_code(403);
    echo "<h3 style='font-family:Arial'>403 · Sin permisos</h3>";
    exit;
}

// ✅ EXTRA “intocable”:
if ($objetivoEsSuper) {
    $rol    = 'SUPER_ADMIN';
    $activo = 1;
}
// 5) Evitar que un admin se quite a sí mismo el rol ADMIN
$miId = (int)($_SESSION['usuario_id'] ?? 0);
if ($id === $miId && !is_super_admin() && $rol !== 'ADMIN') {
    $_SESSION['flash_error'] = 'No puedes quitarte a ti mismo el rol de ADMIN.';
    header('Location: ' . url('/users/usuarios_editar.php?id=' . $id));
    exit;
}

// 6) Verificar que no exista otro registro con el mismo "usuario"
$stmt = $pdo->prepare("
    SELECT id
    FROM usuarios
    WHERE usuario = :usuario
      AND id <> :id
    LIMIT 1
");
$stmt->execute([
    ':usuario' => $usuario,
    ':id'      => $id,
]);
$yaExiste = $stmt->fetch(PDO::FETCH_ASSOC);

if ($yaExiste) {
    $_SESSION['flash_error'] = 'El nombre de usuario ya está en uso por otro usuario.';
    header('Location: ' . url('/users/usuarios_editar.php?id=' . $id));
    exit;
}

// 7) Preparar UPDATE (NOTA: jamás tocamos is_super_admin aquí)
$campos = [
    'nombre'  => $nombre,
    'email'   => ($email !== '' ? $email : null),
    'usuario' => $usuario,
    'rol'     => $rol,
    'activo'  => $activo,
    'id'      => $id,
];

$sql = "
    UPDATE usuarios
    SET nombre  = :nombre,
        email   = :email,
        usuario = :usuario,
        rol     = :rol,
        activo  = :activo
";

// Si hay nueva contraseña, agregamos al UPDATE
if ($tieneNuevaPassword) {
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $sql .= ", password_hash = :password_hash";
    $campos['password_hash'] = $password_hash;
}

$sql .= " WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute($campos);

// ✅ Si el usuario editado es el mismo logueado, refrescar sesión (UI consistente)
if ($id === $miId) {
    $_SESSION['usuario_nombre'] = $nombre;
    $_SESSION['usuario_rol']    = $rol;
}

// 8) Mensaje y volver
$_SESSION['flash_ok'] = 'Usuario actualizado correctamente.';
header('Location: ' . url('/users/usuarios.php'));
exit;
