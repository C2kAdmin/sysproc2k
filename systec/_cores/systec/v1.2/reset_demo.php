<?php
// reset_demo.php

require_once __DIR__ . '/config/auth.php';

// ✅ SOLO SUPER_ADMIN puede usar esto (bloquea ADMIN / TÉCNICO / RECEPCIÓN)
require_super_admin();

$mensaje_error = '';

/**
 * ✅ Recomendado: mover esta clave a tabla parametros (ej: clave_reset_demo)
 * Si no existe, cae al default.
 */
function getParametroLocal($clave, $default = '')
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :clave LIMIT 1");
    $stmt->execute([':clave' => $clave]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);
    return ($fila && $fila['valor'] !== '') ? $fila['valor'] : $default;
}

$CLAVE_RESET = getParametroLocal('clave_reset_demo', '112233Kdoki.');

// (Opcional) Anti doble submit básico
if (!isset($_SESSION['anti_repost_reset'])) {
    $_SESSION['anti_repost_reset'] = bin2hex(random_bytes(16));
}
$antiToken = $_SESSION['anti_repost_reset'];

// Si envía el formulario, hacemos el reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formToken = $_POST['_anti_token'] ?? '';
    if ($formToken === '' || !hash_equals($antiToken, $formToken)) {
        $mensaje_error = 'El formulario ya fue enviado o expiró. Recarga la página.';
    } else {

        // Rotamos token para bloquear refresh
        $_SESSION['anti_repost_reset'] = bin2hex(random_bytes(16));
        $antiToken = $_SESSION['anti_repost_reset'];

        $confirm = $_POST['confirm'] ?? '';
        $clave   = $_POST['clave'] ?? '';

        if ($confirm === 'SI' && hash_equals((string)$CLAVE_RESET, (string)$clave)) {

            try {
                // Desactivar claves foráneas (solo mientras truncamos)
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

                // ✅ Orden correcto por FK (si alguna tabla no existe, se ignora)
                try { $pdo->exec('TRUNCATE TABLE ordenes_evidencias'); } catch (Exception $e) {}
                try { $pdo->exec('TRUNCATE TABLE ordenes_estados'); } catch (Exception $e) {}
                try { $pdo->exec('TRUNCATE TABLE ordenes'); } catch (Exception $e) {}

            } catch (Exception $e) {
                $mensaje_error = 'Error al intentar resetear los datos de demo: ' . $e->getMessage();
            } finally {
                // ✅ Pase lo que pase, reactivamos FK checks
                try { $pdo->exec('SET FOREIGN_KEY_CHECKS = 1'); } catch (Exception $e) {}
            }

            if ($mensaje_error === '') {
                header('Location: ' . url('/dashboard.php?reset_demo=1'));
                exit;
            }

        } else {
            $mensaje_error = '❌ Clave incorrecta. No se realizó el reset.';
        }
    }
}

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Reset de datos de demostración</h4>

            <?php if ($mensaje_error !== ''): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($mensaje_error); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title">Borrar órdenes y estados</h4>
                    <p class="card-category">
                        Esta acción eliminará <strong>todas las órdenes</strong>, el
                        <strong>historial de estados</strong> y las <strong>evidencias</strong>.<br>
                        Los usuarios, parámetros y mensajes de WhatsApp se mantienen.
                    </p>
                </div>
                <div class="card-body">

                    <div class="alert alert-warning">
                        <strong>Atención:</strong> Esta acción es irreversible.
                        Úsala solo para limpiar el sistema antes o después de una demostración.
                    </div>

                    <form method="post" action="<?php echo url('/reset_demo.php'); ?>"
                          onsubmit="return confirm('¿Seguro que quieres borrar TODAS las órdenes, estados y evidencias?');">

                        <input type="hidden" name="_anti_token" value="<?php echo htmlspecialchars($antiToken); ?>">
                        <input type="hidden" name="confirm" value="SI">

                        <div class="form-group">
                            <label>Clave de reset</label>
                            <input type="password" name="clave" class="form-control" required placeholder="Ingresa la clave de reset">
                            <small class="form-text text-muted">
                                Sugerencia: guarda esta clave en <code>parametros.clave_reset_demo</code> para no dejarla hardcodeada.
                            </small>
                        </div>

                        <button type="submit" class="btn btn-danger">
                            Sí, borrar todas las órdenes, estados y evidencias
                        </button>

                        <a href="<?php echo url('/dashboard.php'); ?>" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
