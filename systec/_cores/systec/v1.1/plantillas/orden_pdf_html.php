<?php
// plantillas/orden_pdf_html.php
//
// Variables que vienen desde orden_pdf.php:
// $orden, $firmaRutaAbsoluta

// ---------- Helpers ----------
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

/**
 * Devuelve “Sí / No” coloreado:
 *  - ok_if_yes:       Sí = azul, No = gris
 *  - alert_if_yes:    Sí = rojo, No = azul
 *  - warn_if_yes:     Sí = naranjo, No = azul
 */
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

// ---------- Datos base ----------
$numeroOrden  = str_pad((string)($orden['numero_orden'] ?? 0), 4, '0', STR_PAD_LEFT);
$fechaIngreso = !empty($orden['fecha_ingreso']) ? date('d-m-Y H:i', strtotime($orden['fecha_ingreso'])) : '';
$estadoActual = htmlspecialchars((string)($orden['estado_actual'] ?? 'INGRESADO'));

// Cliente/equipo “bonitos”
$clienteNombreFmt = title_case_smart($orden['cliente_nombre'] ?? '');
$marcaFmt         = title_case_smart($orden['equipo_marca']   ?? '');
$modeloFmt        = title_case_smart($orden['equipo_modelo']  ?? '');
$rutFmt           = format_rut($orden['cliente_rut'] ?? '');

$equipo = trim(($marcaFmt ?: '') . ' ' . ($modeloFmt ?: ''));
if ($equipo === '') $equipo = 'Equipo sin especificar';

// IMEIs con fallback
$imei1Mostrar = trim((string)($orden['equipo_imei1'] ?? ''));
$imei1Mostrar = ($imei1Mostrar === '') ? '<span class="text-muted">Sin IMEI</span>' : htmlspecialchars($imei1Mostrar);

$imei2Mostrar = trim((string)($orden['equipo_imei2'] ?? ''));
$imei2Mostrar = ($imei2Mostrar === '') ? '<span class="text-muted">Sin 2do IMEI</span>' : htmlspecialchars($imei2Mostrar);

// Motivo/Obs con fallback
$motivoBruto = trim((string)($orden['motivo_ingreso'] ?? ''));
$obsBruto    = trim((string)($orden['observaciones_recepcion'] ?? ''));

$motivoMostrar = $motivoBruto !== '' ? $motivoBruto : 'Motivo no registrado.';
$obsMostrar    = $obsBruto    !== '' ? $obsBruto    : 'Sin observaciones importantes.';

// Diagnóstico/costos
$hayDiagnostico = (
    trim((string)($orden['diagnostico'] ?? '')) !== '' ||
    (float)($orden['costo_repuestos'] ?? 0) > 0 ||
    (float)($orden['costo_mano_obra'] ?? 0) > 0 ||
    (float)($orden['costo_total'] ?? 0) > 0
);

// ---------- Firma: base64 (Dompdf-friendly) ----------
$firmaHtml  = '<span class="text-muted">Sin firma registrada.</span>';
$tieneFirma = false;

if (!empty($firmaRutaAbsoluta) && is_file($firmaRutaAbsoluta)) {
    $imgData = @file_get_contents($firmaRutaAbsoluta);
    if ($imgData !== false) {
        $base64 = base64_encode($imgData);

        // Deducir mime por extensión (por si no es png)
        $ext  = strtolower(pathinfo($firmaRutaAbsoluta, PATHINFO_EXTENSION));
        $mime = ($ext === 'jpg' || $ext === 'jpeg') ? 'image/jpeg' : 'image/png';

        $firmaHtml  = '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Firma del cliente" style="max-width:100%; max-height:140px;">';
        $tieneFirma = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Servicio N° <?php echo $numeroOrden; ?></title>
    <style>
        @page { margin: 20mm; }

        * {
            box-sizing: border-box;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
        }

        body { color: #222; }

        h2,h3,h4 { margin: 0 0 4px 0; padding: 0; }

        .titulo { text-align: center; margin-bottom: 8px; }
        .subtitulo { text-align: center; font-size: 10px; color: #666; margin-bottom: 12px; }

        .row { width: 100%; clear: both; }
        .col-6 { width: 50%; float: left; padding-right: 8px; }
        .col-4 { width: 33.3333%; float: left; padding-right: 8px; }
        .col-12 { width: 100%; clear: both; }

        .box-block {
            border: 1px solid #dddddd;
            border-radius: 4px;
            padding: 8px;
            margin-bottom: 10px;
        }

        .label { font-size: 10px; color: #777; }
        .valor-strong { font-weight: bold; }

        ul { margin: 3px 0 0 0; padding-left: 12px; }
        ul li { margin-bottom: 2px; }

        .small-muted { font-size: 9px; color: #777; }

        .firma-box {
            border: 1px solid #ccc;
            height: 130px;
            padding: 5px;
            text-align: center;
        }

        .clear { clear: both; }
        hr { border: 0; border-top: 1px solid #ccc; margin: 8px 0; }

        /* Colores tipo bootstrap */
        .text-primary { color: #007bff; }
        .text-danger  { color: #dc3545; }
        .text-warning { color: #ff9800; }
        .text-muted   { color: #999999; }
    </style>
</head>
<body>

    <div class="titulo">
        <h2>Orden de Servicio N° <?php echo $numeroOrden; ?></h2>
    </div>
    <div class="subtitulo">
        Ingresada el <?php echo htmlspecialchars($fechaIngreso); ?> · Estado: <?php echo $estadoActual; ?>
    </div>

    <!-- Cliente + Equipo -->
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
                <div><span class="label">Equipo:</span> <span class="valor-strong"><?php echo htmlspecialchars($equipo); ?></span></div>
                <div><span class="label">IMEI 1:</span> <span><?php echo $imei1Mostrar; ?></span></div>
                <div><span class="label">IMEI 2:</span> <span><?php echo $imei2Mostrar; ?></span></div>
                <div><span class="label">Clave / Patrón:</span> <span><?php echo htmlspecialchars((string)($orden['equipo_clave'] ?? '')); ?></span></div>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Motivo / Observaciones -->
    <div class="box-block">
        <div class="row">
            <div class="col-6">
                <h4>Motivo de ingreso</h4>
                <p><?php echo nl2br(htmlspecialchars($motivoMostrar)); ?></p>
            </div>
            <div class="col-6">
                <h4>Observaciones de recepción</h4>
                <p><?php echo nl2br(htmlspecialchars($obsMostrar)); ?></p>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Diagnóstico / Costos -->
    <?php if ($hayDiagnostico): ?>
        <div class="box-block">
            <div class="row">
                <div class="col-6">
                    <h4>Diagnóstico técnico</h4>
                    <p><?php echo nl2br(htmlspecialchars((string)($orden['diagnostico'] ?? ''))); ?></p>
                </div>
                <div class="col-6">
                    <h4>Costos</h4>
                    <ul style="list-style:none; padding-left:0; margin:0;">
                        <li><strong>Repuestos:</strong> $<?php echo number_format((float)($orden['costo_repuestos'] ?? 0), 0, ',', '.'); ?></li>
                        <li><strong>Mano de obra:</strong> $<?php echo number_format((float)($orden['costo_mano_obra'] ?? 0), 0, ',', '.'); ?></li>
                        <li><strong>Total:</strong> $<?php echo number_format((float)($orden['costo_total'] ?? 0), 0, ',', '.'); ?></li>
                    </ul>
                    <div class="small-muted">Estos valores corresponden al diagnóstico técnico de la orden.</div>
                </div>
            </div>
            <div class="clear"></div>
        </div>
    <?php endif; ?>

    <!-- Checklist (3 columnas como el PDF nuevo) -->
    <div class="box-block">
        <div class="row">
            <div class="col-12">
                <h4>Checklist del Equipo</h4>
                <p class="small-muted">
                    <span class="text-primary"><strong>Azul:</strong></span> estado normal ·
                    <span class="text-danger"><strong> Rojo:</strong></span> problema ·
                    <span class="text-warning"><strong> Naranjo:</strong></span> advertencia
                </p>
            </div>

            <div class="col-4">
                <ul>
                    <li><strong>Enciende:</strong> <?php echo checklistLabelPdf($orden['chk_enciende'] ?? 0, 'ok_if_yes'); ?></li>
                    <li><strong>Errores de inicio:</strong> <?php echo checklistLabelPdf($orden['chk_error_inicio'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Rayas en pantalla:</strong> <?php echo checklistLabelPdf($orden['chk_rayas'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Manchas:</strong> <?php echo checklistLabelPdf($orden['chk_manchas'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Trizaduras:</strong> <?php echo checklistLabelPdf($orden['chk_trizaduras'] ?? 0, 'alert_if_yes'); ?></li>
                </ul>
            </div>

            <div class="col-4">
                <ul>
                    <li><strong>Pantalla con líneas:</strong> <?php echo checklistLabelPdf($orden['chk_lineas'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Abolladuras / golpes:</strong> <?php echo checklistLabelPdf($orden['chk_golpes'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Signos de intervención:</strong> <?php echo checklistLabelPdf($orden['chk_signos_intervencion'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Puertos con defectos físicos:</strong> <?php echo checklistLabelPdf($orden['chk_puertos_defectuosos'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Faltan tornillos:</strong> <?php echo checklistLabelPdf($orden['chk_tornillos'] ?? 0, 'alert_if_yes'); ?></li>
                </ul>
            </div>

            <div class="col-4">
                <ul>
                    <li><strong>Faltan soportes:</strong> <?php echo checklistLabelPdf($orden['chk_faltan_soportes'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Falta tapa slot:</strong> <?php echo checklistLabelPdf($orden['chk_falta_tapa_slot'] ?? 0, 'alert_if_yes'); ?></li>
                    <li><strong>Garantía fábrica vigente:</strong> <?php echo checklistLabelPdf($orden['chk_garantia_fabrica'] ?? 0, 'ok_if_yes'); ?></li>
                    <li><strong>Tiene patrón / password:</strong> <?php echo checklistLabelPdf($orden['chk_tiene_patron'] ?? 0, 'warn_if_yes'); ?></li>
                </ul>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <!-- Firma -->
    <div class="box-block">
        <div class="row">
            <div class="col-12">
                <h4>Firma del cliente</h4>
                <div class="small-muted">
                    <?php if ($tieneFirma): ?>
                        Firmado por: <?php echo htmlspecialchars($clienteNombreFmt); ?>
                    <?php else: ?>
                        Sin firma registrada
                    <?php endif; ?>
                </div>

                <div class="firma-box">
                    <?php echo $firmaHtml; ?>
                </div>
            </div>
        </div>
        <div class="clear"></div>
    </div>

    <div class="small-muted">
        Recibido por: <?php echo htmlspecialchars((string)($orden['usuario_recepcion'] ?? '')); ?>
    </div>

</body>
</html>
