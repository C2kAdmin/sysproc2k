<?php
declare(strict_types=1);

// superadmin/systec_creator/cliente_editar.php

require_once __DIR__ . '/_config/config.php';
require_once __DIR__ . '/_config/auth.php';

require_super_admin();

/* =========================
   CSRF (local, autocontenido)
   ========================= */
if (!function_exists('sa_csrf_get')) {
    function sa_csrf_get(): string
    {
        if (empty($_SESSION['sa_csrf'])) {
            $_SESSION['sa_csrf'] = bin2hex(random_bytes(32));
        }
        return (string)$_SESSION['sa_csrf'];
    }
}
if (!function_exists('sa_csrf_ok')) {
    function sa_csrf_ok(?string $token): bool
    {
        $sess = (string)($_SESSION['sa_csrf'] ?? '');
        $tok  = (string)($token ?? '');
        return $sess !== '' && $tok !== '' && hash_equals($sess, $tok);
    }
}

$errors = [];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    sa_flash_set('clientes', 'ID inválido.', 'danger');
    header('Location: ' . sa_url('/clientes.php'));
    exit;
}

// Cargar cliente
try {
    $pdo = sa_pdo();
    $st = $pdo->prepare("SELECT * FROM systec_clientes WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $cliente = $st->fetch();
    if (!$cliente) {
        sa_flash_set('clientes', 'Cliente no encontrado.', 'danger');
        header('Location: ' . sa_url('/clientes.php'));
        exit;
    }
} catch (Exception $e) {
    sa_flash_set('clientes', 'Error al leer BD master.', 'danger');
    header('Location: ' . sa_url('/clientes.php'));
    exit;
}

// Defaults desde BD
$slug             = (string)($cliente['slug'] ?? '');
$nombre_comercial = (string)($cliente['nombre_comercial'] ?? '');
$activo           = (int)($cliente['activo'] ?? 0);
$core_version     = (string)($cliente['core_version'] ?? 'v1.2');
$base_url_public  = (string)($cliente['base_url_public'] ?? '');

$db_host          = (string)($cliente['db_host'] ?? 'localhost');
$db_name          = (string)($cliente['db_name'] ?? '');
$db_user          = (string)($cliente['db_user'] ?? '');
$db_pass_mask     = '********'; // no mostramos el pass real

$instance_path    = (string)($cliente['instance_path'] ?? '');
$storage_path     = (string)($cliente['storage_path'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!sa_csrf_ok((string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'CSRF inválido. Recarga y vuelve a intentar.';
    }

    $nombre_comercial = sa_post('nombre_comercial');
    $core_version     = sa_post('core_version', $core_version);
    $base_url_public  = sa_post('base_url_public', $base_url_public);

    $db_host          = sa_post('db_host', $db_host);
    $db_name          = sa_post('db_name', $db_name);
    $db_user          = sa_post('db_user', $db_user);
    $db_pass_new      = (string)($_POST['db_pass'] ?? '');

    $activo           = isset($_POST['activo']) ? 1 : 0;

    if ($db_host === '' || $db_name === '' || $db_user === '') {
        $errors[] = 'DB host/name/user son obligatorios.';
    }

    // Validar core_version exista físicamente
    $corePath = SYSTEC_ROOT ? (SYSTEC_ROOT . '/_cores/systec/' . $core_version) : '';
    if (!$corePath || !is_dir($corePath) || !is_file($corePath . '/router.php')) {
        $errors[] = 'core_version no existe en el servidor (' . htmlspecialchars($core_version, ENT_QUOTES, 'UTF-8') . ').';
    }

    // Si base_url_public viene vacío => recalcular automático
    if (trim((string)$base_url_public) === '') {
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https://' : 'http://';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

        $script = str_replace('\\','/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $pos = strpos($script, '/superadmin/systec_creator');
        $sysproWeb = ($pos !== false) ? substr($script, 0, $pos) : rtrim(dirname($script), '/');
        $sysproWeb = rtrim($sysproWeb, '/');

        $pubPath = $sysproWeb . '/systec/_clients/' . $slug . '/tec/public/';
        $base_url_public = $scheme . $host . $pubPath;
    }

    if (empty($errors)) {
        try {
            $pdo = sa_pdo();

            // Si no viene pass nuevo, mantenemos el existente
            $db_pass_final = $db_pass_new !== '' ? $db_pass_new : (string)($cliente['db_pass'] ?? '');

            $up = $pdo->prepare("UPDATE systec_clientes SET
                    nombre_comercial = :nom,
                    activo = :act,
                    core_version = :core,
                    base_url_public = :url,
                    db_host = :h,
                    db_name = :dn,
                    db_user = :du,
                    db_pass = :dp
                WHERE id = :id
                LIMIT 1");

            $up->execute([
                ':nom'  => $nombre_comercial,
                ':act'  => $activo,
                ':core' => $core_version,
                ':url'  => $base_url_public,
                ':h'    => $db_host,
                ':dn'   => $db_name,
                ':du'   => $db_user,
                ':dp'   => $db_pass_final,
                ':id'   => $id,
            ]);

            sa_flash_set('clientes', 'Cliente actualizado: ' . $slug, 'success');
            header('Location: ' . sa_url('/clientes.php'));
            exit;

        } catch (Exception $e) {
            $errors[] = 'No se pudo actualizar en BD master.';
        }
    }
}

require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/sidebar.php';
?>

<div class="sa-main">
  <div class="sa-top">
    <strong>SysTec Creator</strong>
    <div class="text-muted small">Editar cliente</div>
  </div>

  <div class="sa-content">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0">Editar: <code><?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></code></h4>
      <a class="btn btn-sm btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Volver</a>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="card mb-3">
      <div class="card-body">
        <div class="text-muted small mb-2">Rutas (solo lectura)</div>
        <div><strong>instance_path:</strong> <code><?php echo htmlspecialchars($instance_path, ENT_QUOTES, 'UTF-8'); ?></code></div>
        <div><strong>storage_path:</strong> <code><?php echo htmlspecialchars($storage_path, ENT_QUOTES, 'UTF-8'); ?></code></div>
      </div>
    </div>

    <div class="card">
      <div class="card-body">

        <form method="post" autocomplete="off">
          <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(sa_csrf_get(), ENT_QUOTES, 'UTF-8'); ?>">

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Slug</label>
              <input type="text" class="form-control" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>" disabled>
              <small class="text-muted">no editable (impacta rutas)</small>
            </div>
            <div class="form-group col-md-8">
              <label>Nombre comercial</label>
              <input type="text" name="nombre_comercial" class="form-control" value="<?php echo htmlspecialchars($nombre_comercial, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Core version</label>
              <select name="core_version" class="form-control">
                <option value="v1.1" <?php echo ($core_version==='v1.1')?'selected':''; ?>>v1.1</option>
                <option value="v1.2" <?php echo ($core_version==='v1.2')?'selected':''; ?>>v1.2</option>
              </select>
            </div>
            <div class="form-group col-md-8">
              <label>Base URL pública (vacío = auto)</label>
              <input type="text" name="base_url_public" class="form-control" value="<?php echo htmlspecialchars($base_url_public, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3">
              <label>DB Host *</label>
              <input type="text" name="db_host" class="form-control" value="<?php echo htmlspecialchars($db_host, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB Name *</label>
              <input type="text" name="db_name" class="form-control" value="<?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB User *</label>
              <input type="text" name="db_user" class="form-control" value="<?php echo htmlspecialchars($db_user, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group col-md-3">
              <label>DB Pass (dejar vacío = mantener)</label>
              <input type="password" name="db_pass" class="form-control" value="">
              <small class="text-muted">actual: <?php echo htmlspecialchars($db_pass_mask, ENT_QUOTES, 'UTF-8'); ?></small>
            </div>
          </div>

          <div class="form-group">
            <label>
              <input type="checkbox" name="activo" value="1" <?php echo ($activo===1)?'checked':''; ?>>
              Cliente activo
            </label>
          </div>

          <button class="btn btn-primary" type="submit">Guardar cambios</button>
          <a class="btn btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Cancelar</a>

        </form>

      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
