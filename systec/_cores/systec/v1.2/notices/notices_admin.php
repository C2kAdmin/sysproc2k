<?php
// notices/notices_admin.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN siempre pasa)
require_role(['ADMIN']);

// Flash
$mensaje_ok    = $_SESSION['flash_ok']    ?? '';
$mensaje_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_error']);

// CSRF simple
if (empty($_SESSION['csrf_token'])) {
    try { $_SESSION['csrf_token'] = bin2hex(random_bytes(24)); }
    catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex((string)microtime(true)); }
}
$CSRF = (string)$_SESSION['csrf_token'];

function notices_dt_to_sql(?string $dtLocal): ?string {
    $dtLocal = trim((string)$dtLocal);
    if ($dtLocal === '') return null;

    // Espera "YYYY-MM-DDTHH:MM" (datetime-local)
    $dtLocal = str_replace('T', ' ', $dtLocal);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $dtLocal)) {
        return null;
    }
    return $dtLocal . ':00';
}

function redirect_self(string $qs = ''): void {
    $to = url('/notices/notices_admin.php');
    if ($qs !== '') $to .= $qs;
    header('Location: ' . $to);
    exit;
}

// =========================
// POST: acciones
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($CSRF, $csrf)) {
        $_SESSION['flash_error'] = 'CSRF inválido. Recarga la página e intenta nuevamente.';
        redirect_self();
    }

    $action = trim((string)($_POST['action'] ?? ''));

    try {

        // ---------
        // GUARDAR (crear/editar)
        // ---------
        if ($action === 'save') {

            $id        = (int)($_POST['id'] ?? 0);
            $titulo    = trim((string)($_POST['titulo'] ?? ''));
            $contenido = trim((string)($_POST['contenido'] ?? ''));
            $prioridad = (int)($_POST['prioridad'] ?? 0);
            $activo    = isset($_POST['activo']) ? 1 : 0;

            $starts_at = notices_dt_to_sql((string)($_POST['starts_at'] ?? ''));

            if ($titulo === '') {
                throw new Exception('El título es obligatorio.');
            }
            if ($contenido === '') {
                throw new Exception('El contenido es obligatorio.');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE notices
                    SET titulo = :t,
                        contenido = :c,
                        activo = :a,
                        prioridad = :p,
                        starts_at = :s
                    WHERE id = :id
                    LIMIT 1
                ");
                $stmt->execute([
                    ':t'  => $titulo,
                    ':c'  => $contenido,
                    ':a'  => $activo,
                    ':p'  => $prioridad,
                    ':s'  => $starts_at,
                    ':id' => $id
                ]);

                $_SESSION['flash_ok'] = "Aviso #{$id} actualizado ✅";
                redirect_self('?edit=' . $id);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO notices (titulo, contenido, activo, prioridad, starts_at)
                    VALUES (:t, :c, :a, :p, :s)
                ");
                $stmt->execute([
                    ':t' => $titulo,
                    ':c' => $contenido,
                    ':a' => $activo,
                    ':p' => $prioridad,
                    ':s' => $starts_at
                ]);

                $newId = (int)$pdo->lastInsertId();
                $_SESSION['flash_ok'] = "Aviso #{$newId} creado ✅";
                redirect_self('?edit=' . $newId);
            }
        }

        // ---------
        // TOGGLE ACTIVO
        // ---------
        if ($action === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');

            $stmt = $pdo->prepare("
                UPDATE notices
                SET activo = CASE WHEN COALESCE(activo,0)=1 THEN 0 ELSE 1 END
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute([':id' => $id]);

            $_SESSION['flash_ok'] = "Estado de aviso #{$id} actualizado ✅";
            redirect_self();
        }

        $_SESSION['flash_error'] = 'Acción no válida.';
        redirect_self();

    } catch (Exception $e) {
        $_SESSION['flash_error'] = $e->getMessage();
        redirect_self(isset($_GET['edit']) ? ('?edit='.(int)$_GET['edit']) : '');
    }
}

// =========================
// GET: cargar edición + listado
// =========================
$editId = (int)($_GET['edit'] ?? 0);
$edit = null;

if ($editId > 0) {
    $stmt = $pdo->prepare("SELECT id, titulo, contenido, activo, prioridad, starts_at FROM notices WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $editId]);
    $edit = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$edit) {
        $editId = 0;
        $edit = null;
        $mensaje_error = $mensaje_error ?: 'Aviso no encontrado.';
    }
}

// Listado
$stmt = $pdo->prepare("SELECT id, titulo, activo, prioridad, starts_at FROM notices ORDER BY prioridad DESC, id DESC");
$stmt->execute();
$lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Móvil/PC
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);

// UI
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Form: starts_at datetime-local
$startsLocal = '';
if (!empty($edit['starts_at'])) {
    $tmp = (string)$edit['starts_at']; // "YYYY-MM-DD HH:MM:SS"
    if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $tmp)) {
        $startsLocal = str_replace(' ', 'T', substr($tmp, 0, 16)); // "YYYY-MM-DDTHH:MM"
    }
}
?>
<style>
.notice-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.notice-title h4{ margin:0; }
.notice-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
@media (max-width: 767.98px){
  .notice-head{ flex-direction:column; }
  .notice-actions{ width:100%; justify-content:flex-start; }
  .notice-actions .btn{ flex:1 1 calc(50% - 8px); text-align:center; }
}
.notice-card{
  border:1px solid #eee;
  border-radius:12px;
  background:#fff;
  padding:12px;
  margin-bottom:10px;
}
.notice-card .n-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
}
.notice-card .n-title{ font-weight:700; margin:0; line-height:1.15; }
.notice-card .n-meta{ margin-top:8px; font-size:13px; color:#6b7280; }
.notice-card .n-actions{ margin-top:10px; display:flex; flex-wrap:wrap; gap:8px; }
.notice-card .n-actions .btn{ flex:1 1 calc(50% - 8px); }
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <div class="notice-head mb-3">
        <div class="notice-title">
          <h4 class="page-title mb-0">Noticias / Avisos</h4>
          <small class="text-muted">Publica mensajes que verán los usuarios al entrar</small>
        </div>

        <div class="notice-actions">
          <a class="btn btn-outline-secondary btn-sm" href="<?php echo url('/notices/notices_admin.php'); ?>">
            Nueva
          </a>
          <?php if ($isMobile): ?>
            <a class="btn btn-outline-primary btn-sm" href="#listadoNotices">Ver listado</a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($mensaje_ok): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensaje_ok); ?></div>
      <?php endif; ?>
      <?php if ($mensaje_error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($mensaje_error); ?></div>
      <?php endif; ?>

      <!-- FORM -->
      <div class="card mb-3" style="border-radius:14px;">
        <div class="card-header">
          <strong><?php echo $edit ? ('Editar aviso #'.(int)$edit['id']) : 'Crear aviso'; ?></strong>
        </div>
        <div class="card-body">

          <form method="post" action="<?php echo url('/notices/notices_admin.php') . ($edit ? ('?edit='.(int)$edit['id']) : ''); ?>">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?php echo $edit ? (int)$edit['id'] : 0; ?>">

            <div class="form-group">
              <label>Título</label>
              <input class="form-control" name="titulo" maxlength="180" required
                     value="<?php echo htmlspecialchars((string)($edit['titulo'] ?? '')); ?>">
            </div>

            <div class="form-group">
              <label>Contenido</label>
              <textarea class="form-control" name="contenido" rows="5" required><?php
                echo htmlspecialchars((string)($edit['contenido'] ?? ''));
              ?></textarea>
              <small class="text-muted">Tip: lo mostramos como texto plano (sin HTML) para evitar sorpresas.</small>
            </div>

            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Prioridad</label>
                <input type="number" class="form-control" name="prioridad"
                       value="<?php echo (int)($edit['prioridad'] ?? 0); ?>">
              </div>

              <div class="form-group col-md-5">
                <label>Mostrar desde</label>
                <input type="datetime-local" class="form-control" name="starts_at"
                       value="<?php echo htmlspecialchars($startsLocal, ENT_QUOTES, 'UTF-8'); ?>">
                <small class="text-muted">Vacío = aparece al tiro (si está activo).</small>
              </div>

              <div class="form-group col-md-4 d-flex align-items-center" style="gap:10px; padding-top:28px;">
                <div class="custom-control custom-checkbox">
                  <input type="checkbox" class="custom-control-input" id="activo_chk" name="activo"
                         <?php echo ((int)($edit['activo'] ?? 1) === 1) ? 'checked' : ''; ?>>
                  <label class="custom-control-label" for="activo_chk">Activo</label>
                </div>
              </div>
            </div>

            <div class="d-flex" style="gap:8px; flex-wrap:wrap;">
              <button class="btn btn-primary" type="submit">
                <?php echo $edit ? 'Guardar cambios' : 'Crear aviso'; ?>
              </button>

              <?php if ($edit): ?>
                <a class="btn btn-outline-secondary" href="<?php echo url('/notices/notices_admin.php'); ?>">
                  Cancelar
                </a>
              <?php endif; ?>
            </div>

          </form>
        </div>
      </div>

      <!-- LISTADO -->
      <div id="listadoNotices"></div>

      <?php if ($isMobile): ?>

        <?php foreach ($lista as $n): ?>
          <div class="notice-card">
            <div class="n-top">
              <div>
                <p class="n-title mb-0">
                  #<?php echo (int)$n['id']; ?> · <?php echo htmlspecialchars((string)$n['titulo']); ?>
                </p>
                <div class="n-meta">
                  Prioridad: <strong><?php echo (int)($n['prioridad'] ?? 0); ?></strong> ·
                  Desde: <strong><?php echo $n['starts_at'] ? htmlspecialchars((string)$n['starts_at']) : 'inmediato'; ?></strong> ·
                  Estado:
                  <?php if ((int)($n['activo'] ?? 0) === 1): ?>
                    <span class="badge badge-success">Activo</span>
                  <?php else: ?>
                    <span class="badge badge-secondary">Inactivo</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <div class="n-actions">
              <a class="btn btn-outline-primary btn-sm"
                 href="<?php echo url('/notices/notices_admin.php?edit='.(int)$n['id']); ?>">
                Editar
              </a>

              <form method="post" action="<?php echo url('/notices/notices_admin.php'); ?>" style="margin:0;">
                <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                <button class="btn btn-sm <?php echo ((int)($n['activo'] ?? 0) === 1) ? 'btn-warning' : 'btn-success'; ?>">
                  <?php echo ((int)($n['activo'] ?? 0) === 1) ? 'Desactivar' : 'Activar'; ?>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

      <?php else: ?>

        <div class="card" style="border-radius:14px;">
          <div class="card-header">
            <strong>Listado de avisos</strong>
          </div>
          <div class="card-body table-responsive">

            <table class="table table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Título</th>
                  <th>Prioridad</th>
                  <th>Desde</th>
                  <th>Estado</th>
                  <th style="width:220px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($lista as $n): ?>
                  <tr>
                    <td>#<?php echo (int)$n['id']; ?></td>
                    <td><?php echo htmlspecialchars((string)$n['titulo']); ?></td>
                    <td><?php echo (int)($n['prioridad'] ?? 0); ?></td>
                    <td><?php echo $n['starts_at'] ? htmlspecialchars((string)$n['starts_at']) : 'inmediato'; ?></td>
                    <td>
                      <?php if ((int)($n['activo'] ?? 0) === 1): ?>
                        <span class="badge badge-success">Activo</span>
                      <?php else: ?>
                        <span class="badge badge-secondary">Inactivo</span>
                      <?php endif; ?>
                    </td>
                    <td style="display:flex; gap:8px; flex-wrap:wrap;">
                      <a class="btn btn-outline-primary btn-sm"
                         href="<?php echo url('/notices/notices_admin.php?edit='.(int)$n['id']); ?>">
                        Editar
                      </a>

                      <form method="post" action="<?php echo url('/notices/notices_admin.php'); ?>" style="margin:0;">
                        <input type="hidden" name="csrf" value="<?php echo htmlspecialchars($CSRF, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                        <button class="btn btn-sm <?php echo ((int)($n['activo'] ?? 0) === 1) ? 'btn-warning' : 'btn-success'; ?>">
                          <?php echo ((int)($n['activo'] ?? 0) === 1) ? 'Desactivar' : 'Activar'; ?>
                        </button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

          </div>
        </div>

      <?php endif; ?>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
