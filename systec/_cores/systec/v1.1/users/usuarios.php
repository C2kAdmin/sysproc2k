<?php
// users/usuarios.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN siempre pasa)
require_role(['ADMIN']);

// Mensajes flash
$mensaje_ok    = $_SESSION['flash_ok']    ?? '';
$mensaje_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

// Cargar usuarios
$miRol = strtoupper(trim((string)($_SESSION['usuario_rol'] ?? '')));

$sql = "
    SELECT id, nombre, email, usuario, rol, activo, creado_en, is_super_admin
    FROM usuarios
";

if ($miRol !== 'SUPER_ADMIN') {
    $sql .= " WHERE (COALESCE(is_super_admin, 0) = 0) AND UPPER(TRIM(rol)) <> 'SUPER_ADMIN' ";
}

$sql .= " ORDER BY id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$lista_usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
// Detectar móvil vs PC (simple y suficiente)
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);

// UI
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* ✅ Compactar formulario (menos altura entre campos) */
#nuevoUsuario .form-group{ margin-bottom:.55rem; }
#nuevoUsuario label{ display:none; } /* ocultamos labels, usamos placeholder */
#nuevoUsuario .form-check label{ display:inline-block; } /* pero el checkbox sí mantiene texto */
/* ✅ Header responsive (mismo estilo que dashboard/orden_detalle) */
.users-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.users-title h4{ margin:0; }
.users-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }

@media (max-width: 767.98px){
  .users-head{ flex-direction:column; }
  .users-actions{ width:100%; justify-content:flex-start; }
  .users-actions .btn{ flex:1 1 calc(50% - 8px); text-align:center; }
}

/* ✅ Acciones tabla más amigables en móvil (PC igual) */
.table-actions{ display:flex; gap:6px; flex-wrap:wrap; }
.table-actions .btn{ white-space:nowrap; }

/* ✅ Cards móvil */
.user-card{
  border:1px solid #eee;
  border-radius:12px;
  background:#fff;
  padding:12px;
  margin-bottom:10px;
}
.user-card .u-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
}
.user-card .u-name{
  font-weight:700;
  margin:0;
  line-height:1.15;
}
.user-card .u-user{
  font-size:12px;
  color:#6b7280;
}
.user-card .u-meta{
  margin-top:8px;
  font-size:13px;
}
.user-card .u-meta div{ margin-bottom:4px; }
.user-card .u-actions{
  margin-top:10px;
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}
.user-card .u-actions .btn{
  flex:1 1 calc(50% - 8px);
}
.user-card .u-actions .btn.btn-block-full{
  flex:1 1 100%;
}

</style>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <!-- ✅ Header: título arriba, acciones abajo (móvil) -->
            <div class="users-head mb-3">
              <div class="users-title">
                <h4 class="page-title mb-0">Usuarios</h4>
                <small class="text-muted">Gestión de accesos del sistema</small>
              </div>

              <div class="users-actions">
  <?php if ($isMobile): ?>
    <a href="#listadoUsuarios" class="btn btn-outline-secondary btn-sm">
      <i class="la la-list mr-1"></i> Ver listado
    </a>
  <?php else: ?>
    <a href="javascript:history.back();" class="btn btn-outline-primary btn-sm">
      <i class="la la-arrow-left mr-1"></i> Volver
    </a>
  <?php endif; ?>
</div>
</div>

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
                <!-- FORMULARIO NUEVO USUARIO -->
                <div class="col-md-5" id="nuevoUsuario">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Nuevo usuario</h5>
                            <p class="card-category">Crea un usuario del sistema</p>
                        </div>
                        <div class="card-body">
                            <form method="post"
                                  action="<?php echo url('/users/usuarios_guardar.php'); ?>"
                                  autocomplete="off">

                                <div class="form-group">
                                    <label>Nombre completo *</label>
<input type="text"
       name="nombre"
       class="form-control"
       required
       placeholder="Nombre completo *"
       autocomplete="off">
</div>

                                <div class="form-group">
                                    <label>Email (opcional)</label>
<input type="email"
       name="email"
       class="form-control"
       placeholder="Email (opcional)"
       autocomplete="off">
</div>

                                <div class="form-group">
                                    <label>Usuario *</label>
<input type="text"
       name="usuario"
       class="form-control"
       required
       placeholder="Usuario *"
       autocomplete="new-username">
</div>

                                <div class="form-group">
                                    <label>Contraseña *</label>
<input type="password"
       name="password"
       class="form-control"
       required
       placeholder="Contraseña *"
       autocomplete="new-password">
</div>

                                <div class="form-group">
                                    <label>Rol *</label>
<select name="rol" class="form-control" required>
    <option value="" disabled selected>Rol *</option>
    <option value="ADMIN">Administrador</option>
    <option value="TECNICO">Técnico</option>
    <option value="RECEPCION">Recepción</option>
</select>
</div>

                                <div class="form-group form-check">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="activo"
                                           name="activo"
                                           checked>
                                    <label class="form-check-label" for="activo">Usuario activo</label>
                                </div>

                                <button type="submit" class="btn btn-primary btn-block">
                                    Guardar usuario
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- LISTADO DE USUARIOS -->
                <div class="col-md-7" id="listadoUsuarios">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Listado de usuarios</h5>
                        </div>
                        <div class="card-body">

                            <!-- ✅ PC: tabla normal -->
                            <?php if (!$isMobile): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Usuario</th>
                                            <th>Email</th>
                                            <th>Rol</th>
                                            <th>Activo</th>
                                            <th>Creado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($lista_usuarios as $u): ?>
                                            <?php
                                                $emailMostrar = trim((string)($u['email'] ?? ''));
                                                $emailMostrar = $emailMostrar !== '' ? $emailMostrar : '—';

                                                $creadoMostrar = '—';
                                                if (!empty($u['creado_en'])) {
                                                    $creadoMostrar = date('d-m-Y H:i', strtotime((string)$u['creado_en']));
                                                }

                                                $uId = (int)$u['id'];
$uRol = strtoupper(trim((string)($u['rol'] ?? '')));
$uIsSuper = ((int)($u['is_super_admin'] ?? 0) === 1) || ($uRol === 'SUPER_ADMIN');
$soyYo = ($uId === (int)($_SESSION['usuario_id'] ?? 0));

$miRol = strtoupper(trim((string)($_SESSION['usuario_rol'] ?? '')));
$miId  = (int)($_SESSION['usuario_id'] ?? 0);

// ⚠️ ID del ADMIN dueño (Yeison) en ESTA BD
$OWNER_ADMIN_ID = 6;

$soyOwnerAdmin   = ($miId === $OWNER_ADMIN_ID);
$esAdminObjetivo = in_array($uRol, ['ADMIN','SUPER_ADMIN'], true) || $uIsSuper;

// Permiso base: no a ti mismo, no a super
$puedoDesactivar = (!$soyYo && !$uIsSuper);

// Si el objetivo es ADMIN => solo SUPER_ADMIN o OwnerAdmin
if ($puedoDesactivar && $esAdminObjetivo) {
    $puedoDesactivar = ($miRol === 'SUPER_ADMIN' || $soyOwnerAdmin);
}
?>
                                            <tr>
<td><?php echo $uId; ?></td>
                                                <td><?php echo htmlspecialchars((string)$u['nombre']); ?></td>
                                                <td><?php echo htmlspecialchars((string)$u['usuario']); ?></td>
                                                <td><?php echo htmlspecialchars($emailMostrar); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars((string)$u['rol']); ?>
                                                    <?php if ($uIsSuper): ?>
                                                        <span class="badge badge-warning ml-1">Super</span>
                                                    <?php endif; ?>
                                                    <?php if ($soyYo): ?>
                                                        <span class="badge badge-info ml-1">Tú</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ((int)$u['activo'] === 1): ?>
                                                        <span class="badge badge-success">Sí</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($creadoMostrar); ?></td>
                                                <td>
                                                    <div class="table-actions">
                                                      <a href="<?php echo url('/users/usuarios_editar.php?id=' . $uId); ?>"
                                                         class="btn btn-sm btn-primary">
                                                          Editar
                                                      </a>

                                                      <?php if ((int)$u['activo'] === 1): ?>
    <?php if ($puedoDesactivar): ?>
        <form method="post"
              action="<?php echo url('/users/usuarios_eliminar.php'); ?>"
              style="display:inline-block; margin:0;"
              onsubmit="return confirm('¿Seguro que deseas desactivar este usuario?');">
            <input type="hidden" name="id" value="<?php echo $uId; ?>">
            <button type="submit" class="btn btn-sm btn-danger">
                Desactivar
            </button>
        </form>
    <?php endif; ?>
<?php else: ?>
    <?php if ($puedoDesactivar): ?>
        <form method="post"
              action="<?php echo url('/users/usuarios_activar.php'); ?>"
              style="display:inline-block; margin:0;"
              onsubmit="return confirm('¿Seguro que deseas activar este usuario?');">
            <input type="hidden" name="id" value="<?php echo $uId; ?>">
            <button type="submit" class="btn btn-sm btn-success">
                Activar
            </button>
        </form>
    <?php endif; ?>
<?php endif; ?>
</div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>

                                        <?php if (empty($lista_usuarios)): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">
                                                    No hay usuarios registrados.
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endif; ?>

                            <!-- ✅ MÓVIL: cards (sin scroll horizontal) -->
                            <?php if ($isMobile): ?>
                                <?php if (empty($lista_usuarios)): ?>
                                    <div class="text-center text-muted">No hay usuarios registrados.</div>
                                <?php else: ?>
                                    <?php foreach ($lista_usuarios as $u): ?>
                                        <?php
                                            $emailMostrar = trim((string)($u['email'] ?? ''));
                                            $emailMostrar = $emailMostrar !== '' ? $emailMostrar : '—';

                                            $creadoMostrar = '—';
                                            if (!empty($u['creado_en'])) {
                                                $creadoMostrar = date('d-m-Y H:i', strtotime((string)$u['creado_en']));
                                            }

                                            $uId = (int)$u['id'];
$uRol = strtoupper(trim((string)($u['rol'] ?? '')));
$uIsSuper = ((int)($u['is_super_admin'] ?? 0) === 1) || ($uRol === 'SUPER_ADMIN');
$soyYo = ($uId === (int)($_SESSION['usuario_id'] ?? 0));

$miRol = strtoupper(trim((string)($_SESSION['usuario_rol'] ?? '')));
$miId  = (int)($_SESSION['usuario_id'] ?? 0);

// ⚠️ ID del ADMIN dueño (Yeison) en ESTA BD
$OWNER_ADMIN_ID = 6;

$soyOwnerAdmin   = ($miId === $OWNER_ADMIN_ID);
$esAdminObjetivo = in_array($uRol, ['ADMIN','SUPER_ADMIN'], true) || $uIsSuper;

// Permiso base: no a ti mismo, no a super
$puedoDesactivar = (!$soyYo && !$uIsSuper);

// Si el objetivo es ADMIN => solo SUPER_ADMIN o OwnerAdmin
if ($puedoDesactivar && $esAdminObjetivo) {
    $puedoDesactivar = ($miRol === 'SUPER_ADMIN' || $soyOwnerAdmin);
}
?>

                                        <div class="user-card">
<div class="u-top">
                                                <div>
                                                    <p class="u-name"><?php echo htmlspecialchars((string)$u['nombre']); ?></p>
                                                    <div class="u-user">@<?php echo htmlspecialchars((string)$u['usuario']); ?></div>
                                                </div>
                                                <div>
                                                    <?php if ($uIsSuper): ?>
                                                        <span class="badge badge-warning">Super</span>
                                                    <?php endif; ?>
                                                    <?php if ($soyYo): ?>
                                                        <span class="badge badge-info">Tú</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>

                                            <div class="u-meta">
                                                <div><strong>ID:</strong> <?php echo $uId; ?></div>
                                                <div><strong>Email:</strong> <?php echo htmlspecialchars($emailMostrar); ?></div>
                                                <div>
                                                    <strong>Rol:</strong> <?php echo htmlspecialchars($uRol); ?>
                                                </div>
                                                <div>
                                                    <strong>Activo:</strong>
                                                    <?php if ((int)$u['activo'] === 1): ?>
                                                        <span class="badge badge-success">Sí</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary">No</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div><strong>Creado:</strong> <?php echo htmlspecialchars($creadoMostrar); ?></div>
                                            </div>

                                            <div class="u-actions">
                                                <a href="<?php echo url('/users/usuarios_editar.php?id=' . $uId); ?>"
                                                   class="btn btn-primary btn-sm">
                                                    Editar
                                                </a>

                                                <?php if (!empty($puedoDesactivar) && $puedoDesactivar): ?>

    <?php if ((int)$u['activo'] === 1): ?>
        <form method="post"
              action="<?php echo url('/users/usuarios_eliminar.php'); ?>"
              style="margin:0; flex:1 1 calc(50% - 8px);"
              onsubmit="return confirm('¿Seguro que deseas desactivar este usuario?');">
            <input type="hidden" name="id" value="<?php echo $uId; ?>">
            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                Desactivar
            </button>
        </form>
    <?php else: ?>
        <form method="post"
              action="<?php echo url('/users/usuarios_activar.php'); ?>"
              style="margin:0; flex:1 1 calc(50% - 8px);"
              onsubmit="return confirm('¿Seguro que deseas activar este usuario?');">
            <input type="hidden" name="id" value="<?php echo $uId; ?>">
            <button type="submit" class="btn btn-success btn-sm" style="width:100%;">
                Activar
            </button>
        </form>
    <?php endif; ?>

<?php else: ?>
    <button class="btn btn-outline-secondary btn-sm" disabled>
        <?php echo ((int)$u['activo'] === 1) ? 'Desactivar' : 'Activar'; ?>
    </button>
<?php endif; ?>
</div>
                                        </div>

                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endif; ?>

                            </div>
                    </div>
                </div>

            </div><!-- row -->
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
