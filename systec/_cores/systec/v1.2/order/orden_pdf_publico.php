<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// order/orden_pdf_publico.php
// PDF público por token (NO requiere sesión)

require_once __DIR__ . '/../config/config_publico.php';
require_once __DIR__ . '/../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Validar token
$token = trim($_GET['token'] ?? '');
if ($token === '' || strlen($token) < 16) {
    http_response_code(404);
    exit('Token inválido');
}

// Helper parámetros
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

// --------- Helpers ---------
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

function title_case_smart($str) {
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

function checklistLabelPdf($value, $mode = 'normal') {
    $v   = (int)$value;
    $txt = $v ? 'Sí' : 'No';
    $class = 'text-primary';

    switch ($mode) {
        case 'ok_if_yes':    $class = $v ? 'text-primary' : 'text-muted'; break;
        case 'alert_if_yes': $class = $v ? 'text-danger'  : 'text-primary'; break;
        case 'warn_if_yes':  $class = $v ? 'text-warning' : 'text-primary'; break;
        default:             $class = 'text-primary'; break;
    }
    return '<span class="' . $class . '">' . $txt . '</span>';
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
   Helper: render galería 2 columnas (tabla)
----------------------------- */
function renderGaleriaEvidencias($items) {
    if (empty($items)) return '';

    $html = '<table class="evi-table" width="100%" cellspacing="0" cellpadding="0">';
    $col = 0;

    foreach ($items as $ev) {
        if ($col === 0) $html .= '<tr>';

        $tipo = htmlspecialchars((string)$ev['tipo']);
        $coment = trim((string)$ev['comentario']);
        $coment = $coment !== '' ? htmlspecialchars($coment) : '';
        $fecha = !empty($ev['fecha']) ? date('d-m-Y H:i', strtotime($ev['fecha'])) : '';
        $fecha = $fecha ? htmlspecialchars($fecha) : '';

        $html .= '<td class="evi-td" width="50%">';
        $html .= '  <div class="evi-card">';
        $html .= '    <img class="evi-img" src="' . htmlspecialchars($ev['data']) . '" alt="Evidencia">';
        $html .= '    <div class="evi-meta">';
        $html .= '      <div class="evi-tipo"><strong>' . $tipo . '</strong></div>';
        if ($coment !== '') $html .= '  <div class="muted">' . $coment . '</div>';
        if ($fecha !== '')  $html .= '  <div class="muted">' . $fecha . '</div>';
        $html .= '    </div>';
        $html .= '  </div>';
        $html .= '</td>';

        $col++;
        if ($col === 2) {
            $html .= '</tr>';
            $col = 0;
        }
    }

    if ($col === 1) {
        $html .= '<td class="evi-td" width="50%"></td></tr>';
    }

    $html .= '</table>';
    return $html;
}

/* -----------------------------
   Datos del negocio
----------------------------- */
$nombreNegocio = getParametro('nombre_negocio', 'ServiTec');
$direccion     = getParametro('direccion', '');
$telefono      = getParametro('telefono', '');
$whatsapp      = getParametro('whatsapp', '');
$emailNegocio  = getParametro('email', '');
$pieOrdenTxt   = getParametro('pie_orden', 'Gracias por confiar en nosotros.');
$logoRuta      = getParametro('logo_ruta', '');

// Traer orden por token_publico
$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE token_publico = :t LIMIT 1");
$stmt->execute([':t' => $token]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada');
}

$ordenId = (int)$orden['id'];

/* -----------------------------
   Formatos
----------------------------- */
$clienteNombreFmt = title_case_smart($orden['cliente_nombre'] ?? '');
$marcaFmt         = title_case_smart($orden['equipo_marca']   ?? '');
$modeloFmt        = title_case_smart($orden['equipo_modelo']  ?? '');
$rutFmt           = format_rut($orden['cliente_rut'] ?? '');

// IMEIs
$imei1Mostrar = trim($orden['equipo_imei1'] ?? '');
$imei1Mostrar = ($imei1Mostrar === '') ? '<span class="text-muted">Sin IMEI</span>' : htmlspecialchars($imei1Mostrar);

$imei2Mostrar = trim($orden['equipo_imei2'] ?? '');
$imei2Mostrar = ($imei2Mostrar === '') ? '<span class="text-muted">Sin 2do IMEI</span>' : htmlspecialchars($imei2Mostrar);

// Motivo / Obs
$motivoBruto = trim($orden['motivo_ingreso'] ?? '');
$obsBruto    = trim($orden['observaciones_recepcion'] ?? '');
$motivoMostrar = $motivoBruto !== '' ? $motivoBruto : 'Motivo no registrado.';
$obsMostrar    = $obsBruto    !== '' ? $obsBruto    : 'Sin observaciones importantes.';

// BaseUrl (seguimiento) - más estable si APP_URL existe en config_publico.php
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$appPath = defined('APP_URL') ? rtrim((string)APP_URL, '/') : rtrim(str_replace('/order', '', dirname($_SERVER['SCRIPT_NAME'])), '/');
$baseUrl = $scheme . $_SERVER['HTTP_HOST'] . $appPath;

$seguimientoUrl = '';
if (!empty($orden['token_publico'])) {
    $seguimientoUrl = $baseUrl . '/order/seguimiento_orden.php?token=' . urlencode($orden['token_publico']);
}

// Logo base64
$logoHtml = '';
if ($logoRuta) {
    $logoFs = __DIR__ . '/../' . ltrim($logoRuta, '/');
    $data = img_to_datauri($logoFs);
    if ($data) $logoHtml = '<img src="' . $data . '" style="max-height:46px;">';
}

// Firma base64 (compacta)
$firmaHtml  = '<em>Sin firma registrada</em>';
$tieneFirma = false;

if (!empty($orden['firma_ruta'])) {
    $firmaFsPath = __DIR__ . '/../' . ltrim($orden['firma_ruta'], '/');
    $data = img_to_datauri($firmaFsPath);
    if ($data) {
        $firmaHtml  = '<img src="' . $data . '" style="max-width:100%; max-height:90px;">';
        $tieneFirma = true;
    } else {
        $firmaHtml = '<em>Firma no disponible</em>';
    }
}

// Evidencias visibles — base64
$stmtEv = $pdo->prepare("
    SELECT id, tipo, comentario, archivo, fecha
    FROM ordenes_evidencias
    WHERE orden_id = :id
      AND visible_cliente = 1
    ORDER BY id ASC
");
$stmtEv->execute([':id' => $ordenId]);
$evidencias = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

$evidenciasPdf = [];
foreach ($evidencias as $ev) {
    $archivoRel = trim((string)($ev['archivo'] ?? ''));
    if ($archivoRel === '') continue;

    // ✅ Resolver ruta física desde STORAGE_PATH (con fallback al CORE por compat)
    $archivoRel = ltrim(str_replace(['..\\','../'], '', $archivoRel), '/\\');

    $storageBase = $INSTANCE['STORAGE_PATH'] ?? '';
    $fs = '';

    if ($storageBase !== '') {
        $fs = rtrim($storageBase, '/\\') . '/' . $archivoRel;
    }

    // fallback compat: evidencias antiguas en CORE
    if ($fs === '' || !is_file($fs)) {
        $fsOld = APP_BASE . '/' . ltrim($archivoRel, '/\\');
        if (is_file($fsOld)) $fs = $fsOld;
    }

    $data = img_to_datauri($fs);
    if (!$data) continue;

    $evidenciasPdf[] = [
        'id' => (int)$ev['id'],
        'tipo' => (string)($ev['tipo'] ?? ''),
        'comentario' => (string)($ev['comentario'] ?? ''),
        'fecha' => (string)($ev['fecha'] ?? ''),
        'data' => $data,
    ];
}

// Reglas paginado evidencias
$eviFirstPageMax = 2;
$evidenciasPrimera = array_slice($evidenciasPdf, 0, $eviFirstPageMax);
$evidenciasResto   = array_slice($evidenciasPdf, $eviFirstPageMax);

// Datos PDF
$numeroOrden = str_pad((string)($orden['numero_orden'] ?? $ordenId), 4, '0', STR_PAD_LEFT);

$fechaIng = '';
if (!empty($orden['fecha_ingreso'])) {
    $ts = strtotime($orden['fecha_ingreso']);
    $fechaIng = $ts ? date('d-m-Y H:i', $ts) : (string)$orden['fecha_ingreso'];
}

$estadoAct = htmlspecialchars((string)($orden['estado_actual'] ?? ''));

ob_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de Servicio N° <?php echo $numeroOrden; ?></title>
    <style>
        * { box-sizing: border-box; font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10px; }
        body { margin: 14px 16px; color: #222; }

        h2 { margin: 0 0 4px 0; padding: 0; font-size: 14px; }
        h3 { margin: 0 0 2px 0; padding: 0; font-size: 12px; }
        h4 { margin: 0 0 4px 0; padding: 0; font-size: 11px; }

        .titulo { text-align: center; margin-bottom: 6px; }
        .subtitulo { text-align: center; font-size: 9px; color: #666; margin-bottom: 8px; }

        .row { width: 100%; clear: both; }
        .col-6 { width: 50%; float: left; padding-right: 6px; }
        .col-4 { width: 33.3333%; float: left; padding-right: 6px; }
        .col-12 { width: 100%; clear: both; }

        .box-block { border: 1px solid #dddddd; border-radius: 4px; padding: 6px; margin-bottom: 6px; }
        .label { font-size: 9px; color: #777; }
        .valor-strong { font-weight: bold; }
        ul { margin: 2px 0 0 0; padding-left: 12px; }
        ul li { margin-bottom: 1px; }

        hr { border: 0; border-top: 1px solid #ccc; margin: 6px 0; }

        .clear { clear: both; }

        .header-negocio-left { float:left; width:72%; }
        .header-negocio-right { float:right; width:28%; text-align:right; }

        .small-muted { font-size: 8.5px; color: #777; line-height: 1.25; }

        .text-primary { color: #007bff; }
        .text-danger  { color: #dc3545; }
        .text-warning { color: #ff9800; }
        .text-muted   { color: #999999; }

        .firma-box {
            border: 1px solid #ccc;
            height: 92px;
            padding: 4px;
            text-align: center;
        }
        .avoid-break { page-break-inside: avoid; }

        /* Evidencias: tabla 2 columnas */
        .evi-table { width: 100%; border-collapse: separate; border-spacing: 6px 6px; }
        .evi-td { vertical-align: top; }
        .evi-card { border:1px solid #e5e5e5; border-radius:6px; padding:6px; }
        .evi-img  { width: 100%; height: 150px; object-fit: cover; border-radius:6px; border:1px solid #ddd; }
        .evi-meta { margin-top: 4px; font-size: 9px; }
        .muted { color:#777; font-size: 8.5px; }

        .page-break { page-break-before: always; }
    </style>
</head>
<body>

<div class="row" style="margin-bottom:6px;">
    <div class="header-negocio-left">
        <h3><?php echo htmlspecialchars($nombreNegocio); ?></h3>
        <div class="small-muted">
            <?php echo htmlspecialchars($direccion); ?><br>
            <?php if ($telefono): ?>Tel: <?php echo htmlspecialchars($telefono); ?><?php endif; ?>
            <?php if ($whatsapp): ?> · WhatsApp: <?php echo htmlspecialchars($whatsapp); ?><?php endif; ?><br>
            <?php if ($emailNegocio): ?>Email: <?php echo htmlspecialchars($emailNegocio); ?><?php endif; ?>
        </div>
    </div>
    <div class="header-negocio-right">
        <?php if ($logoHtml) echo $logoHtml; ?>
    </div>
    <div class="clear"></div>
</div>

<div class="titulo">
    <h2>Orden de Servicio N° <?php echo $numeroOrden; ?></h2>
</div>
<div class="subtitulo">
    <?php if ($fechaIng): ?>Ingresada el <?php echo htmlspecialchars($fechaIng); ?><?php endif; ?>
    <?php if ($fechaIng && $estadoAct !== ''): ?> · <?php endif; ?>
    <?php if ($estadoAct !== ''): ?>Estado: <?php echo $estadoAct; ?><?php endif; ?>
</div>

<div class="box-block">
    <div class="row">
        <div class="col-6">
            <h4>Datos del Cliente</h4>
            <div><span class="label">Nombre:</span> <span class="valor-strong"><?php echo htmlspecialchars($clienteNombreFmt); ?></span></div>
            <div><span class="label">Teléfono:</span> <span><?php echo htmlspecialchars((string)($orden['cliente_telefono'] ?? '')); ?></span></div>
            <div><span class="label">RUT:</span> <span><?php echo htmlspecialchars($rutFmt); ?></span></div>
            <div><span class="label">Correo:</span> <span><?php echo htmlspecialchars((string)($orden['cliente_email'] ?? '')); ?></span></div>
        </div>

        <div class="col-6">
            <h4>Datos del Equipo</h4>
            <div><span class="label">Marca:</span> <span class="valor-strong"><?php echo htmlspecialchars($marcaFmt); ?></span></div>
            <div><span class="label">Modelo:</span> <span><?php echo htmlspecialchars($modeloFmt); ?></span></div>
            <div><span class="label">IMEI 1:</span> <span><?php echo $imei1Mostrar; ?></span></div>
            <div><span class="label">IMEI 2:</span> <span><?php echo $imei2Mostrar; ?></span></div>
            <div><span class="label">Clave / Patrón:</span> <span><?php echo htmlspecialchars((string)($orden['equipo_clave'] ?? '')); ?></span></div>
        </div>
    </div>
    <div class="clear"></div>
</div>

<div class="box-block">
    <div class="row">
        <div class="col-6">
            <h4>Motivo de ingreso</h4>
            <div><?php echo nl2br(htmlspecialchars($motivoMostrar)); ?></div>
        </div>
        <div class="col-6">
            <h4>Observaciones de recepción</h4>
            <div><?php echo nl2br(htmlspecialchars($obsMostrar)); ?></div>
        </div>
    </div>
    <div class="clear"></div>
</div>

<div class="box-block">
    <div class="row">
        <div class="col-12">
            <h4>Checklist del Equipo</h4>
            <div class="small-muted">
                <span class="text-primary"><strong>Azul:</strong></span> normal ·
                <span class="text-danger"><strong> Rojo:</strong></span> problema ·
                <span class="text-warning"><strong> Naranjo:</strong></span> advertencia
            </div>
        </div>

        <div class="col-4"><ul>
            <li><strong>Enciende:</strong> <?php echo checklistLabelPdf($orden['chk_enciende'] ?? 0, 'ok_if_yes'); ?></li>
            <li><strong>Errores de inicio:</strong> <?php echo checklistLabelPdf($orden['chk_error_inicio'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Rayas en pantalla:</strong> <?php echo checklistLabelPdf($orden['chk_rayas'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Manchas:</strong> <?php echo checklistLabelPdf($orden['chk_manchas'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Trizaduras:</strong> <?php echo checklistLabelPdf($orden['chk_trizaduras'] ?? 0, 'alert_if_yes'); ?></li>
        </ul></div>

        <div class="col-4"><ul>
            <li><strong>Pantalla con líneas:</strong> <?php echo checklistLabelPdf($orden['chk_lineas'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Abolladuras / golpes:</strong> <?php echo checklistLabelPdf($orden['chk_golpes'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Signos de intervención:</strong> <?php echo checklistLabelPdf($orden['chk_signos_intervencion'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Puertos con defectos físicos:</strong> <?php echo checklistLabelPdf($orden['chk_puertos_defectuosos'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Faltan tornillos:</strong> <?php echo checklistLabelPdf($orden['chk_tornillos'] ?? 0, 'alert_if_yes'); ?></li>
        </ul></div>

        <div class="col-4"><ul>
            <li><strong>Faltan soportes:</strong> <?php echo checklistLabelPdf($orden['chk_faltan_soportes'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Falta tapa slot:</strong> <?php echo checklistLabelPdf($orden['chk_falta_tapa_slot'] ?? 0, 'alert_if_yes'); ?></li>
            <li><strong>Garantía fábrica vigente:</strong> <?php echo checklistLabelPdf($orden['chk_garantia_fabrica'] ?? 0, 'ok_if_yes'); ?></li>
            <li><strong>Tiene patrón / password:</strong> <?php echo checklistLabelPdf($orden['chk_tiene_patron'] ?? 0, 'warn_if_yes'); ?></li>
        </ul></div>
    </div>
    <div class="clear"></div>
</div>

<?php if (!empty($evidenciasPrimera)): ?>
<div class="box-block">
    <h4>Evidencias</h4>
    <?php echo renderGaleriaEvidencias($evidenciasPrimera); ?>
    <?php if (!empty($evidenciasResto)): ?>
        <div class="small-muted" style="margin-top:2px;">
            Se adjuntan más evidencias en página(s) siguiente(s).
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="box-block avoid-break">
    <div class="row">
        <div class="col-12">
            <h4>Firma del cliente</h4>
            <div class="small-muted">
                <?php echo $tieneFirma ? ('Firmado por: ' . htmlspecialchars($clienteNombreFmt)) : 'Sin firma registrada'; ?>
            </div>
            <div class="firma-box"><?php echo $firmaHtml; ?></div>
        </div>
    </div>
    <div class="clear"></div>
</div>

<?php if (!empty($orden['usuario_recepcion'])): ?>
<div class="small-muted" style="margin-top:2px;">
    Recibido por: <?php echo htmlspecialchars((string)($orden['usuario_recepcion'] ?? '')); ?>
</div>
<?php endif; ?>

<?php if ($seguimientoUrl !== ''): ?>
    <div class="small-muted" style="margin-top:6px;">
        Puedes revisar el estado de tu equipo en línea:<br>
        <?php echo htmlspecialchars($seguimientoUrl); ?>
    </div>
<?php endif; ?>

<?php if ($pieOrdenTxt): ?>
    <hr>
    <div class="small-muted"><?php echo nl2br(htmlspecialchars($pieOrdenTxt)); ?></div>
<?php endif; ?>

<?php if (!empty($evidenciasResto)): ?>
    <div class="page-break"></div>
    <div class="titulo">
        <h2>Evidencias (continuación)</h2>
    </div>
    <div class="subtitulo">Orden N° <?php echo $numeroOrden; ?></div>

    <div class="box-block">
        <?php echo renderGaleriaEvidencias($evidenciasResto); ?>
    </div>
<?php endif; ?>

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

$dompdf->stream('orden_' . $numeroOrden . '.pdf', ['Attachment' => false]);
exit;
