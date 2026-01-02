<?php
declare(strict_types=1);

require_once __DIR__ . '/_config/config.php';

if (isset($_SESSION['super_admin']) && $_SESSION['super_admin'] === true) {
    header('Location: ' . sa_url('/clientes.php'));
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(sa_post('email'));
    $pass  = (string)($_POST['pass'] ?? '');

    if ($email === '' || $pass === '') {
        $error = 'Completa email y contraseña.';
    } else {
        try {
            $pdo = sa_pdo();
            $stmt = $pdo->prepare("SELECT id, email, pass_hash, role, activo FROM superadmin_users WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $u = $stmt->fetch();

            if (!$u || (int)$u['activo'] !== 1 || !password_verify($pass, (string)$u['pass_hash'])) {
                $error = 'Credenciales inválidas.';
            } else {
                $_SESSION['super_admin']   = true;
                $_SESSION['sa_user_id']    = (int)$u['id'];
                $_SESSION['sa_user_email'] = (string)$u['email'];
                $_SESSION['sa_user_role']  = (string)($u['role'] ?? '');

                header('Location: ' . sa_url('/clientes.php'));
                exit;
            }
        } catch (Exception $e) {
            $error = 'Error al conectar a BD master. Revisa credenciales en _config/config.php';
        }
    }
}

require_once __DIR__ . '/_layout/header.php';
?>

<div class="sa-main">
  <div class="sa-top">
    <strong>SysTec Creator</strong>
    <div class="text-muted small">Login SuperAdmin</div>
  </div>

  <div class="sa-content">
    <div class="container" style="max-width:460px;">
      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">Ingresar</h5>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <div class="form-group">
              <label>Email</label>
              <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars((string)($email ?? ''), ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
              <label>Contraseña</label>
              <input type="password" name="pass" class="form-control" required>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Entrar</button>
          </form>

          <div class="text-muted small mt-3">
            Si no existe usuario aún, créalo en la BD master (tabla <code>superadmin_users</code>).
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
