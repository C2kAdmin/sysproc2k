<?php
// order/orden_nueva.php

require_once __DIR__ . '/../config/auth.php';

// ✅ Solo ADMIN y RECEPCION (SUPER_ADMIN siempre pasa por auth.php)
require_role(['ADMIN', 'RECEPCION']);

$mensaje_error = '';

// Anti-doble submit
if (!isset($_SESSION['anti_repost_token'])) {
    $_SESSION['anti_repost_token'] = bin2hex(random_bytes(16));
}
$antiToken = $_SESSION['anti_repost_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formToken = $_POST['_anti_token'] ?? '';
    if ($formToken === '' || !hash_equals($antiToken, $formToken)) {
        $mensaje_error = 'El formulario ya fue enviado o expiró. Recarga la página e inténtalo nuevamente.';
    } else {

        // rotar token para que refresh NO duplique
        $_SESSION['anti_repost_token'] = bin2hex(random_bytes(16));
        $antiToken = $_SESSION['anti_repost_token'];

        // --- VALIDACIÓN TELÉFONO (SIN +56) ---
        $telefono_raw     = trim($_POST['cliente_telefono'] ?? '');
        $telefono_digitos = preg_replace('/\D+/', '', $telefono_raw);

        if ($telefono_digitos === '' || strlen($telefono_digitos) < 8 || strlen($telefono_digitos) > 9) {
            $mensaje_error = 'Ingresa un teléfono chileno válido sin el +56 (solo números, 8 o 9 dígitos).';
        } else {

            $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
            $motivo_ingreso = trim($_POST['motivo_ingreso'] ?? '');

            if ($cliente_nombre === '' || $motivo_ingreso === '') {
                $mensaje_error = 'Nombre del cliente y motivo de ingreso son obligatorios.';
            } else {

                // Guardamos siempre con 56 delante
                $telefono_guardar = '56' . $telefono_digitos;

                try {
                    // 1) Siguiente número de orden
                    $stmt = $pdo->query("SELECT COALESCE(MAX(numero_orden),0)+1 AS siguiente FROM ordenes");
                    $row = $stmt->fetch();
                    $numero_orden = $row ? (int)$row['siguiente'] : 1;

                    // 2) Usuario recepción
                    $usuario_recepcion = $_SESSION['usuario_nombre'] ?? 'Recepción';

                    // 3) Helper checkboxes
                    if (!function_exists('chk')) {
                        function chk($name) { return isset($_POST[$name]) ? 1 : 0; }
                    }

                    // 4) Token público
                    $token_publico = bin2hex(random_bytes(16));

                    // 5) INSERT
                    $sql = "INSERT INTO ordenes (
                                numero_orden,
                                token_publico,
                                fecha_ingreso,
                                usuario_recepcion,
                                cliente_nombre,
                                cliente_telefono,
                                cliente_rut,
                                cliente_email,
                                equipo_marca,
                                equipo_modelo,
                                equipo_imei1,
                                equipo_imei2,
                                equipo_clave,
                                motivo_ingreso,
                                observaciones_recepcion,
                                chk_enciende,
                                chk_error_inicio,
                                chk_rayas,
                                chk_manchas,
                                chk_trizaduras,
                                chk_lineas,
                                chk_golpes,
                                chk_signos_intervencion,
                                chk_puertos_defectuosos,
                                chk_tornillos,
                                chk_faltan_soportes,
                                chk_falta_tapa_slot,
                                chk_garantia_fabrica,
                                chk_tiene_patron,
                                requiere_firma,
                                estado_actual
                            ) VALUES (
                                :numero_orden,
                                :token_publico,
                                NOW(),
                                :usuario_recepcion,
                                :cliente_nombre,
                                :cliente_telefono,
                                :cliente_rut,
                                :cliente_email,
                                :equipo_marca,
                                :equipo_modelo,
                                :equipo_imei1,
                                :equipo_imei2,
                                :equipo_clave,
                                :motivo_ingreso,
                                :observaciones_recepcion,
                                :chk_enciende,
                                :chk_error_inicio,
                                :chk_rayas,
                                :chk_manchas,
                                :chk_trizaduras,
                                :chk_lineas,
                                :chk_golpes,
                                :chk_signos_intervencion,
                                :chk_puertos_defectuosos,
                                :chk_tornillos,
                                :chk_faltan_soportes,
                                :chk_falta_tapa_slot,
                                :chk_garantia_fabrica,
                                :chk_tiene_patron,
                                :requiere_firma,
                                :estado_actual
                            )";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        ':numero_orden'            => $numero_orden,
                        ':token_publico'           => $token_publico,
                        ':usuario_recepcion'       => $usuario_recepcion,
                        ':cliente_nombre'          => $cliente_nombre,
                        ':cliente_telefono'        => $telefono_guardar,
                        ':cliente_rut'             => trim($_POST['cliente_rut']      ?? ''),
                        ':cliente_email'           => trim($_POST['cliente_email']    ?? ''),
                        ':equipo_marca'            => trim($_POST['equipo_marca']     ?? ''),
                        ':equipo_modelo'           => trim($_POST['equipo_modelo']    ?? ''),
                        ':equipo_imei1'            => trim($_POST['equipo_imei1']     ?? ''),
                        ':equipo_imei2'            => trim($_POST['equipo_imei2']     ?? ''),
                        ':equipo_clave'            => trim($_POST['equipo_clave']     ?? ''),
                        ':motivo_ingreso'          => $motivo_ingreso,
                        ':observaciones_recepcion' => trim($_POST['observaciones']    ?? ''),
                        ':chk_enciende'            => chk('chk_enciende'),
                        ':chk_error_inicio'        => chk('chk_error_inicio'),
                        ':chk_rayas'               => chk('chk_rayas'),
                        ':chk_manchas'             => chk('chk_manchas'),
                        ':chk_trizaduras'          => chk('chk_trizaduras'),
                        ':chk_lineas'              => chk('chk_lineas'),
                        ':chk_golpes'              => chk('chk_golpes'),
                        ':chk_signos_intervencion' => chk('chk_signos_intervencion'),
                        ':chk_puertos_defectuosos' => chk('chk_puertos_defectuosos'),
                        ':chk_tornillos'           => chk('chk_tornillos'),
                        ':chk_faltan_soportes'     => chk('chk_faltan_soportes'),
                        ':chk_falta_tapa_slot'     => chk('chk_falta_tapa_slot'),
                        ':chk_garantia_fabrica'    => chk('chk_garantia_fabrica'),
                        ':chk_tiene_patron'        => chk('chk_tiene_patron'),
                        ':requiere_firma'          => chk('requiere_firma'),
                        ':estado_actual'           => 'INGRESADO',
                    ]);

                    // Redirigir a detalle
                    $id = (int)$pdo->lastInsertId();
                    header('Location: ' . url('/order/orden_detalle.php?id=' . $id . '&creada=1'));
                    exit;

                } catch (Exception $e) {
                    $mensaje_error = 'Error al guardar la orden. Intente nuevamente.';
                    // $mensaje_error = 'Error al guardar la orden: ' . $e->getMessage();
                }
            }
        }
    }
}
?>

<?php require_once __DIR__ . '/../includes/header.php'; ?>
<?php require_once __DIR__ . '/../includes/sidebar.php'; ?>

<style>
.checklist-row { font-size: 13px; }
.checklist-row .check-item { display:block; margin:0; padding:0; line-height:1.2; white-space:nowrap; }
.checklist-row .check-item input[type="checkbox"] { margin-right:4px; position:static !important; opacity:1 !important; }
.checklist-title { margin-top:1.25rem; margin-bottom:0.5rem; }
#requiere_firma { position:static !important; opacity:1 !important; margin-right:4px; }
.input-group-text.prefix-phone { min-width:3.2rem; justify-content:center; }
</style>

<div class="main-panel">
<div class="content">
<div class="container-fluid">

    <h4 class="page-title">Nueva Orden de Servicio</h4>

    <?php if ($mensaje_error !== ''): ?>
        <div class="alert alert-danger">
            <?php echo htmlspecialchars($mensaje_error); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h4 class="card-title mb-0">Recepción de Equipo</h4>
            <p class="card-category">Complete la información del cliente y del equipo</p>
        </div>

        <div class="card-body">

            <form method="post" action="<?php echo url('/order/orden_nueva.php'); ?>">
                <input type="hidden" name="_anti_token" value="<?php echo htmlspecialchars($antiToken); ?>">

                <!-- FILA 1 -->
                <div class="row">

                    <div class="col-md-6">
                        <h5 class="mt-3">Datos del Cliente</h5>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="sr-only">Nombre del cliente *</label>
                                    <input type="text" name="cliente_nombre" class="form-control" placeholder="Nombre del cliente *" required>
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
                                               title="Ingresa solo números, 8 o 9 dígitos sin el +56">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="sr-only">RUT</label>
                                    <input type="text" name="cliente_rut" class="form-control" placeholder="RUT (opcional)">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="sr-only">Correo</label>
                                    <input type="email" name="cliente_email" class="form-control" placeholder="Correo (opcional)">
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
                                    <input type="text" name="equipo_marca" class="form-control" placeholder="Marca">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="sr-only">Modelo</label>
                                    <input type="text" name="equipo_modelo" class="form-control" placeholder="Modelo">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="sr-only">IMEI 1</label>
                                    <input type="text" name="equipo_imei1" class="form-control" placeholder="IMEI 1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="sr-only">IMEI 2</label>
                                    <input type="text" name="equipo_imei2" class="form-control" placeholder="IMEI 2 (opcional)">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="sr-only">Clave / Patrón</label>
                            <input type="text" name="equipo_clave" class="form-control" placeholder="Clave / Patrón (opcional)">
                        </div>
                    </div>

                </div>

                <!-- FILA 2 -->
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h5 class="mb-2">Motivo de ingreso</h5>
                        <div class="form-group mb-0">
                            <label class="sr-only" for="motivo_ingreso">Motivo de ingreso *</label>
                            <textarea id="motivo_ingreso" name="motivo_ingreso" class="form-control" rows="3" required
                                      placeholder="Describa el problema o motivo de ingreso *"></textarea>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <h5 class="mb-2">Observaciones de recepción</h5>
                        <div class="form-group mb-0">
                            <label class="sr-only" for="observaciones">Observaciones de recepción</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" rows="3"
                                      placeholder="Observaciones de recepción (rayas, golpes, accesorios, etc.)"></textarea>
                        </div>
                    </div>
                </div>

                <!-- FILA 3 -->
                <h5 class="checklist-title">Checklist del Equipo</h5>
                <div class="row checklist-row">
                    <div class="col-md-4">
                        <label class="check-item"><input type="checkbox" name="chk_enciende"> Enciende</label>
                        <label class="check-item"><input type="checkbox" name="chk_error_inicio"> Arroja errores de inicio</label>
                        <label class="check-item"><input type="checkbox" name="chk_rayas"> Rayas en pantalla</label>
                        <label class="check-item"><input type="checkbox" name="chk_manchas"> Manchas</label>
                        <label class="check-item"><input type="checkbox" name="chk_trizaduras"> Trizaduras</label>
                    </div>

                    <div class="col-md-4">
                        <label class="check-item"><input type="checkbox" name="chk_lineas"> Pantalla con líneas</label>
                        <label class="check-item"><input type="checkbox" name="chk_golpes"> Abolladuras / golpes</label>
                        <label class="check-item"><input type="checkbox" name="chk_signos_intervencion"> Signos de intervención</label>
                        <label class="check-item"><input type="checkbox" name="chk_puertos_defectuosos"> Puertos con defectos físicos</label>
                        <label class="check-item"><input type="checkbox" name="chk_tornillos"> Faltan tornillos</label>
                    </div>

                    <div class="col-md-4">
                        <label class="check-item"><input type="checkbox" name="chk_faltan_soportes"> Faltan soportes</label>
                        <label class="check-item"><input type="checkbox" name="chk_falta_tapa_slot"> Falta tapa slot</label>
                        <label class="check-item"><input type="checkbox" name="chk_garantia_fabrica"> Garantía de fábrica vigente</label>
                        <label class="check-item"><input type="checkbox" name="chk_tiene_patron"> Tiene patrón / password</label>
                    </div>
                </div>

                <!-- FILA 4 -->
                <div class="row align-items-center mt-4">
                    <div class="col-md-6">
                        <div class="form-group form-check mb-0">
                            <input type="checkbox" class="form-check-input" id="requiere_firma" name="requiere_firma" checked>
                            <label class="form-check-label" for="requiere_firma">Requiere firma del cliente</label>
                            <small class="form-text text-muted">
                                Deje esta opción marcada para que el cliente firme la orden.
                                Desmarcar solo en casos especiales.
                            </small>
                        </div>
                    </div>
                    <div class="col-md-6 text-right mt-3 mt-md-0">
                        <button type="submit" class="btn btn-primary">
                            Guardar orden
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
