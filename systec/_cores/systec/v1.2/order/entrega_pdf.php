<?php
// order/entrega_pdf.php
// PDF interno (requiere sesión) - Comprobante de Entrega / Retiro

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ✅ Sesión requerida
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

/* -----------------------------
   Helpers
----------------------------- */
function getParametro($clave, $default = '')
{
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :clave LIMIT 1");
        $stmt->execute([':clave' => $clave]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($fila && $fila['valor'] !== '') ? $fila['valor'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function title_case_smart($str) {
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

function format_rut($rutRaw) {
    $rut = preg_replace('/[^0-9kK]/', '', (string)$rutRaw);
    if ($rut === '') return '';
    $dv  = strtoupper(substr($rut, -1));
    $num = substr($rut, 0, -1);
    if ($num === '') return (string)$rutRaw;

    $rev    = strrev($num);
    $chunks = str_split($rev, 3);
    $withDotsRev = implode('.', $chunks);
    $withDots    = strrev($withDotsRev);
    return $withDots . '-' . $dv;
}

/* ✅ Ajuste: formatear Marca/Modelo solo para mostrar */
function texto_equipo($marca, $modelo) {
    $m = title_case_smart($marca ?? '');
    $mo = title_case_smart($modelo ?? '');
    $txt = trim($m . ' ' . $mo);
    return $txt !== '' ? $txt : 'Equipo sin especificar';
}

function img_to_datauri($fsPath) {
    if (!$fsPath || !is_file($fsPath)) return '';
    $ext = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png')  $mime = 'image/png';
    if ($ext === 'webp') $mime = 'image/webp';
    if ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';

    $bin = @file_get_contents($fsPath);
    if ($bin === false || $bin === '') return '';
    return 'data:' . $mime . ';base64,' . base64_encode($bin);
}

/* -----------------------------
   Datos negocio
----------------------------- */
$nombreNegocio = getParametro('nombre_negocio', 'ServiTec');
$direccion     = getParametro('direccion', '');
$telefono      = getParametro('telefono', '');
$whatsapp      = getParametro('whatsapp', '');
$emailNegocio  = getParametro('email', '');
$logoRuta      = getParametro('logo_ruta', '');

/* -----------------------------
   Orden
----------------------------- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    exit('ID de orden no válido');
}

$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    exit('Orden no encontrada');
}

/* -----------------------------
   Datos “bonitos”
----------------------------- */
$numeroOrden = str_pad((string)($orden['numero_orden'] ?? $id), 4, '0', STR_PAD_LEFT);
$fechaIng    = !empty($orden['fecha_ingreso']) ? date('d-m-Y H:i', strtotime($orden['fecha_ingreso'])) : '';
$estadoAct   = (string)($orden['estado_actual'] ?? '');

$clienteNombreFmt = title_case_smart($orden['cliente_nombre'] ?? '');
$clienteTel       = (string)($orden['cliente_telefono'] ?? '');
$clienteRutFmt    = format_rut($orden['cliente_rut'] ?? '');
$clienteEmail     = (string)($orden['cliente_email'] ?? '');

/* ✅ Equipo ya sale formateado */
$equipoTxt   = texto_equipo($orden['equipo_marca'] ?? '', $orden['equipo_modelo'] ?? '');
$imei1       = trim((string)($orden['equipo_imei1'] ?? ''));
$imei2       = trim((string)($orden['equipo_imei2'] ?? ''));
$clave       = trim((string)($orden['equipo_clave'] ?? ''));

$motivo      = trim((string)($orden['motivo_ingreso'] ?? ''));
$motivo      = $motivo !== '' ? $motivo : 'Motivo no registrado.';

$obsFinal = trim((string)($_GET['obs'] ?? ''));
if ($obsFinal === '') {
    // por defecto, dejamos vacío para que tú lo completes si quieres
    $obsFinal = '';
}

// Responsable que entrega (si en tu sesión tienes un nombre, lo usamos)
$entregaPor = (string)($_SESSION['usuario_nombre'] ?? ($_SESSION['nombre'] ?? ''));
if ($entregaPor === '') $entregaPor = (string)($_SESSION['usuario_email'] ?? 'Administrador');

$fechaEntrega = date('d-m-Y H:i');

/* -----------------------------
   Logo base64
----------------------------- */
$logoHtml = '';
if ($logoRuta) {
    $logoFs = __DIR__ . '/../' . ltrim($logoRuta, '/');
    $data = img_to_datauri($logoFs);
    if ($data) {
        $logoHtml = '<img src="' . $data . '" style="max-height:46px;">';
    }
}

/* -----------------------------
   HTML PDF
----------------------------- */
ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Comprobante de Entrega - Orden <?php echo $numeroOrden; ?></title>
<style>
    *{ box-sizing:border-box; font-family:DejaVu Sans, Arial, Helvetica, sans-serif; }
    body{ margin:14px 16px; color:#222; font-size:10px; }

    .row{ width:100%; clear:both; }
    .left{ float:left; width:72%; }
    .right{ float:right; width:28%; text-align:right; }
    .clear{ clear:both; }

    h2{ margin:0; font-size:14px; }
    h3{ margin:0 0 2px 0; font-size:12px; }
    h4{ margin:0 0 6px 0; font-size:11px; }

    .muted{ color:#777; font-size:9px; line-height:1.25; }
    .box{ border:1px solid #ddd; border-radius:6px; padding:8px; margin-top:8px; }
    .titleCenter{ text-align:center; margin-top:8px; }
    .subCenter{ text-align:center; color:#666; font-size:9px; margin-top:4px; }

    .grid{ width:100%; }
    .col-6{ float:left; width:50%; padding-right:8px; }
    .label{ color:#777; font-size:9px; }
    .strong{ font-weight:700; }

    .hr{ border-top:1px solid #ccc; margin:8px 0; }

    .firmas{ margin-top:8px; }
    .firmaBox{
        border:1px solid #ccc;
        border-radius:6px;
        height:90px;
        padding:6px;
    }
    .firmaLabel{ margin-top:4px; font-size:9px; color:#666; }

    .cond{
        font-size:9px;
        color:#444;
        line-height:1.25;
        margin-top:4px;
    }
    .avoid-break{ page-break-inside:avoid; }

</style>
</head>
<body>

<div class="row">
    <div class="left">
        <h3><?php echo htmlspecialchars($nombreNegocio); ?></h3>
        <div class="muted">
            <?php echo htmlspecialchars($direccion); ?><br>
            <?php if ($telefono): ?>Tel: <?php echo htmlspecialchars($telefono); ?><?php endif; ?>
            <?php if ($whatsapp): ?> · WhatsApp: <?php echo htmlspecialchars($whatsapp); ?><?php endif; ?><br>
            <?php if ($emailNegocio): ?>Email: <?php echo htmlspecialchars($emailNegocio); ?><?php endif; ?>
        </div>
    </div>
    <div class="right">
        <?php if ($logoHtml) echo $logoHtml; ?>
    </div>
    <div class="clear"></div>
</div>

<div class="titleCenter">
    <h2>Comprobante de Entrega / Retiro</h2>
</div>
<div class="subCenter">
    Orden N° <strong><?php echo $numeroOrden; ?></strong>
    <?php if ($fechaIng): ?> · Ingreso: <?php echo htmlspecialchars($fechaIng); ?><?php endif; ?>
    <?php if ($estadoAct): ?> · Estado: <?php echo htmlspecialchars($estadoAct); ?><?php endif; ?>
</div>

<div class="box">
    <div class="row">
        <div class="col-6">
            <h4>Datos del Cliente</h4>
            <div><span class="label">Nombre:</span> <span class="strong"><?php echo htmlspecialchars($clienteNombreFmt); ?></span></div>
            <div><span class="label">Teléfono:</span> <?php echo htmlspecialchars($clienteTel); ?></div>
            <div><span class="label">RUT:</span> <?php echo htmlspecialchars($clienteRutFmt); ?></div>
            <div><span class="label">Correo:</span> <?php echo htmlspecialchars($clienteEmail); ?></div>
        </div>

        <div class="col-6">
            <h4>Datos del Equipo</h4>
            <div><span class="label">Equipo:</span> <span class="strong"><?php echo htmlspecialchars($equipoTxt); ?></span></div>
            <div><span class="label">IMEI 1:</span> <?php echo htmlspecialchars($imei1 ?: '—'); ?></div>
            <div><span class="label">IMEI 2:</span> <?php echo htmlspecialchars($imei2 ?: '—'); ?></div>
            <div><span class="label">Clave/Patrón:</span> <?php echo htmlspecialchars($clave ?: '—'); ?></div>
        </div>
    </div>
    <div class="clear"></div>

    <div class="hr"></div>

    <h4>Trabajo / Motivo</h4>
    <div><?php echo nl2br(htmlspecialchars($motivo)); ?></div>

    <?php if ($obsFinal !== ''): ?>
        <div class="hr"></div>
        <h4>Observación final de entrega</h4>
        <div><?php echo nl2br(htmlspecialchars($obsFinal)); ?></div>
    <?php endif; ?>

    <div class="hr"></div>

    <h4>Confirmación del cliente</h4>
    <div class="cond">
        Declaro que recibí conforme el equipo señalado, y que se me informó el estado del equipo al momento de la entrega.
        Este comprobante no corresponde a boleta/factura y solo acredita la entrega/recepción del equipo.
    </div>
</div>

<div class="box avoid-break firmas">
    <div class="row">
        <div class="col-6">
            <h4>Firma del cliente (Recibí conforme)</h4>
            <div class="firmaBox"></div>
            <div class="firmaLabel">
                Nombre: ________________________________ &nbsp;&nbsp; RUT: __________________________
            </div>
        </div>

        <div class="col-6">
            <h4>Entrega realizada por</h4>
            <div class="firmaBox" style="height:90px;">
                <div class="muted" style="margin-top:4px;">
                    <strong><?php echo htmlspecialchars($entregaPor); ?></strong><br>
                    Fecha/Hora: <?php echo htmlspecialchars($fechaEntrega); ?><br>
                    Firma: ________________________________
                </div>
            </div>
        </div>
    </div>
    <div class="clear"></div>
</div>

<div class="muted" style="margin-top:8px;">
    *Recomendación: probar equipo al momento del retiro (pantalla, carga, sonido, cámaras, botones).
</div>

</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

$dompdf->stream('comprobante_entrega_' . $numeroOrden . '.pdf', ['Attachment' => false]);
exit;
