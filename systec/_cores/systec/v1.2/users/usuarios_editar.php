<?php
// users/usuarios_editar.php

require_once __DIR__ . '/../config/auth.php';

// ✅ Solo ADMIN (SUPER_ADMIN siempre pasa)
require_role(['ADMIN']);

// Obtener ID de la URL
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_error'] = 'Usuario no válido.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// Cargar datos del usuario (incluye blindaje)
$stmt = $pdo->prepare("
    SELECT id, nombre, email, usuario, rol, activo, creado_en, is_super_admin
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

// ✅ Detectar SUPER_ADMIN por rol o flag
$rolObjetivo = strtoupper(trim((string)($usuario['rol'] ?? '')));
$isSuperFlag = (int)($usuario['is_super_admin'] ?? 0) === 1;
$objetivoEsSuper = ($rolObjetivo === 'SUPER_ADMIN' || $isSuperFlag);

// ✅ BLOQUEO: un ADMIN NO puede editar al SUPER_ADMIN
if (!is_super_admin() && $objetivoEsSuper) {
    $_SESSION['flash_error'] = 'No tienes permisos para editar al SUPER_ADMIN.';
    header('Location: ' . url('/users/usuarios.php'));
    exit;
}

// Mensajes flash
$mensaje_ok    = $_SESSION['flash_ok']    ?? '';
$mensaje_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Editar usuario</h4>

            <?php if ($mensaje_ok): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($mensaje_ok); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($mensaje_error); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Editando: <?php echo htmlspecialchars((string)$usuario['nombre']); ?>
                            </h5>
                            <p class="card-category">
                                ID #<?php echo (int)$usuario['id']; ?> &mdash; creado el
                                <?php echo htmlspecialchars((string)$usuario['creado_en']); ?>
                            </p>

                            <?php if ($objetivoEsSuper): ?>
                                <div class="alert alert-warning mb-0 mt-2">
                                    Este usuario es <strong>SUPER_ADMIN</strong>. Su rol y estado no se pueden modificar.
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="card-body">
                            <form method="post"
                                  action="<?php echo url('/users/usuarios_actualizar.php'); ?>"
                                  autocomplete="off">

                                <input type="hidden" name="id" value="<?php echo (int)$usuario['id']; ?>">

                                <div class="form-group">
                                    <label>Nombre completo *</label>
                                    <input type="text"
                                           name="nombre"
                                           class="form-control"
                                           required
                                           autocomplete="off"
                                           value="<?php echo htmlspecialchars((string)$usuario['nombre']); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Email (opcional)</label>
                                    <input type="email"
                                           name="email"
                                           class="form-control"
                                           autocomplete="off"
                                           value="<?php echo htmlspecialchars((string)($usuario['email'] ?? '')); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Usuario *</label>
                                    <input type="text"
                                           name="usuario"
                                           class="form-control"
                                           required
                                           autocomplete="new-username"
                                           value="<?php echo htmlspecialchars((string)$usuario['usuario']); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Rol *</label>
                                    <select name="rol"
                                            class="form-control"
                                            required
                                            <?php echo $objetivoEsSuper ? 'disabled' : ''; ?>>
                                        <option value="">Seleccione...</option>

                                        <?php $rolActual = strtoupper(trim((string)($usuario['rol'] ?? ''))); ?>

                                        <option value="ADMIN"     <?php echo ($rolActual === 'ADMIN' ? 'selected' : ''); ?>>Administrador</option>
                                        <option value="TECNICO"   <?php echo ($rolActual === 'TECNICO' ? 'selected' : ''); ?>>Técnico</option>
                                        <option value="RECEPCION" <?php echo ($rolActual === 'RECEPCION' ? 'selected' : ''); ?>>Recepción</option>
                                    </select>

                                    <?php if ($objetivoEsSuper): ?>
                                        <!-- Si el select está disabled, no enviará rol. Lo enviamos oculto. -->
                                        <input type="hidden" name="rol" value="<?php echo htmlspecialchars((string)$rolActual); ?>">
                                    <?php endif; ?>
                                </div>

                                <div class="form-group form-check">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="activo"
                                           name="activo"
                                           <?php echo ((int)$usuario['activo'] === 1 ? 'checked' : ''); ?>
                                           <?php echo $objetivoEsSuper ? 'disabled' : ''; ?>>
                                    <label class="form-check-label" for="activo">Usuario activo</label>

                                    <?php if ($objetivoEsSuper): ?>
                                        <!-- Mantener el valor real si está disabled -->
                                        <input type="hidden" name="activo" value="<?php echo ((int)$usuario['activo'] === 1 ? '1' : '0'); ?>">
                                    <?php endif; ?>
                                </div>

                                <hr>

                                <div class="form-group">
                                    <label>Nueva contraseña (opcional)</label>
                                    <input type="password"
                                           name="password"
                                           class="form-control"
                                           autocomplete="new-password">
                                    <small class="form-text text-muted">
                                        Déjalo en blanco si no quieres cambiar la contraseña.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label>Repetir nueva contraseña</label>
                                    <input type="password"
                                           name="password2"
                                           class="form-control"
                                           autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    Guardar cambios
                                </button>

                                <a href="<?php echo url('/users/usuarios.php'); ?>" class="btn btn-secondary">
                                    Volver al listado
                                </a>

                            </form>
                        </div>
                    </div>
                </div>
            </div><!-- row -->

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
