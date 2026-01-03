<?php
// systec/_cores/systec/v1.2/notices/notices_admin.php
require_once __DIR__ . '/../config/auth.php';

require_role(['ADMIN', 'SUPER_ADMIN']);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
  try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
  catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex((string)mt_rand()) . bin2hex((string)mt_rand()); }
}
$CSRF = (string)$_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function to_mysql_datetime($val){
  $val = trim((string)$val);
  if ($val === '') return null;                 // NULL => mostrar inmediato
  // datetime-local viene como 2026-01-02T15:49
  $val = str_replace('T', ' ', $val);
  if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $val)) return $val . ':00';
  if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $val)) return $val;
  return null; // si viene raro, lo tratamos como NULL
}

$ok  = '';
$err = '';

// ------------------------
// POST actions
// ------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $csrfPost = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($CSRF, $csrfPost)) {
    $err = 'CSRF inválido. Recarga la página e intenta nuevamente.';
  } else {

    $action = trim((string)($_POST['action'] ?? ''));

    try {

      if ($action === 'create') {

        $titulo    = trim((string)($_POST['titulo'] ?? ''));
        $contenido = trim((string)($_POST['contenido'] ?? ''));
        $prioridad = (int)($_POST['prioridad'] ?? 0);
        $activo    = isset($_POST['activo']) ? 1 : 0;
        $starts_at = to_mysql_datetime($_POST['starts_at'] ?? '');

        if ($titulo === '' || mb_strlen($titulo) < 3) throw new Exception('Título inválido.');
        if ($contenido === '' || mb_strlen($contenido) < 3) throw new Exception('Contenido inválido.');

        $stmt = $pdo->prepare("
          INSERT INTO notices (titulo, contenido, prioridad, activo, starts_at, created_at)
          VALUES (:t, :c, :p, :a, :s, NOW())
        ");
        $stmt->execute([
          ':t' => $titulo,
          ':c' => $contenido,
          ':p' => $prioridad,
          ':a' => $activo,
          ':s' => $starts_at
        ]);

        header('Location: ' . url('/notices/notices_admin.php?ok=1'));
        exit;
      }

      if ($action === 'update') {

        $id        = (int)($_POST['id'] ?? 0);
        $titulo    = trim((string)($_POST['titulo'] ?? ''));
        $contenido = trim((string)($_POST['contenido'] ?? ''));
        $prioridad = (int)($_POST['prioridad'] ?? 0);
        $activo    = isset($_POST['activo']) ? 1 : 0;
        $starts_at = to_mysql_datetime($_POST['starts_at'] ?? '');

        if ($id <= 0) throw new Exception('ID inválido.');
        if ($titulo === '' || mb_strlen($titulo) < 3) throw new Exception('Título inválido.');
        if ($contenido === '' || mb_strlen($contenido) < 3) throw new Exception('Contenido inválido.');

        $stmt = $pdo->prepare("
          UPDATE notices
          SET titulo = :t, contenido = :c, prioridad = :p, activo = :a, starts_at = :s
          WHERE id = :id
          LIMIT 1
        ");
        $stmt->execute([
          ':t'  => $titulo,
          ':c'  => $contenido,
          ':p'  => $prioridad,
          ':a'  => $activo,
          ':s'  => $starts_at,
          ':id' => $id
        ]);

        header('Location: ' . url('/notices/notices_admin.php?ok=2'));
        exit;
      }

      if ($action === 'toggle') {

        $id     = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        if ($id <= 0) throw new Exception('ID inválido.');

        $stmt = $pdo->prepare("UPDATE notices SET activo = :a WHERE id = :id LIMIT 1");
        $stmt->execute([':a' => $activo, ':id' => $id]);

        header('Location: ' . url('/notices/notices_admin.php?ok=3'));
        exit;
      }

      throw new Exception('Acción inválida.');


    } catch (Exception $e) {
      $err = $e->getMessage();
    }
  }
}

if (isset($_GET['ok'])) {
  $ok = 'Cambios guardados ✅';
}

// ------------------------
// Load notices
// ------------------------
$notices = [];
try {
  $stmt = $pdo->query("
    SELECT id, titulo, contenido, prioridad, activo, starts_at, created_at
    FROM notices
    ORDER BY prioridad DESC, id DESC
  ");
  $notices = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $err = $err ?: 'Error consultando notices. Revisa tabla/columnas.';
}

$editId = (int)($_GET['edit'] ?? 0);
$edit = null;
if ($editId > 0) {
  foreach ($notices as $n) {
    if ((int)$n['id'] === $editId) { $edit = $n; break; }
  }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 class="mb-0">Noticias / Avisos</h4>
          <small class="text-muted">Publica mensajes que verán los usuarios al entrar</small>
        </div>

        <a class="btn btn-outline-primary btn-sm" href="<?php echo url('/notices/notices_admin.php'); ?>">
          Nueva
        </a>
      </div>

      <?php if ($ok): ?>
        <div class="alert alert-success"><?php echo h($ok); ?></div>
      <?php endif; ?>

      <?php if ($err): ?>
        <div class="alert alert-danger"><?php echo h($err); ?></div>
      <?php endif; ?>

      <div class="card mb-3">
        <div class="card-header">
          <strong><?php echo $edit ? 'Editar aviso' : 'Crear aviso'; ?></strong>
        </div>
        <div class="card-body">
          <form method="post" action="<?php echo url('/notices/notices_admin.php'); ?><?php echo $edit ? '?edit='.(int)$edit['id'] : ''; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo h($CSRF); ?>">
            <input type="hidden" name="action" value="<?php echo $edit ? 'update' : 'create'; ?>">
            <?php if ($edit): ?>
              <input type="hidden" name="id" value="<?php echo (int)$edit['id']; ?>">
            <?php endif; ?>

            <div class="form-group">
              <label>Título</label>
              <input class="form-control" name="titulo" maxlength="180" required
                     value="<?php echo h($edit['titulo'] ?? ''); ?>">
            </div>

            <div class="form-group">
              <label>Contenido</label>
              <textarea class="form-control" name="contenido" rows="5" required><?php echo h($edit['contenido'] ?? ''); ?></textarea>
              <small class="text-muted">Tip: se mostrará como texto plano (sin HTML).</small>
            </div>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>Prioridad</label>
                <input type="number" class="form-control" name="prioridad"
                       value="<?php echo (int)($edit['prioridad'] ?? 0); ?>">
              </div>

              <div class="form-group col-md-5">
                <label>Mostrar desde</label>
                <?php
                  $valStarts = '';
                  if (!empty($edit['starts_at'])) {
                    $dt = (string)$edit['starts_at']; // 2026-01-02 15:49:00
                    $dt = str_replace(' ', 'T', substr($dt, 0, 16)); // datetime-local
                    $valStarts = $dt;
                  }
                ?>
                <input type="datetime-local" class="form-control" name="starts_at"
                       value="<?php echo h($valStarts); ?>">
                <small class="text-muted">Vacío = mostrar inmediato.</small>
              </div>

              <div class="form-group col-md-3 d-flex align-items-center" style="padding-top:28px;">
                <?php $isActive = (int)($edit['activo'] ?? 1) === 1; ?>
                <label class="mb-0">
                  <input type="checkbox" name="activo" <?php echo $isActive ? 'checked' : ''; ?>>
                  Activo
                </label>
              </div>
            </div>

            <button class="btn btn-primary">
              <?php echo $edit ? 'Guardar cambios' : 'Publicar'; ?>
            </button>

            <?php if ($edit): ?>
              <a class="btn btn-outline-secondary" href="<?php echo url('/notices/notices_admin.php'); ?>">
                Cancelar
              </a>
            <?php endif; ?>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <strong>Avisos existentes</strong>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th style="width:80px;">ID</th>
                  <th>Título</th>
                  <th style="width:110px;">Prioridad</th>
                  <th style="width:190px;">Desde</th>
                  <th style="width:120px;">Activo</th>
                  <th style="width:220px;">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$notices): ?>
                  <tr><td colspan="6" class="text-center p-3 text-muted">Sin avisos aún</td></tr>
                <?php else: ?>
                  <?php foreach ($notices as $n): ?>
                    <tr>
                      <td><?php echo (int)$n['id']; ?></td>
                      <td>
                        <strong><?php echo h($n['titulo']); ?></strong>
                        <div class="text-muted small" style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:520px;">
                          <?php echo h($n['contenido']); ?>
                        </div>
                      </td>
                      <td><?php echo (int)$n['prioridad']; ?></td>
                      <td><?php echo !empty($n['starts_at']) ? h($n['starts_at']) : '<span class="text-muted">—</span>'; ?></td>
                      <td>
                        <?php if ((int)$n['activo'] === 1): ?>
                          <span class="badge badge-success">Sí</span>
                        <?php else: ?>
                          <span class="badge badge-secondary">No</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <a class="btn btn-sm btn-outline-primary"
                           href="<?php echo url('/notices/notices_admin.php?edit='.(int)$n['id']); ?>">
                          Editar
                        </a>

                        <form method="post" action="<?php echo url('/notices/notices_admin.php'); ?>" style="display:inline-block;">
                          <input type="hidden" name="csrf_token" value="<?php echo h($CSRF); ?>">
                          <input type="hidden" name="action" value="toggle">
                          <input type="hidden" name="id" value="<?php echo (int)$n['id']; ?>">
                          <input type="hidden" name="activo" value="<?php echo ((int)$n['activo'] === 1) ? 0 : 1; ?>">
                          <button class="btn btn-sm btn-outline-secondary">
                            <?php echo ((int)$n['activo'] === 1) ? 'Desactivar' : 'Activar'; ?>
                          </button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
