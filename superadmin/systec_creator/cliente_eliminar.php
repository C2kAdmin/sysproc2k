<?php
declare(strict_types=1);

// superadmin/systec_creator/cliente_eliminar.php
// Eliminación SEGURA: registro master + (opcional) carpeta FS + (opcional) vaciar tablas DB.

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

function sa_client_pdo_no_db(string $db_host, string $db_user, string $db_pass): PDO
{
    $dsn = "mysql:host={$db_host};charset=utf8mb4";
    return new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function sa_client_pdo(string $db_host, string $db_name, string $db_user, string $db_pass): PDO
{
    $dsn = "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4";
    return new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
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

// 2) POST: ejecutar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors) && $client) {

    if (!sa_csrf_ok((string)($_POST['csrf'] ?? ''))) {
        $errors[] = 'CSRF inválido. Recarga y vuelve a intentar.';
    } else {

        $slug = (string)($client['slug'] ?? '');
        $confirm = trim((string)($_POST['confirm_slug'] ?? ''));

        // checkboxes
        $do_fs   = isset($_POST['delete_fs']) ? 1 : 0;      // borrar carpeta _clients/slug
        $do_wipe = isset($_POST['wipe_db']) ? 1 : 0;        // borrar tablas+views
        $do_drop = isset($_POST['drop_db']) ? 1 : 0;        // DROP DATABASE (si permisos)
        $do_row  = isset($_POST['delete_master']) ? 1 : 0;  // borrar registro en master

        if ($confirm !== $slug) {
            $errors[] = 'Confirmación inválida. Debes escribir exactamente el slug del cliente.';
        }

        if (!$do_fs && !$do_wipe && !$do_drop && !$do_row) {
            $errors[] = 'No seleccionaste ninguna acción.';
        }

        if (empty($errors)) {

            // Datos DB guardados en master
            $db_host = (string)($client['db_host'] ?? 'localhost');
            $db_name = (string)($client['db_name'] ?? '');
            $db_user = (string)($client['db_user'] ?? '');
            $db_pass = (string)($client['db_pass'] ?? '');

            // FS root esperado del cliente (por slug)
            $clientsRoot = (defined('SYSTEC_CLIENTS_ROOT') && SYSTEC_CLIENTS_ROOT) ? (string)SYSTEC_CLIENTS_ROOT : '';
            $clientRoot  = ($clientsRoot !== '' ? ($clientsRoot . '/' . $slug) : '');

            // 2.1) Borrar FS (si corresponde)
            if ($do_fs && $clientRoot !== '') {
                if (!is_dir($clientRoot)) {
                    // ya no existe -> OK
                } else {
                    if (!sa_safe_path_under($clientRoot, $clientsRoot)) {
                        $errors[] = 'Ruta FS no segura (fuera de _clients). Se cancela borrado de carpeta.';
                    } else {
                        if (!sa_rrmdir($clientRoot)) {
                            $errors[] = 'No se pudo borrar la carpeta del cliente (permisos).';
                        }
                    }
                }
            }

            // 2.2) Wipe/Drop DB (si corresponde)
            if (empty($errors) && ($do_wipe || $do_drop)) {

                if ($db_name === '' || $db_user === '' || $db_pass === '') {
                    $errors[] = 'No hay credenciales DB suficientes en el registro master para borrar DB/tablas.';
                } else {
                    try {
                        if ($do_drop) {
                            $pdoNoDb = sa_client_pdo_no_db($db_host, $db_user, $db_pass);
                            $safeDb = '`' . str_replace('`','',$db_name) . '`';
                            $pdoNoDb->exec("DROP DATABASE {$safeDb}");
                        } elseif ($do_wipe) {
                            $pdoClient = sa_client_pdo($db_host, $db_name, $db_user, $db_pass);

                            $pdoClient->exec("SET FOREIGN_KEY_CHECKS=0");

                            // ✅ Borra TABLAS y VIEWS sin romperse por "is a view"
                            try {
                                $objs = $pdoClient->query("
                                    SELECT TABLE_NAME, TABLE_TYPE
                                    FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = DATABASE()
                                ")->fetchAll();

                                foreach ($objs as $o) {
                                    $name = (string)($o['TABLE_NAME'] ?? '');
                                    $type = strtoupper((string)($o['TABLE_TYPE'] ?? 'BASE TABLE'));
                                    if ($name === '') continue;

                                    $safe = '`' . str_replace('`','',$name) . '`';

                                    if ($type === 'VIEW') {
                                        $pdoClient->exec("DROP VIEW IF EXISTS {$safe}");
                                    } else {
                                        $pdoClient->exec("DROP TABLE IF EXISTS {$safe}");
                                    }
                                }
                            } catch (Throwable $e) {
                                // fallback: intenta view y luego table
                                $tables = $pdoClient->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
                                foreach ($tables as $t) {
                                    $tbl = (string)$t[0];
                                    $safe = '`' . str_replace('`','',$tbl) . '`';
                                    try { $pdoClient->exec("DROP VIEW IF EXISTS {$safe}"); } catch (Throwable $e2) {}
                                    $pdoClient->exec("DROP TABLE IF EXISTS {$safe}");
                                }
                            }

                            $pdoClient->exec("SET FOREIGN_KEY_CHECKS=1");
                        }
                    } catch (Exception $e) {
                        $errors[] = 'No se pudo borrar DB/tablas (probable falta de permisos en hosting).';
                    }
                }
            }

            // 2.3) Borrar registro master (al final)
            if (empty($errors) && $do_row) {
                try {
                    $pdo = sa_pdo();
                    $st = $pdo->prepare("DELETE FROM systec_clientes WHERE id = :id LIMIT 1");
                    $st->execute([':id' => (int)$client['id']]);
                } catch (Exception $e) {
                    $errors[] = 'No se pudo borrar el registro en BD master.';
                }
            }

            if (empty($errors)) {
                sa_flash_set('clientes', "Cliente eliminado/limpiado: {$slug}", 'success');
                header('Location: ' . sa_url('/clientes.php'));
                exit;
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
    <div class="text-muted small">Eliminar cliente (seguro)</div>
  </div>

  <div class="sa-content">

    <h4 class="mb-3">Eliminar cliente</h4>

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
      <div class="card">
        <div class="card-body">
          <p class="mb-2">
            Vas a operar sobre el cliente:
            <strong><code><?php echo htmlspecialchars((string)$client['slug'], ENT_QUOTES, 'UTF-8'); ?></code></strong>
          </p>

          <div class="text-muted small mb-3">
            Consejo de vida: esto es “borrar”, no “desactivar” 😅. Confirma bien.
          </div>

          <form method="post">
            <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(sa_csrf_get(), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="id" value="<?php echo (int)$client['id']; ?>">

            <div class="form-group">
              <label>Escribe el slug para confirmar *</label>
              <input type="text" name="confirm_slug" class="form-control" placeholder="Ej: <?php echo htmlspecialchars((string)$client['slug'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>

            <hr>

            <div class="form-group">
              <label class="d-block">Acciones</label>

              <label class="d-block">
                <input type="checkbox" name="delete_master" value="1" checked>
                Eliminar registro en BD master (systec_clientes)
              </label>

              <label class="d-block">
                <input type="checkbox" name="delete_fs" value="1" checked>
                Borrar carpeta en <code>_clients/<?php echo htmlspecialchars((string)$client['slug'], ENT_QUOTES, 'UTF-8'); ?></code>
              </label>

              <label class="d-block">
                <input type="checkbox" name="wipe_db" value="1">
                Borrar TODAS las tablas y vistas de la DB del cliente (DROP)
              </label>

              <label class="d-block text-danger">
                <input type="checkbox" name="drop_db" value="1">
                DROP DATABASE (requiere permisos del hosting)
              </label>

              <div class="text-muted small mt-2">
                Si ya borraste carpeta/tablas a mano, puedes dejar solo “Eliminar registro en BD master”.
              </div>
            </div>

            <button class="btn btn-danger" type="submit">Confirmar eliminación</button>
            <a class="btn btn-light" href="<?php echo sa_url('/clientes.php'); ?>">Cancelar</a>
          </form>

        </div>
      </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
