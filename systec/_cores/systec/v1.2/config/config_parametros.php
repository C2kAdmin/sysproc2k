<?php
// config/config_parametros.php

require_once __DIR__ . '/../config/auth.php';

// ✅ SOLO ADMIN (SUPER_ADMIN siempre pasa)
require_role(['ADMIN']);

/**
 * Helpers para leer / guardar parámetros
 */
function getParametro($clave, $default = '')
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :clave LIMIT 1");
    $stmt->execute([':clave' => $clave]);
    $fila = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($fila && $fila['valor'] !== '') {
        return $fila['valor'];
    }
    return $default;
}

function setParametro($clave, $valor)
{
    global $pdo;
    $stmt = $pdo->prepare("
        INSERT INTO parametros (clave, valor)
        VALUES (:clave, :valor)
        ON DUPLICATE KEY UPDATE valor = VALUES(valor)
    ");
    $stmt->execute([
        ':clave' => $clave,
        ':valor' => $valor,
    ]);
}

$mensaje_ok    = '';
$mensaje_error = '';

// Si viene POST, guardamos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        setParametro('nombre_negocio', trim($_POST['nombre_negocio'] ?? ''));
        setParametro('direccion', trim($_POST['direccion'] ?? ''));
        setParametro('telefono', trim($_POST['telefono'] ?? ''));
        setParametro('whatsapp', trim($_POST['whatsapp'] ?? ''));
        setParametro('email', trim($_POST['email'] ?? ''));
        setParametro('pie_orden', trim($_POST['pie_orden'] ?? ''));
        setParametro('horario_taller', trim($_POST['horario_taller'] ?? ''));

        // ------- Subida de logo (opcional) -------
        if (
            isset($_FILES['logo']) &&
            $_FILES['logo']['error'] === UPLOAD_ERR_OK &&
            !empty($_FILES['logo']['tmp_name'])
        ) {
            $tmpPath = $_FILES['logo']['tmp_name'];

            // Validar que sea imagen
            $info = @getimagesize($tmpPath);
            if ($info !== false) {

                $mime = $info['mime'];
                $ext  = 'png';
                if ($mime === 'image/jpeg') $ext = 'jpg';
                if ($mime === 'image/png')  $ext = 'png';

                // Carpeta destino (CORE)
                $destDir = dirname(__DIR__) . '/assets/img';
                if (!is_dir($destDir)) {
                    mkdir($destDir, 0775, true);
                }

                // Nombre fijo
                $destRel = 'assets/img/logo_negocio.' . $ext;
                $destAbs = dirname(__DIR__) . '/' . $destRel;

                if (move_uploaded_file($tmpPath, $destAbs)) {
                    setParametro('logo_ruta', $destRel);
                }
            }
        }

        $mensaje_ok = 'Parámetros guardados correctamente.';
    } catch (Throwable $e) {
        $mensaje_error = 'Error al guardar los parámetros. Intente nuevamente.';
    }
}

// Cargar valores actuales
$val_nombre_negocio = getParametro('nombre_negocio', 'ServiTec');
$val_direccion      = getParametro('direccion', '');
$val_telefono       = getParametro('telefono', '');
$val_whatsapp       = getParametro('whatsapp', '');
$val_email          = getParametro('email', '');
$val_pie_orden      = getParametro('pie_orden', 'Gracias por confiar en nosotros.');
$val_horario_taller = getParametro('horario_taller', '');
$val_logo_ruta      = getParametro('logo_ruta', '');

$APP = defined('APP_URL') ? APP_URL : '';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Parámetros del sistema</h4>

            <?php if ($mensaje_ok): ?>
                <div class="alert alert-success">
                    <?php echo htmlspecialchars($mensaje_ok); ?>
                </div>
            <?php endif; ?>

            <?php if ($mensaje_error): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($mensaje_error); ?>
                </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-8">

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Datos generales</h5>
                            <p class="card-category">
                                Información que se usará en órdenes, mensajes y otros módulos.
                            </p>
                        </div>

                        <div class="card-body">

                            <form method="post"
                                  action="<?php echo htmlspecialchars(url('/config/config_parametros.php')); ?>"
                                  enctype="multipart/form-data">

                                <div class="form-group">
                                    <label>Nombre del negocio *</label>
                                    <input type="text"
                                           name="nombre_negocio"
                                           class="form-control"
                                           required
                                           value="<?php echo htmlspecialchars($val_nombre_negocio); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text"
                                           name="direccion"
                                           class="form-control"
                                           value="<?php echo htmlspecialchars($val_direccion); ?>">
                                </div>

                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label>Teléfono fijo</label>
                                        <input type="text"
                                               name="telefono"
                                               class="form-control"
                                               value="<?php echo htmlspecialchars($val_telefono); ?>">
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>WhatsApp</label>
                                        <input type="text"
                                               name="whatsapp"
                                               class="form-control"
                                               value="<?php echo htmlspecialchars($val_whatsapp); ?>">
                                    </div>

                                    <div class="form-group col-md-4">
                                        <label>Email</label>
                                        <input type="email"
                                               name="email"
                                               class="form-control"
                                               value="<?php echo htmlspecialchars($val_email); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Horario del taller</label>
                                    <input type="text"
                                           name="horario_taller"
                                           class="form-control"
                                           value="<?php echo htmlspecialchars($val_horario_taller); ?>">
                                </div>

                                <div class="form-group">
                                    <label>Pie de página</label>
                                    <textarea name="pie_orden"
                                              class="form-control"
                                              rows="3"><?php echo htmlspecialchars($val_pie_orden); ?></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Logo del negocio</label><br>

                                    <?php if ($val_logo_ruta): ?>
                                        <img src="<?php echo htmlspecialchars($APP . '/' . $val_logo_ruta); ?>"
                                             style="max-height:60px;" class="mb-2">
                                    <?php endif; ?>

                                    <input type="file" name="logo" class="form-control-file">
                                </div>

                                <button type="submit" class="btn btn-primary">
                                    Guardar parámetros
                                </button>

                            </form>

                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
