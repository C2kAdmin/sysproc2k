<?php
// login.php

// Cargamos config.php, que ya incluye session_config.php
require_once __DIR__ . '/config/config.php';

$mensaje_error = '';

// Si ya está logueado, lo mandamos al dashboard
if (isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Datos ingresados
    $login    = trim($_POST['login_input'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $mensaje_error = 'Debe ingresar usuario y contraseña.';
    } else {

        // Buscar por usuario o email
        $stmt = $pdo->prepare("
    SELECT *
    FROM usuarios
    WHERE usuario = ?
       OR email   = ?
    LIMIT 1
");
$stmt->execute([$login, $login]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && !empty($user['password_hash']) && password_verify($password, $user['password_hash'])) {

            // Guardamos datos mínimos en la sesión
            $_SESSION['usuario_id']     = (int)$user['id'];
            $_SESSION['usuario_nombre'] = (string)$user['nombre'];
            $_SESSION['usuario_rol']    = (string)$user['rol'];

            header('Location: ' . url('/dashboard.php'));
            exit;

        } else {
            $mensaje_error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>SysTec <?php echo SYSTEC_VERSION; ?>
 - Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- ✅ Importante en multi-cliente: siempre con url() -->
    <link rel="stylesheet" href="<?php echo url('/assets/css/bootstrap.min.css'); ?>">

    <style>
        body { background: #f5f5f5; }
        .login-container {
            max-width: 420px;
            margin: 80px auto;
            background: #ffffff;
            border-radius: 6px;
            padding: 24px 28px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .login-title { text-align: center; margin-bottom: 20px; }

        @media (max-width: 576px) {
            .login-container {
                margin: 40px auto;
                padding: 24px 18px;
                max-width: 95%;
                box-shadow: none;
                border-radius: 0;
            }
            .login-title { font-size: 24px; margin-bottom: 16px; }
            .login-container label,
            .login-container input,
            .login-container button { font-size: 16px; }
            .login-container input { padding: 10px 12px; }
            .login-container button.btn-primary { width: 100%; padding: 12px 0; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="login-container">

        <h3 class="login-title">SysTec <?php echo SYSTEC_VERSION; ?>
</h3>

        <?php if ($mensaje_error !== ''): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($mensaje_error); ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo url('/login.php'); ?>" autocomplete="off">

            <!-- Campos trampa ocultos para que el navegador intente autocompletar aquí -->
            <input type="text" name="fakeusernameremembered" style="display:none">
            <input type="password" name="fakepasswordremembered" style="display:none">

            <div class="form-group">
                <label for="login_input">Usuario o email</label>
                <input type="text"
                       class="form-control"
                       id="login_input"
                       name="login_input"
                       autocomplete="off"
                       required>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password"
                       class="form-control"
                       id="password"
                       name="password"
                       autocomplete="new-password"
                       required>
            </div>

            <button type="submit" class="btn btn-primary btn-block mt-3">
                Iniciar sesión
            </button>
        </form>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var userInput = document.getElementById('login_input');
    if (userInput) {
        userInput.value = '';
        userInput.focus();
    }
});
</script>

</body>
</html>
