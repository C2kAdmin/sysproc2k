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

function sa_rrmdir(string $dir): bool
{
    if (!is_dir($dir)) return true;

    try {
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }

        return @rmdir($dir);
    } catch (Throwable $e) {
        return false;
    }
}

function sa_safe_path_under(string $path, string $root): bool
{
    $rp = realpath($path);
    $rr = realpath($root);
    if (!$rp || !$rr) return false;
    $rr = rtrim(str_replace('\\','/',$rr), '/') . '/';
    $rp = str_replace('\\','/',$rp);
    return strpos($rp . (is_dir($rp) ? '/' : ''), $rr) === 0;
}

$errors = [];
$client = null;

// 1) Cargar cliente por ID
$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    $errors[] = 'ID inválido.';
} else {
    try {
        $pdo = sa_pdo();
        $st = $pdo->prepare("SELECT * FROM systec_clientes WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $client = $st->fetch() ?: null;
        if (!$client) $errors[] = 'Cliente no encontrado en BD master.';
    } catch (Exception $e) {
        $errors[] = 'No se pudo leer BD master.';
    }
}

// 2) POST: actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && $client) {

    if (!sa_csrf_ok((string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'CSRF inválido. Recarga y vuelve a intentar.';
    } else {

        // Campos editables
        $nombre_comercial = trim((string)($_POST['nombre_comercial'] ?? ''));
        $core_version_in  = trim((string)($_POST['core_version'] ?? 'v1.2'));
        $core_version     = in_array($core_version_in, ['v1.1','v1.2'], true) ? $core_version_in : 'v1.2';
        $activo           = isset($_POST['activo']) ? 1 : 0;

        // DB creds editables (por si cambian en hosting)
        $db_host = 'localhost';
        $db_name = trim((string)($_POST['db_name'] ?? ''));
        $db_user = trim((string)($_POST['db_user'] ?? ''));
        $db_pass = (string)($_POST['db_pass'] ?? '');

        if ($db_name === '' || $db_user === '' || $db_pass === '') {
            $errors[] = 'DB name/user/pass son obligatorios.';
        }

        // Recalcular URL pública (siempre automática)
        $slug = (string)($client['slug'] ?? '');
        $https  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((string)($_SERVER['SERVER_PORT'] ?? '') === '443');
        $scheme = $https ? 'https://' : 'http://';
        $host   = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost');

        $script = str_replace('\\','/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
        $pos = strpos($script, '/superadmin/systec_creator');
        $sysproWeb = ($pos !== false) ? substr($script, 0, $pos) : rtrim(dirname($script), '/');
        $sysproWeb = rtrim($sysproWeb, '/');

        $pubPath = $sysproWeb . '/systec/_clients/' . $slug . '/tec/public/';
        $base_url_public = $scheme . $host . $pubPath;

        // Guardar
        if (empty($errors)) {
            try {
                $pdo = sa_pdo();

                $st = $pdo->prepare("
                    UPDATE systec_clientes
                    SET
                      nombre_comercial = :nom,
                      activo = :act,
                      core_version = :core,
                      base_url_public = :url,
                      db_host = :h,
                      db_name = :dn,
                      db_user = :du,
                      db_pass = :dp,
                      updated_at = NOW()
                    WHERE id = :id
                    LIMIT 1
                ");

                $st->execute([
                    ':nom'  => $nombre_comercial,
                    ':act'  => $activo,
                    ':core' => $core_version,
                    ':url'  => $base_url_public,
                    ':h'    => $db_host,
                    ':dn'   => $db_name,
                    ':du'   => $db_user,
                    ':dp'   => $db_pass,
                    ':id'   => (int)$client['id'],
                ]);

                sa_flash_set('clientes', 'Cliente actualizado: ' . $slug, 'success');
                header('Location: ' . sa_url('/clientes.php'));
                exit;

            } catch (Exception $e) {
                $errors[] = 'No se pudo actualizar el registro en BD master.';
            }
        }
    }
}

// Layout
require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/sidebar.php';
?>

<div class="sa-main">
  <div class="sa-top">
    <strong>SysTec Creator</strong>
    <div class="text-muted small">Editar cliente (BD master)</div>
  </div>

  <div class="sa-content">

    <h4 class="mb-3">Editar cliente</h4>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul class="mb-0">
          <?php foreach ($errors as $e): ?>
            <li><?php echo htmlspecialchars((string)$e, ENT_QUOTES, 'UTF-8'); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <?php if ($client): ?>
      <?php
        $slug = (string)($client['slug'] ?? '');

        // Valores actuales para el form
        $nombre_comercial = (string)($client['nombre_comercial'] ?? '');
        $core_version     = (string)($client['core_version'] ?? 'v1.2');
        $activo           = (int)($client['activo'] ?? 1);

        $db_name = (string)($client['db_name'] ?? '');
        $db_user = (string)($client['db_user'] ?? '');
        $db_pass = (string)($client['db_pass'] ?? '');

        $instance_path = (string)($client['instance_path'] ?? '');
        $storage_path  = (string)($client['storage_path'] ?? '');
        $base_url_public = (string)($client['base_url_public'] ?? '');

        $clientsRoot = (defined('SYSTEC_CLIENTS_ROOT') && SYSTEC_CLIENTS_ROOT) ? (string)SYSTEC_CLIENTS_ROOT : '';
        $clientRoot  = ($clientsRoot !== '' ? ($clientsRoot . '/' . $slug) : '');

        $fsOk = true;
        if ($clientsRoot && $clientRoot && is_dir($clientRoot)) {
            $fsOk = sa_safe_path_under($clientRoot, $clientsRoot);
        }
      ?>

      <div class="card mb-3">
        <div class="card-body">
          <div><strong>Slug:</strong> <code><?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></code></div>
          <div class="small text-muted mt-1">
            Instance: <code><?php echo htmlspecialchars($instance_path, ENT_QUOTES, 'UTF-8'); ?></code><br>
            Storage: <code><?php echo htmlspecialchars($storage_path, ENT_QUOTES, 'UTF-8'); ?></code><br>
            URL: <code><?php echo htmlspecialchars($base_url_public, ENT_QUOTES, 'UTF-8'); ?></code>
          </div>

          <?php if (!$fsOk): ?>
            <div class="alert alert-warning mt-3 mb-0">
              Ruta FS no segura (fuera de _clients). No se permite operación de carpetas.
            </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-body">

          <form method="post" autocomplete="off">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(sa_csrf_get(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$client['id']; ?>">

            <div class="form-row">
              <div class="form-group col-md-8">
                <label>Nombre comercial</label>
                <input type="text" name="nombre_comercial" class="form-control"
                       value="<?php echo htmlspecialchars($nombre_comercial, ENT_QUOTES, 'UTF-8'); ?>">
              </div>
              <div class="form-group col-md-2">
                <label>Versión core</label>
                <select name="core_version" class="form-control">
                  <option value="v1.1" <?php echo ($core_version==='v1.1')?'selected':''; ?>>v1.1</option>
                  <option value="v1.2" <?php echo ($core_version==='v1.2')?'selected':''; ?>>v1.2</option>
                </select>
              </div>
              <div class="form-group col-md-2">
                <label>Activo</label>
                <div class="form-control" style="height:auto;">
                  <label class="mb-0">
                    <input type="checkbox" name="activo" value="1" <?php echo ($activo===1)?'checked':''; ?>>
                    Sí
                  </label>
                </div>
              </div>
            </div>

            <hr>

            <h5 class="mb-2">Credenciales DB (cliente)</h5>

            <div class="form-row">
              <div class="form-group col-md-4">
                <label>DB Host</label>
                <input type="text" class="form-control" value="localhost" disabled>
              </div>
              <div class="form-group col-md-4">
                <label>DB Name *</label>
                <input type="text" name="db_name" class="form-control"
                       value="<?php echo htmlspecialchars($db_name, ENT_QUOTES, 'UTF-8'); ?>" required>
              </div>
              <div class="form-group col-md-4">
                <label>DB User *</label>
                <input type="text" name="db_user" class="form-control"
                       value="<?php echo htmlspecialchars($db_user, ENT_QUOTES, 'UTF-8'); ?>" required autocomplete="new-password">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>DB Pass *</label>
                <input type="password" name="db_pass" class="form-control" value="" required autocomplete="new-password">
                <small class="text-muted">Por seguridad no pre-cargamos la contraseña. Reingrésala para guardar.</small>
              </div>
              <div class="form-group col-md-6">
                <label>DB Pass actual (solo referencia)</label>
                <input type="text" class="form-control" value="<?php echo htmlspecialchars($db_pass, ENT_QUOTES, 'UTF-8'); ?>" readonly>
              </div>
            </div>

            <button class="btn btn-primary" type="submit">Guardar cambios</button>
            <a class="btn btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Volver</a>

          </form>

          <hr>

          <h5 class="mb-2">Reparar estructura de carpetas (opcional)</h5>
          <div class="text-muted small mb-2">
            Esto solo sirve si el cliente quedó “a medias” y quieres limpiar y recrear carpeta completa.
          </div>

          <?php if ($fsOk && $clientRoot && is_dir($clientRoot)): ?>
            <form method="post" action="<?php echo sa_url('/cliente_eliminar.php?id=' . (int)$client['id']); ?>">
              <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(sa_csrf_get(), ENT_QUOTES, 'UTF-8'); ?>">
              <input type="hidden" name="id" value="<?php echo (int)$client['id']; ?>">
              <input type="hidden" name="confirm_slug" value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>">

              <input type="hidden" name="delete_fs" value="1">
              <input type="hidden" name="wipe_db" value="0">
              <input type="hidden" name="drop_db" value="0">
              <input type="hidden" name="delete_master" value="0">

              <button class="btn btn-outline-danger" type="submit"
                onclick="return confirm('Esto borrará SOLO la carpeta del cliente (no DB, no master). ¿Seguro?');">
                Borrar carpeta del cliente (solo FS)
              </button>
            </form>
          <?php else: ?>
            <div class="alert alert-light mb-0">
              No se detectó carpeta del cliente o la ruta no es segura.
            </div>
          <?php endif; ?>

        </div>
      </div>

    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
