<?php
// order/orden_editar.php
require_once __DIR__ . '/../config/config.php';

$APP = defined('APP_URL') ? APP_URL : '';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

// 1) ID
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

$mensaje_error = '';

// Anti doble submit
if (!isset($_SESSION['anti_repost_edit'])) {
    $_SESSION['anti_repost_edit'] = bin2hex(random_bytes(16));
}
$antiToken = $_SESSION['anti_repost_edit'];

// 2) Cargar orden
$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Helpers
function chkPost($name) { return isset($_POST[$name]) ? 1 : 0; }
function solo_digitos($txt) { return preg_replace('/\D+/', '', $txt ?? ''); }

// 3) POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formToken = $_POST['_anti_token'] ?? '';
    if ($formToken === '' || !hash_equals($antiToken, $formToken)) {
        $mensaje_error = 'El formulario ya fue enviado o expiró. Recarga la página.';
    } else {

        // regenerar token
        $_SESSION['anti_repost_edit'] = bin2hex(random_bytes(16));
        $antiToken = $_SESSION['anti_repost_edit'];

        $telefono_raw     = trim($_POST['cliente_telefono'] ?? '');
        $telefono_digitos = solo_digitos($telefono_raw);

        if ($telefono_digitos === '' || strlen($telefono_digitos) < 8 || strlen($telefono_digitos) > 9) {
            $mensaje_error = 'Ingresa un teléfono chileno válido sin el +56 (solo números, 8 o 9 dígitos).';
        } else {

            $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
            $motivo_ingreso = trim($_POST['motivo_ingreso'] ?? '');

            if ($cliente_nombre === '' || $motivo_ingreso === '') {
                $mensaje_error = 'Nombre y motivo de ingreso son obligatorios.';
            } else {

                $telefono_guardar = '56' . $telefono_digitos;

                try {
                    $sql = "UPDATE ordenes SET
                                cliente_nombre          = :cliente_nombre,
                                cliente_telefono        = :cliente_telefono,
                                cliente_rut             = :cliente_rut,
                                cliente_email           = :cliente_email,
                                equipo_marca            = :equipo_marca,
                                equipo_modelo           = :equipo_modelo,
                                equipo_imei1            = :equipo_imei1,
                                equipo_imei2            = :equipo_imei2,
                                equipo_clave            = :equipo_clave,
                                motivo_ingreso          = :motivo_ingreso,
                                observaciones_recepcion = :observaciones_recepcion,
                                chk_enciende            = :chk_enciende,
                                chk_error_inicio        = :chk_error_inicio,
                                chk_rayas               = :chk_rayas,
                                chk_manchas             = :chk_manchas,
                                chk_trizaduras          = :chk_trizaduras,
                                chk_lineas              = :chk_lineas,
                                chk_golpes              = :chk_golpes,
                                chk_signos_intervencion = :chk_signos_intervencion,
                                chk_puertos_defectuosos = :chk_puertos_defectuosos,
                                chk_tornillos           = :chk_tornillos,
                                chk_faltan_soportes     = :chk_faltan_soportes,
                                chk_falta_tapa_slot     = :chk_falta_tapa_slot,
                                chk_garantia_fabrica    = :chk_garantia_fabrica,
                                chk_tiene_patron        = :chk_tiene_patron,
                                requiere_firma          = :requiere_firma
                            WHERE id = :id";

                    $stmt2 = $pdo->prepare($sql);
                    $stmt2->execute([
                        ':cliente_nombre'          => $cliente_nombre,
                        ':cliente_telefono'        => $telefono_guardar,
                        ':cliente_rut'             => trim($_POST['cliente_rut']   ?? ''),
                        ':cliente_email'           => trim($_POST['cliente_email'] ?? ''),
                        ':equipo_marca'            => trim($_POST['equipo_marca']  ?? ''),
                        ':equipo_modelo'           => trim($_POST['equipo_modelo'] ?? ''),
                        ':equipo_imei1'            => trim($_POST['equipo_imei1']  ?? ''),
                        ':equipo_imei2'            => trim($_POST['equipo_imei2']  ?? ''),
                        ':equipo_clave'            => trim($_POST['equipo_clave']  ?? ''),
                        ':motivo_ingreso'          => $motivo_ingreso,
                        ':observaciones_recepcion' => trim($_POST['observaciones'] ?? ''),
                        ':chk_enciende'            => chkPost('chk_enciende'),
                        ':chk_error_inicio'        => chkPost('chk_error_inicio'),
                        ':chk_rayas'               => chkPost('chk_rayas'),
                        ':chk_manchas'             => chkPost('chk_manchas'),
                        ':chk_trizaduras'          => chkPost('chk_trizaduras'),
                        ':chk_lineas'              => chkPost('chk_lineas'),
                        ':chk_golpes'              => chkPost('chk_golpes'),
                        ':chk_signos_intervencion' => chkPost('chk_signos_intervencion'),
                        ':chk_puertos_defectuosos' => chkPost('chk_puertos_defectuosos'),
                        ':chk_tornillos'           => chkPost('chk_tornillos'),
                        ':chk_faltan_soportes'     => chkPost('chk_faltan_soportes'),
                        ':chk_falta_tapa_slot'     => chkPost('chk_falta_tapa_slot'),
                        ':chk_garantia_fabrica'    => chkPost('chk_garantia_fabrica'),
                        ':chk_tiene_patron'        => chkPost('chk_tiene_patron'),
                        ':requiere_firma'          => chkPost('requiere_firma'),
                        ':id'                      => $id,
                    ]);

                    header('Location: ' . url('/order/orden_detalle.php?id=' . $id));
                    exit;

                } catch (Throwable $e) {
                    $mensaje_error = 'Error al actualizar la orden. Intente nuevamente.';
                }
            }
        }
    }

    // recargar datos tras error
    $stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $orden = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 4) Teléfono para mostrar (sin 56)
$tel_dig       = solo_digitos($orden['cliente_telefono'] ?? '');
$telefono_edit = (strpos($tel_dig, '56') === 0) ? substr($tel_dig, 2) : $tel_dig;

// UI
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
    .checklist-row {
        font-size: 13px;
    }
    .checklist-row .check-item {
        display: block;
        margin: 0;
        padding: 0;
        line-height: 1.2;
        white-space: nowrap;
    }
    .checklist-row .check-item input[type="checkbox"] {
        margin-right: 4px;
        position: static !important;
        opacity: 1 !important;
    }
    .checklist-title {
        margin-top: 1.25rem;
        margin-bottom: 0.5rem;
    }
    #requiere_firma {
        position: static !important;
        opacity: 1 !important;
        margin-right: 4px;
    }
    .input-group-text.prefix-phone {
        min-width: 3.2rem;
        justify-content: center;
    }
</style>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Editar Orden de Servicio</h4>

            <?php if ($mensaje_error !== ''): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($mensaje_error); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">
                        Orden N° <?php echo str_pad((string)($orden['numero_orden'] ?? $id), 4, '0', STR_PAD_LEFT); ?>
                    </h4>
                    <p class="card-category">
                        Modifique los datos del cliente, equipo o recepción.
                    </p>
                </div>

                <div class="card-body">
                    <form method="post" action="<?php echo htmlspecialchars(url('/order/orden_editar.php?id=' . $id)); ?>">
                        <input type="hidden" name="_anti_token" value="<?php echo htmlspecialchars($antiToken); ?>">

                        <!-- FILA 1 -->
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="mt-3">Datos del Cliente</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">Nombre del cliente *</label>
                                            <input type="text"
                                                   name="cliente_nombre"
                                                   class="form-control"
                                                   placeholder="Nombre del cliente *"
                                                   required
                                                   value="<?php echo htmlspecialchars($orden['cliente_nombre'] ?? ''); ?>">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">Teléfono de contacto *</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text prefix-phone">+56</span>
                                                </div>
                                                <input type="tel"
                                                       name="cliente_telefono"
                                                       class="form-control"
                                                       placeholder="9XXXXXXXX (sin +56) *"
                                                       required
                                                       inputmode="numeric"
                                                       pattern="\d{8,9}"
                                                       title="Ingresa solo números, 8 o 9 dígitos sin el +56"
                                                       value="<?php echo htmlspecialchars($telefono_edit); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">RUT</label>
                                            <input type="text"
                                                   name="cliente_rut"
                                                   class="form-control"
                                                   placeholder="RUT (opcional)"
                                                   value="<?php echo htmlspecialchars($orden['cliente_rut'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">Correo</label>
                                            <input type="email"
                                                   name="cliente_email"
                                                   class="form-control"
                                                   placeholder="Correo (opcional)"
                                                   value="<?php echo htmlspecialchars($orden['cliente_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h5 class="mt-3">Datos del Equipo</h5>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">Marca</label>
                                            <input type="text"
                                                   name="equipo_marca"
                                                   class="form-control"
                                                   placeholder="Marca"
                                                   value="<?php echo htmlspecialchars($orden['equipo_marca'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">Modelo</label>
                                            <input type="text"
                                                   name="equipo_modelo"
                                                   class="form-control"
                                                   placeholder="Modelo"
                                                   value="<?php echo htmlspecialchars($orden['equipo_modelo'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">IMEI 1</label>
                                            <input type="text"
                                                   name="equipo_imei1"
                                                   class="form-control"
                                                   placeholder="IMEI 1"
                                                   value="<?php echo htmlspecialchars($orden['equipo_imei1'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="sr-only">IMEI 2</label>
                                            <input type="text"
                                                   name="equipo_imei2"
                                                   class="form-control"
                                                   placeholder="IMEI 2 (opcional)"
                                                   value="<?php echo htmlspecialchars($orden['equipo_imei2'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="sr-only">Clave / Patrón</label>
                                    <input type="text"
                                           name="equipo_clave"
                                           class="form-control"
                                           placeholder="Clave / Patrón (opcional)"
                                           value="<?php echo htmlspecialchars($orden['equipo_clave'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <!-- FILA 2 -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h5 class="mb-2">Motivo de ingreso</h5>
                                <div class="form-group mb-0">
                                    <label class="sr-only" for="motivo_ingreso">Motivo de ingreso *</label>
                                    <textarea id="motivo_ingreso"
                                              name="motivo_ingreso"
                                              class="form-control"
                                              rows="3"
                                              required
                                              placeholder="Describa el problema o motivo de ingreso *"><?php
                                        echo htmlspecialchars($orden['motivo_ingreso'] ?? '');
                                    ?></textarea>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <h5 class="mb-2">Observaciones de recepción</h5>
                                <div class="form-group mb-0">
                                    <label class="sr-only" for="observaciones">Observaciones de recepción</label>
                                    <textarea id="observaciones"
                                              name="observaciones"
                                              class="form-control"
                                              rows="3"
                                              placeholder="Observaciones de recepción (rayas, golpes, accesorios, etc.)"><?php
                                        echo htmlspecialchars($orden['observaciones_recepcion'] ?? '');
                                    ?></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- FILA 3: CHECKLIST -->
                        <h5 class="checklist-title">Checklist del Equipo</h5>
                        <div class="row checklist-row">

                            <div class="col-md-4">
                                <label class="check-item">
                                    <input type="checkbox" name="chk_enciende" <?php if (!empty($orden['chk_enciende'])) echo 'checked'; ?>>
                                    Enciende
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_error_inicio" <?php if (!empty($orden['chk_error_inicio'])) echo 'checked'; ?>>
                                    Arroja errores de inicio
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_rayas" <?php if (!empty($orden['chk_rayas'])) echo 'checked'; ?>>
                                    Rayas en pantalla
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_manchas" <?php if (!empty($orden['chk_manchas'])) echo 'checked'; ?>>
                                    Manchas
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_trizaduras" <?php if (!empty($orden['chk_trizaduras'])) echo 'checked'; ?>>
                                    Trizaduras
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="check-item">
                                    <input type="checkbox" name="chk_lineas" <?php if (!empty($orden['chk_lineas'])) echo 'checked'; ?>>
                                    Pantalla con líneas
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_golpes" <?php if (!empty($orden['chk_golpes'])) echo 'checked'; ?>>
                                    Abolladuras / golpes
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_signos_intervencion" <?php if (!empty($orden['chk_signos_intervencion'])) echo 'checked'; ?>>
                                    Signos de intervención
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_puertos_defectuosos" <?php if (!empty($orden['chk_puertos_defectuosos'])) echo 'checked'; ?>>
                                    Puertos con defectos físicos
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_tornillos" <?php if (!empty($orden['chk_tornillos'])) echo 'checked'; ?>>
                                    Faltan tornillos
                                </label>
                            </div>

                            <div class="col-md-4">
                                <label class="check-item">
                                    <input type="checkbox" name="chk_faltan_soportes" <?php if (!empty($orden['chk_faltan_soportes'])) echo 'checked'; ?>>
                                    Faltan soportes
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_falta_tapa_slot" <?php if (!empty($orden['chk_falta_tapa_slot'])) echo 'checked'; ?>>
                                    Falta tapa slot
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_garantia_fabrica" <?php if (!empty($orden['chk_garantia_fabrica'])) echo 'checked'; ?>>
                                    Garantía de fábrica vigente
                                </label>
                                <label class="check-item">
                                    <input type="checkbox" name="chk_tiene_patron" <?php if (!empty($orden['chk_tiene_patron'])) echo 'checked'; ?>>
                                    Tiene patrón / password
                                </label>
                            </div>
                        </div>

                        <!-- FILA 4 -->
                        <div class="row align-items-center mt-4">
                            <div class="col-md-6">
                                <div class="form-group form-check mb-0">
                                    <input type="checkbox"
                                           class="form-check-input"
                                           id="requiere_firma"
                                           name="requiere_firma"
                                           <?php if (!empty($orden['requiere_firma'])) echo 'checked'; ?>>
                                    <label class="form-check-label" for="requiere_firma">
                                        Requiere firma del cliente
                                    </label>
                                    <small class="form-text text-muted">
                                        Desmarque solo si esta orden no necesita firma del cliente.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6 text-right mt-3 mt-md-0">
                                <a href="<?php echo htmlspecialchars(url('/order/orden_detalle.php?id=' . $id)); ?>"
                                   class="btn btn-outline-secondary">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    Guardar cambios
                                </button>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
