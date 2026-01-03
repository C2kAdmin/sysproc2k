<?php
declare(strict_types=1);

// superadmin/systec_creator/clientes.php

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

// Toggle activo (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'], $_POST['toggle_to'])) {

    if (!sa_csrf_ok((string)($_POST['csrf'] ?? ''))) {
        sa_flash_set('clientes', 'CSRF inválido. Recarga y vuelve a intentar.', 'danger');
        header('Location: ' . sa_url('/clientes.php'));
        exit;
    }

    $id = (int)$_POST['toggle_id'];
    $to = (int)$_POST['toggle_to'];

    try {
        $pdo = sa_pdo();
        $st = $pdo->prepare("UPDATE systec_clientes SET activo = :a WHERE id = :id");
        $st->execute([':a' => $to, ':id' => $id]);
        sa_flash_set('clientes', 'Estado actualizado.', 'success');
    } catch (Exception $e) {
        sa_flash_set('clientes', 'No se pudo actualizar el estado.', 'danger');
    }

    header('Location: ' . sa_url('/clientes.php'));
    exit;
}

// 2️⃣ Traer clientes desde BD master
$clientes = [];
try {
    $pdo = sa_pdo();
    $st = $pdo->query("SELECT * FROM systec_clientes ORDER BY created_at DESC, id DESC");
    $clientes = $st->fetchAll() ?: [];
} catch (Exception $e) {
    $clientes = [];
    sa_flash_set('clientes', 'Error al leer BD master. Revisa credenciales en _config/config.php', 'danger');
}

$flash = sa_flash_get('clientes');

// 3️⃣ Layout propio
require_once __DIR__ . '/_layout/header.php';
require_once __DIR__ . '/_layout/sidebar.php';
?>

<div class="sa-main">
  <div class="sa-top">
    <strong>SysTec Creator</strong>
    <div class="text-muted small">Listado de clientes (BD master)</div>
  </div>

  <div class="sa-content">

    <div class="d-flex align-items-center justify-content-between mb-3">
      <h4 class="mb-0">Clientes</h4>
      <a class="btn btn-sm btn-primary" href="<?php echo sa_url('/cliente_crear.php'); ?>">+ Crear cliente</a>
    </div>

    <?php if ($flash): ?>
      <div class="alert alert-<?php echo htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>">
        <?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body p-0">
        <table class="table table-sm mb-0">
          <thead class="thead-light">
            <tr>
              <th>ID</th>
              <th>Slug</th>
              <th>Nombre</th>
              <th>Core</th>
              <th>Activo</th>
              <th>URL</th>
              <th>FS</th>
              <th class="text-right">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($clientes)): ?>
              <tr>
                <td colspan="8" class="p-3 text-muted">Sin clientes registrados todavía.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($clientes as $c): ?>
                <?php
                  $instanceOk = !empty($c['instance_path']) && is_file((string)$c['instance_path']);
                  $storageOk  = !empty($c['storage_path'])  && is_dir((string)$c['storage_path']);
                  $editUrl    = sa_url('/cliente_editar.php?id=' . (int)$c['id']);
                  $delUrl     = sa_url('/cliente_eliminar.php?id=' . (int)$c['id']);
                ?>
                <tr>
                  <td><?php echo (int)$c['id']; ?></td>
                  <td><code><?php echo htmlspecialchars((string)$c['slug'], ENT_QUOTES, 'UTF-8'); ?></code></td>
                  <td><?php echo htmlspecialchars((string)($c['nombre_comercial'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td><?php echo htmlspecialchars((string)($c['core_version'] ?? 'v1.1'), ENT_QUOTES, 'UTF-8'); ?></td>
                  <td>
                    <?php if ((int)$c['activo'] === 1): ?>
                      <span class="badge badge-success">Sí</span>
                    <?php else: ?>
                      <span class="badge badge-secondary">No</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($c['base_url_public'])): ?>
                      <a href="<?php echo htmlspecialchars((string)$c['base_url_public'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Abrir</a>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if ($instanceOk && $storageOk): ?>
                      <span class="badge badge-success">OK</span>
                    <?php else: ?>
                      <span class="badge badge-warning">Revisar</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-right">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars($editUrl, ENT_QUOTES, 'UTF-8'); ?>">Editar</a>

                    <a class="btn btn-sm btn-outline-danger" href="<?php echo htmlspecialchars($delUrl, ENT_QUOTES, 'UTF-8'); ?>">
                      Eliminar
                    </a>

                    <form method="post" style="display:inline-block;">
                      <input type="hidden" name="csrf" value="<?php echo htmlspecialchars(sa_csrf_get(), ENT_QUOTES, 'UTF-8'); ?>">
                      <input type="hidden" name="toggle_id" value="<?php echo (int)$c['id']; ?>">
                      <input type="hidden" name="toggle_to" value="<?php echo ((int)$c['activo'] === 1) ? 0 : 1; ?>">
                      <button class="btn btn-sm btn-outline-secondary" type="submit">
                        <?php echo ((int)$c['activo'] === 1) ? 'Desactivar' : 'Activar'; ?>
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

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
