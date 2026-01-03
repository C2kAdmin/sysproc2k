<?php
declare(strict_types=1);

require_once __DIR__ . '/_config/config.php';

$error = '';
$ok    = '';

$username = '';
$email    = '';

try {
    $pdo = sa_pdo();
    $count = (int)$pdo->query("SELECT COUNT(*) FROM superadmin_users")->fetchColumn();
} catch (Exception $e) {
    $count = -1;
    $error = 'No se pudo conectar a la BD. Revisa _config/config.php';
}

if ($count > 0) {
    require_once __DIR__ . '/_layout/header.php';
    ?>
    <div class="sa-main">
      <div class="sa-top">
        <strong>SysTec Creator</strong>
        <div class="text-muted small">Seed SuperAdmin</div>
      </div>
      <div class="sa-content">
        <div class="alert alert-warning mb-0">
          Seed deshabilitado: ya existe al menos 1 usuario en <code>superadmin_users</code>.
          <br>✅ Puedes borrar este archivo.
        </div>
      </div>
    </div>
    <?php
    require_once __DIR__ . '/_layout/footer.php';
    exit;
}

if ($count === 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = strtolower(trim(sa_post('username')));
    $email    = strtolower(trim(sa_post('email')));
    $pass     = (string)($_POST['pass'] ?? '');

    if ($username === '' || $email === '' || $pass === '') {
        $error = 'Completa username, email y contraseña.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido.';
    } elseif (strlen($pass) < 6) {
        $error = 'Contraseña mínima 6 caracteres.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);

            $st = $pdo->prepare("
                INSERT INTO superadmin_users (username, email, password_hash, activo)
                VALUES (:u, :e, :h, 1)
            ");
            $st->execute([':u' => $username, ':e' => $email, ':h' => $hash]);

            $ok = '✅ SuperAdmin creado. Ahora entra por /login.php y luego BORRA este seed.';
        } catch (Exception $e) {
            $error = 'No se pudo crear el usuario (revisa UNIQUE o estructura).';
        }
    }
}

require_once __DIR__ . '/_layout/header.php';
?>

<div class="sa-main">
  <div class="sa-top">
    <strong>SysTec Creator</strong>
    <div class="text-muted small">Seed SuperAdmin (primer setup)</div>
  </div>

  <div class="sa-content">
    <div class="container" style="max-width:520px;">
      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">Crear primer SuperAdmin</h5>

          <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <?php if ($ok): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($ok, ENT_QUOTES, 'UTF-8'); ?></div>
          <?php endif; ?>

          <form method="post" autocomplete="off">
            <div class="form-group">
              <label>Username</label>
              <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
              <label>Email</label>
              <input type="text" name="email" class="form-control" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="form-group">
              <label>Contraseña</label>
              <input type="password" name="pass" class="form-control" required>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Crear SuperAdmin</button>
            <a class="btn btn-light btn-block" href="<?php echo sa_url('/login.php'); ?>">Volver al login</a>
          </form>

          <div class="text-muted small mt-3">
            ⚠️ Borrar este archivo después del primer setup.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/_layout/footer.php'; ?>
