<?php
// order/orden_diagnostico.php

require_once __DIR__ . '/../config/auth.php';

// ✅ ADMIN y TECNICO (SUPER_ADMIN se permite automáticamente)
require_role(['ADMIN','TECNICO']);

// ------------------------------
// 2) Tomar ID de la URL
// ------------------------------
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}
$mensaje_error = '';

// Anti doble submit
if (!isset($_SESSION['anti_repost_diag'])) {
    $_SESSION['anti_repost_diag'] = bin2hex(random_bytes(16));
}
$antiToken = $_SESSION['anti_repost_diag'];

// ------------------------------
// 3) Cargar la orden
// ------------------------------
$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}
// Estado actual antes (para saber si cambia)
$estadoActualAntes = $orden['estado_actual'] ?? '';

// Helpers
function normalizar_numero($txt)
{
    $txt = trim((string)$txt);
    if ($txt === '') return 0;

    // quitamos puntos de miles, cambiamos coma por punto
    $txt = str_replace(['.', ','], ['', '.'], $txt);
    return is_numeric($txt) ? (float)$txt : 0;
}

function normalizar_estado($estado)
{
    $e = trim((string)$estado);
    if ($e === '') return '';

    // Uniformamos: quitamos dobles espacios y dejamos mayúsculas
    $e = preg_replace('/\s+/', ' ', $e);
    $e = mb_strtoupper($e, 'UTF-8');

    // Normalizamos variantes sin tilde / con tilde
    if ($e === 'DIAGNOSTICO') $e = 'DIAGNÓSTICO';
if ($e === 'EN REPARACION') $e = 'EN REPARACIÓN';

// Alias por si algún día llega con variación
if ($e === 'ENTREGADA') $e = 'ENTREGADO';

return $e;
}

// Estados permitidos en esta pantalla (normalizados)
$estadosPermitidos = [
    'DIAGNÓSTICO',
    'EN REPARACIÓN',
    'EN ESPERA POR REPUESTOS',
    'REPARADO',
    'ENTREGADO',
];
// ------------------------------
// 4) Si viene POST, guardar diagnóstico/costos/estado
// ------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formToken = $_POST['_anti_token'] ?? '';
    if ($formToken === '' || !hash_equals($antiToken, $formToken)) {
        $mensaje_error = 'El formulario ya fue enviado o expiró. Recarga la página.';
    } else {

        // regeneramos token para bloquear repost
        $_SESSION['anti_repost_diag'] = bin2hex(random_bytes(16));
        $antiToken = $_SESSION['anti_repost_diag'];

        $diagnostico    = trim($_POST['diagnostico'] ?? '');
        $costoRepuestos = normalizar_numero($_POST['costo_repuestos'] ?? '0');
        $costoManoObra  = normalizar_numero($_POST['costo_mano_obra'] ?? '0');
        $costoTotal     = $costoRepuestos + $costoManoObra;

        $nuevoEstadoRaw = $_POST['nuevo_estado'] ?? '';
        $nuevoEstado    = normalizar_estado($nuevoEstadoRaw);

        $comentarioEstado = trim($_POST['comentario_estado'] ?? '');

        // Si no viene estado, mantenemos el anterior (pero normalizado para comparar)
        $estadoAntesNorm = normalizar_estado($estadoActualAntes);
        if ($nuevoEstado === '') {
            $nuevoEstado = $estadoAntesNorm;
        }

        // Si viene un estado que NO está permitido, no lo aplicamos
        if (!in_array($nuevoEstado, $estadosPermitidos, true)) {
            $nuevoEstado = $estadoAntesNorm; // lo ignoramos silenciosamente
        }

        try {
            $pdo->beginTransaction();

            // 4.1) Actualizar diagnóstico y costos (y estado_actual)
            $sql = "UPDATE ordenes SET
                        diagnostico      = :diagnostico,
                        costo_repuestos  = :costo_repuestos,
                        costo_mano_obra  = :costo_mano_obra,
                        costo_total      = :costo_total,
                        estado_actual    = :estado_actual
                    WHERE id = :id";

            $stmtUpd = $pdo->prepare($sql);
            $stmtUpd->execute([
                ':diagnostico'     => $diagnostico,
                ':costo_repuestos' => $costoRepuestos,
                ':costo_mano_obra' => $costoManoObra,
                ':costo_total'     => $costoTotal,
                ':estado_actual'   => $nuevoEstado,
                ':id'              => $id,
            ]);

            // 4.2) Si el estado cambió, lo registramos en historial
            if ($nuevoEstado !== $estadoAntesNorm) {

                // Si no ponen comentario, dejamos uno mínimo (para trazabilidad)
                if ($comentarioEstado === '') {
                    $comentarioEstado = 'Cambio de estado desde pantalla diagnóstico/costos.';
                }

                $stmtHist = $pdo->prepare("
                    INSERT INTO ordenes_estados (orden_id, estado, comentario, usuario)
                    VALUES (:orden_id, :estado, :comentario, :usuario)
                ");
                $stmtHist->execute([
                    ':orden_id'   => $id,
                    ':estado'     => $nuevoEstado,
                    ':comentario' => $comentarioEstado,
                    ':usuario'    => $_SESSION['usuario_nombre'] ?? 'Sistema',
                ]);
            }

            $pdo->commit();

            header('Location: ' . url('/order/orden_detalle.php?id=' . $id));
exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $mensaje_error = 'Error al guardar el diagnóstico y costos.';
            // debug:
            // $mensaje_error = $e->getMessage();
        }

        // Si hubo error, recargamos orden desde BD
        $stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        $estadoActualAntes = $orden['estado_actual'] ?? '';
    }
}

// ------------------------------
// 5) Valores para mostrar
// ------------------------------
$numeroOrden   = str_pad((string)$orden['numero_orden'], 4, '0', STR_PAD_LEFT);
$clienteNombre = $orden['cliente_nombre'] ?? '';
$equipo        = trim(($orden['equipo_marca'] ?? '') . ' ' . ($orden['equipo_modelo'] ?? ''));
if ($equipo === '') $equipo = 'Equipo sin especificar';

$estadoActualAntes = normalizar_estado($estadoActualAntes);

// Formateo CL
$costoRepuestosFmt = number_format((float)($orden['costo_repuestos'] ?? 0), 0, ',', '.');
$costoManoObraFmt  = number_format((float)($orden['costo_mano_obra'] ?? 0), 0, ',', '.');
$costoTotalFmt     = number_format((float)($orden['costo_total'] ?? 0), 0, ',', '.');

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.detalle-mini{font-size:13px;color:#6b7280}
.detalle-mini strong{color:#111827}
</style>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Diagnóstico y costos</h4>

            <?php if ($mensaje_error !== ''): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($mensaje_error); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-0">
                            Orden N° <?php echo htmlspecialchars($numeroOrden); ?>
                        </h4>
                        <div class="detalle-mini">
                            Cliente: <strong><?php echo htmlspecialchars($clienteNombre); ?></strong>
                            · Equipo: <strong><?php echo htmlspecialchars($equipo); ?></strong>
                            · Estado actual: <strong><?php echo htmlspecialchars($estadoActualAntes); ?></strong>
                        </div>
                    </div>
                    <div>
                        <a href="<?php echo url('/order/orden_detalle.php?id='.(int)$orden['id']); ?>"
class="btn btn-outline-secondary btn-sm">
                            Volver al detalle
                        </a>
                    </div>
                </div>

                <div class="card-body">

                    <form method="post" action="<?php echo url('/order/orden_diagnostico.php?id='.(int)$orden['id']); ?>">
<input type="hidden" name="_anti_token" value="<?php echo htmlspecialchars($antiToken); ?>">

                        <div class="row">
                            <!-- Diagnóstico -->
                            <div class="col-md-7">
                                <h5>Diagnóstico técnico</h5>
                                <div class="form-group">
                                    <label for="diagnostico" class="sr-only">Diagnóstico técnico</label>
                                    <textarea id="diagnostico"
                                              name="diagnostico"
                                              class="form-control"
                                              rows="8"
                                              placeholder="Describa el diagnóstico, piezas a cambiar, pruebas realizadas, etc."><?php
                                        echo htmlspecialchars($orden['diagnostico'] ?? '');
                                    ?></textarea>
                                </div>
                            </div>

                            <!-- Costos + Estado -->
                            <div class="col-md-5">
                                <h5>Costos del servicio</h5>
                                <div class="form-row">
                                    <div class="form-group col-md-6">
                                        <label for="costo_repuestos">Repuestos</label>
                                        <input type="text"
                                               id="costo_repuestos"
                                               name="costo_repuestos"
                                               class="form-control"
                                               placeholder="0"
                                               value="<?php echo htmlspecialchars($costoRepuestosFmt); ?>">
                                    </div>
                                    <div class="form-group col-md-6">
                                        <label for="costo_mano_obra">Mano de obra</label>
                                        <input type="text"
                                               id="costo_mano_obra"
                                               name="costo_mano_obra"
                                               class="form-control"
                                               placeholder="0"
                                               value="<?php echo htmlspecialchars($costoManoObraFmt); ?>">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label>Total estimado</label>
                                    <input type="text"
                                           id="costo_total_vista"
                                           class="form-control"
                                           readonly
                                           value="<?php echo htmlspecialchars($costoTotalFmt); ?>">
                                    <small class="text-muted">
                                        Puedes usar puntos para miles (ej: 15.000).
                                    </small>
                                </div>

                                <hr>

                                <h5 class="mt-3">Estado de la orden</h5>
                                <div class="form-group">
                                    <label for="nuevo_estado">Nuevo estado</label>
                                    <select id="nuevo_estado"
                                            name="nuevo_estado"
                                            class="form-control">
                                        <option value="">(mantener: <?php echo htmlspecialchars($estadoActualAntes); ?>)</option>
                                        <?php
                                        $estadosPosibles = [
    'DIAGNÓSTICO',
    'EN REPARACIÓN',
    'EN ESPERA POR REPUESTOS',
    'REPARADO',
    'ENTREGADO',
];
foreach ($estadosPosibles as $estado) {
                                            $sel = ($estadoActualAntes === $estado) ? 'selected' : '';
                                            echo '<option value="' . htmlspecialchars($estado) . '" ' . $sel . '>'
                                                 . htmlspecialchars($estado)
                                                 . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <small class="text-muted">
                                        Si no seleccionas nada, se mantiene el estado actual.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="comentario_estado">Comentario para historial (opcional)</label>
                                    <input type="text"
                                           id="comentario_estado"
                                           name="comentario_estado"
                                           class="form-control"
                                           placeholder="Ej: Se informa diagnóstico y valor al cliente.">
                                </div>
                            </div>
                        </div>

                        <div class="text-right mt-3">
                            <a href="<?php echo $APP; ?>/order/orden_detalle.php?id=<?php echo (int)$orden['id']; ?>"
                               class="btn btn-outline-secondary">
                                Cancelar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Guardar diagnóstico y costos
                            </button>
                        </div>

                    </form>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function(){
    function soloDigitos(s){ return (s || '').replace(/[^\d]/g,''); }
    function fmtCL(n){
        n = Math.max(0, parseInt(n || '0', 10) || 0);
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    function calc(){
        var r = soloDigitos(document.getElementById('costo_repuestos').value);
        var m = soloDigitos(document.getElementById('costo_mano_obra').value);
        var total = (parseInt(r || '0', 10) || 0) + (parseInt(m || '0', 10) || 0);
        document.getElementById('costo_total_vista').value = fmtCL(total);
    }
    var a = document.getElementById('costo_repuestos');
    var b = document.getElementById('costo_mano_obra');
    if (a && b){
        a.addEventListener('input', calc);
        b.addEventListener('input', calc);
        calc();
    }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
