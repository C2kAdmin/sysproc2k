<?php
/**
 * Cliente directo — Admin de Noticias/Avisos (notices)
 * Ruta: systec/_clients/cliente1/notices/notices_admin.php
 *
 * IMPORTANTE:
 * - Este archivo corre “por fuera” del CORE, pero usa el CORE como motor.
 * - Define SYSTEC_APP_URL apuntando a /tec/public para que los links vuelvan al sistema normal.
 */

declare(strict_types=1);

// =========================
// BOOTSTRAP de instancia (cliente1)
// =========================
$corePath = realpath(__DIR__ . '/../../../_cores/systec/v1.2');
if (!$corePath) {
  http_response_code(500);
  echo 'Error: no se encontró SYSTEC_CORE_PATH.';
  exit;
}
define('SYSTEC_CORE_PATH', $corePath);

$instancePath = realpath(__DIR__ . '/../tec/config/instance.php');
if (!$instancePath) {
  http_response_code(500);
  echo 'Error: no se encontró instance.php del cliente (SYSTEC_INSTANCE_PATH).';
  exit;
}
define('SYSTEC_INSTANCE_PATH', $instancePath);

// APP_URL debe apuntar al “frontend real” del cliente (tec/public)
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$clientBase = preg_replace('~/notices/.*$~', '', $scriptName); // /.../_clients/cliente1
$appUrl     = rtrim((string)$clientBase . '/tec/public', '/');
if ($appUrl === '/') $appUrl = '';
define('SYSTEC_APP_URL', $appUrl);

// CORE_URL (para assets del core)
$docroot = str_replace('\\', '/', rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'));
$coreNorm = str_replace('\\', '/', $corePath);
$coreUrl  = '';
if ($docroot !== '' && strpos($coreNorm, $docroot) === 0) {
  $coreUrl = substr($coreNorm, strlen($docroot));
}
$coreUrl = $coreUrl ? '/' . ltrim($coreUrl, '/') : '';
define('SYSTEC_CORE_URL', $coreUrl);

// =========================
// AUTH + ACL
// =========================
require_once SYSTEC_CORE_PATH . '/config/auth.php';

// Solo ADMIN o SUPER_ADMIN
require_role(['ADMIN', 'SUPER_ADMIN']);

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['csrf_token'])) {
  try { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
  catch (Exception $e) { $_SESSION['csrf_token'] = bin2hex((string)mt_rand()) . bin2hex((string)mt_rand()); }
}
$CSRF = (string)$_SESSION['csrf_token'];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function str_len_safe(string $s): int { return function_exists('mb_strlen') ? (int)mb_strlen($s) : (int)strlen($s); }

function to_mysql_datetime($val){
  $val = trim((string)$val);
  if ($val === '') return null; // NULL => mostrar inmediato
  // datetime-local: 2026-01-02T15:49
  $val = str_replace('T', ' ', $val);
  if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}$/', $val)) return $val . ':00';
  if (preg_match('/^\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2}$/', $val)) return $val;
  return null;
}

$self = $scriptName ?: '';

// =========================
// POST actions
// =========================
$ok  = '';
$err = '';

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

        if ($titulo === '' || str_len_safe($titulo) < 3) throw new Exception('Título inválido.');
        if ($contenido === '' || str_len_safe($contenido) < 3) throw new Exception('Contenido inválido.');

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

        header('Location: ' . $self . '?ok=1');
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
        if ($titulo === '' || str_len_safe($titulo) < 3) throw new Exception('Título inválido.');
        if ($contenido === '' || str_len_safe($contenido) < 3) throw new Exception('Contenido inválido.');

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

        header('Location: ' . $self . '?ok=2');
        exit;
      }

      if ($action === 'toggle') {

        $id     = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        if ($id <= 0) throw new Exception('ID inválido.');

        $stmt = $pdo->prepare("UPDATE notices SET activo = :a WHERE id = :id LIMIT 1");
        $stmt->execute([':a' => $activo, ':id' => $id]);

        header('Location: ' . $self . '?ok=3');
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

// =========================
// Load notices
// =========================
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

// Layout (CORE)
require_once SYSTEC_CORE_PATH . '/includes/header.php';
require_once SYSTEC_CORE_PATH . '/includes/sidebar.php';
?>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 class="mb-0">Noticias / Avisos</h4>
          <small class="text-muted">Publica mensajes que verán los usuarios al entrar</small>
        </div>

        <a class="btn btn-outline-primary btn-sm" href="<?php echo h($self); ?>">
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
          <form method="post" action="<?php echo h($self); ?><?php echo $edit ? '?edit='.(int)$edit['id'] : ''; ?>">
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
              <a class="btn btn-outline-secondary" href="<?php echo h($self); ?>">
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
                           href="<?php echo h($self . '?edit='.(int)$n['id']); ?>">
                          Editar
                        </a>

                        <form method="post" action="<?php echo h($self); ?>" style="display:inline-block;">
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

<?php require_once SYSTEC_CORE_PATH . '/includes/footer.php'; ?>
