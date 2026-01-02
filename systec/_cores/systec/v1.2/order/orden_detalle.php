<?php
// order/orden_detalle.php
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

/**
 * Normaliza texto en UTF-8 para comparar:
 * - quita tildes/diacríticos
 * - convierte a mayúsculas
 * - recorta espacios
 */
function normalize_ascii_upper(string $s): string {
    $s = trim($s);

    if (function_exists('iconv')) {
        $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
        if ($t !== false && $t !== '') $s = $t;
    }

    $map = [
        'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N',
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n'
    ];
    $s = strtr($s, $map);

    return strtoupper(trim($s));
}

// ------------------ POST: Cambiar estado ------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nuevo_estado'])) {
    $nuevoEstado      = trim($_POST['nuevo_estado'] ?? '');
    $comentarioEstado = trim($_POST['comentario_estado'] ?? '');

    if ($nuevoEstado !== '') {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE ordenes SET estado_actual = :estado WHERE id = :id");
            $stmt->execute([':estado' => $nuevoEstado, ':id' => $id]);

            $stmt = $pdo->prepare("
                INSERT INTO ordenes_estados (orden_id, estado, comentario, usuario)
                VALUES (:orden_id, :estado, :comentario, :usuario)
            ");
            $stmt->execute([
                ':orden_id'   => $id,
                ':estado'     => $nuevoEstado,
                ':comentario' => $comentarioEstado,
                ':usuario'    => $_SESSION['usuario_nombre'] ?? 'Sistema',
            ]);

            $pdo->commit();
            header('Location: ' . url('/order/orden_detalle.php?id=' . $id));
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }
}

// ------------------ Cargar orden ------------------
$stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Historial
$stmtHist = $pdo->prepare("
    SELECT fecha, estado, comentario, usuario
    FROM ordenes_estados
    WHERE orden_id = :id
    ORDER BY fecha DESC
");
$stmtHist->execute([':id' => $id]);
$historial_estados = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

// Evidencias mini
$stmtEv = $pdo->prepare("
    SELECT id, tipo, comentario, visible_cliente, fecha
    FROM ordenes_evidencias
    WHERE orden_id = :id
    ORDER BY id DESC
    LIMIT 4
");
$stmtEv->execute([':id' => $id]);
$evidenciasMini = $stmtEv->fetchAll(PDO::FETCH_ASSOC);
$hayEvidencias  = !empty($evidenciasMini);

// ------------------ WhatsApp/PDF/URLs ------------------
$equipo = trim(($orden['equipo_marca'] ?? '') . ' ' . ($orden['equipo_modelo'] ?? ''));
$equipo = $equipo !== '' ? $equipo : 'Equipo sin especificar';

$numeroOrden = str_pad((string)($orden['numero_orden'] ?? ''), 4, '0', STR_PAD_LEFT);

$costoTotal    = (float)($orden['costo_total'] ?? 0);
$costoTotalTxt = '$' . number_format($costoTotal, 0, ',', '.');

$estadoActualTexto = (string)($orden['estado_actual'] ?? '');
$estadoNorm = normalize_ascii_upper($estadoActualTexto);

$slugPlantilla = null;
switch ($estadoNorm) {
    case 'INGRESADO': $slugPlantilla = 'ingreso'; break;
    case 'DIAGNOSTICO': $slugPlantilla = 'diagnostico'; break;
    case 'EN REPARACION': $slugPlantilla = 'en_reparacion'; break;
    case 'EN ESPERA POR REPUESTOS': $slugPlantilla = 'espera_repuestos'; break;
    case 'REPARADO':
    case 'ENTREGADO': $slugPlantilla = 'listo_entrega'; break;
    default: $slugPlantilla = null; break;
}

$horarioTaller = '';
try {
    $stmtParam = $pdo->prepare("SELECT valor FROM parametros WHERE clave = 'horario_taller' LIMIT 1");
    $stmtParam->execute();
    $rowHorario = $stmtParam->fetch(PDO::FETCH_ASSOC);
    if ($rowHorario && isset($rowHorario['valor'])) $horarioTaller = trim((string)$rowHorario['valor']);
} catch (Exception $e) {}

// Base texto WhatsApp
$waTextBase = "Orden de servicio N° " . $numeroOrden
    . " - Cliente: " . ($orden['cliente_nombre'] ?? '')
    . " - Equipo: " . $equipo
    . " - Motivo: " . ($orden['motivo_ingreso'] ?? '');

// BASE URL ABSOLUTA
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Intentamos sacar el PATH real de la instancia desde url('/')
$path = '';
if (function_exists('url')) {
    $tmp  = url('/');
    $path = parse_url($tmp, PHP_URL_PATH) ?: $tmp;
}
$path = '/' . trim((string)$path, '/');
$path = ($path === '/') ? '' : $path;

$baseUrl = $scheme . $host . $path;

// PDF + Seguimiento
if (!empty($orden['token_publico'])) {
    $pdfUrl = $baseUrl . '/order/orden_pdf_publico.php?token=' . urlencode($orden['token_publico']);
} else {
    $pdfUrl = $baseUrl . '/order/orden_pdf.php?id=' . (int)$orden['id'];
}

if (!empty($orden['token_publico'])) {
    $seguimientoUrl = $baseUrl . '/order/seguimiento_orden.php?token=' . urlencode($orden['token_publico']);
} else {
    $seguimientoUrl = '';
}

$entregaPdfUrl = $baseUrl . '/order/entrega_pdf.php?id=' . (int)$orden['id'];

// Plantilla WA desde DB
$waText = $waTextBase;
if ($slugPlantilla !== null) {
    try {
        $stmtTpl = $pdo->prepare("
            SELECT contenido
            FROM mensajes_whatsapp
            WHERE slug = :slug AND activo = 1
            LIMIT 1
        ");
        $stmtTpl->execute([':slug' => $slugPlantilla]);
        $contenidoTpl = $stmtTpl->fetchColumn();

        if ($contenidoTpl) {
            $diagnosticoTxt = trim($orden['diagnostico'] ?? '');
            $waText = str_replace(
                ['{NOMBRE}','{EQUIPO}','{NUMERO_ORDEN}','{DIAGNOSTICO}','{COSTO_TOTAL}','{ESTADO}','{HORARIO_TALLER}'],
                [$orden['cliente_nombre'] ?? '', $equipo, $numeroOrden, $diagnosticoTxt, $costoTotalTxt, $estadoActualTexto, $horarioTaller],
                $contenidoTpl
            );
        }
    } catch (Exception $e) {}
}

$waText .= "\n\nComprobante PDF: " . $pdfUrl;

if ($estadoNorm === 'ENTREGADO') {
    $waText .= "\nComprobante de entrega (PDF): " . $entregaPdfUrl;
}

if ($seguimientoUrl !== '') {
    $waText .= "\nSeguimiento de tu orden: " . $seguimientoUrl;
} else {
    $waText .= "\nPara revisar el estado de tu equipo, ingresa a:"
        . "\n" . $baseUrl . "/order/seguimiento_orden.php"
        . "\ncon tu número de orden y el teléfono registrado.";
}

// Teléfono WA
$telBruto  = $orden['cliente_telefono'] ?? '';
$telLimpio = preg_replace('/\D+/', '', (string)$telBruto);

if ($telLimpio !== '') {
    if (strpos($telLimpio, '56') !== 0) {
        if (strlen($telLimpio) === 9 && $telLimpio[0] === '9') $telLimpio = '56' . $telLimpio;
        elseif (strlen($telLimpio) === 8) $telLimpio = '569' . $telLimpio;
    }
    $waUrl = "https://wa.me/" . $telLimpio . "?text=" . urlencode($waText);
} else {
    $waUrl = "https://web.whatsapp.com/send?text=" . urlencode($waText);
}

// Firma + flags
$firmaRuta = $orden['firma_ruta'] ?? null;
$ordenRecienCreada = (isset($_GET['creada']) && $_GET['creada'] == '1');
$requiereFirma     = !empty($orden['requiere_firma']);

// =========================
// Helpers UI (formato)
// =========================
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

function checklistLabel($value, $mode = 'normal') {
    $v = (int)$value;
    $txt = $v ? 'Sí' : 'No';

    $class = 'text-primary';
    switch ($mode) {
        case 'ok_if_yes':    $class = $v ? 'text-primary' : 'text-muted'; break;
        case 'alert_if_yes': $class = $v ? 'text-danger'  : 'text-primary'; break;
        case 'warn_if_yes':  $class = $v ? 'text-warning' : 'text-primary'; break;
    }
    return '<span class="'.$class.'">'.$txt.'</span>';
}

// Formatos visibles
$clienteNombreFmt = title_case_smart($orden['cliente_nombre'] ?? '');
$marcaFmt         = title_case_smart($orden['equipo_marca'] ?? '');
$modeloFmt        = title_case_smart($orden['equipo_modelo'] ?? '');
$rutFmt           = format_rut($orden['cliente_rut'] ?? '');

// IMEI display
$imei1Mostrar = trim((string)($orden['equipo_imei1'] ?? ''));
$imei1Mostrar = ($imei1Mostrar === '') ? '<span class="text-muted">Sin IMEI</span>' : htmlspecialchars($imei1Mostrar);

$imei2Mostrar = trim((string)($orden['equipo_imei2'] ?? ''));
$imei2Mostrar = ($imei2Mostrar === '') ? '<span class="text-muted">Sin 2do IMEI</span>' : htmlspecialchars($imei2Mostrar);

// Motivo / Obs fallback
$motivoBruto = trim((string)($orden['motivo_ingreso'] ?? ''));
$obsBruto    = trim((string)($orden['observaciones_recepcion'] ?? ''));

$motivoMostrar = ($motivoBruto !== '') ? $motivoBruto : 'Motivo no registrado.';
$obsMostrar    = ($obsBruto    !== '') ? $obsBruto    : 'Sin observaciones importantes.';

// Diagnóstico/costos visible solo si hay data real
$hayDiagnostico = (
    trim((string)($orden['diagnostico'] ?? '')) !== '' ||
    (float)($orden['costo_repuestos'] ?? 0) > 0 ||
    (float)($orden['costo_mano_obra'] ?? 0) > 0 ||
    (float)($orden['costo_total'] ?? 0) > 0
);

// Firma src (usa url() para respetar instancia)
$firmaSrc = '';
if (!empty($firmaRuta)) {
    $firmaSrc = url('/' . ltrim((string)$firmaRuta, '/'));
}

// =======================
// PARAMETROS (helper simple)
// =======================
function param_get(string $clave, string $default = ''): string {
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :c LIMIT 1");
        $s->execute([':c' => $clave]);
        $v = $s->fetchColumn();
        if ($v === false || $v === null) return $default;
        $v = trim((string)$v);
        return ($v === '') ? $default : $v;
    } catch (Exception $e) {
        return $default;
    }
}

/* =========================
   TICKET / IMPRESIÓN (PC + ASUS)
========================= */

// Detectar móvil vs PC
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);

// Links del ticket: si existe token, úsalo (sirve para público/ASUS también)
if (!empty($orden['token_publico'])) {
    $ticketViewUrl = url('/order/orden_ticket.php?token=' . urlencode($orden['token_publico']) . '&w=80');
} else {
    $ticketViewUrl = '../order/orden_ticket.php?id='.(int)$orden['id'].'&w=80';
}

// ASUS proxy (móvil) — POR CLIENTE (parametros)
// Por defecto: APAGADO (no afecta a otros clientes)
$POS_PRINT_ENABLED = (int)param_get('pos_print_enabled', '0'); // 0/1
$ASUS_PRINT_BASE   = trim(param_get('pos_print_base', ''));    // ej: http://192.168.2.157
$ASUS_PRINT_PATH   = trim(param_get('pos_print_path', '/pos_print/imprimir_pos.php')); // ej: /pos_print/imprimir_pos.php

// Normalizar base/path
$ASUS_PRINT_BASE = rtrim($ASUS_PRINT_BASE, "/ \t\n\r\0\x0B");
if ($ASUS_PRINT_PATH === '') $ASUS_PRINT_PATH = '/pos_print/imprimir_pos.php';
if ($ASUS_PRINT_PATH[0] !== '/') $ASUS_PRINT_PATH = '/' . $ASUS_PRINT_PATH;

// Ticket ABS (se lo pasamos a la ASUS)
$ticketAbsUrl = (!empty($orden['token_publico']))

    ? ($baseUrl . '/order/orden_ticket.php?token=' . urlencode($orden['token_publico']) . '&w=80')
    : ($baseUrl . '/order/orden_ticket.php?id='.(int)$orden['id'].'&w=80');

// URLs proxy (solo si está habilitado y hay base + path)
$printProxySinCostos = '';
$printProxyConCostos = '';
if ($POS_PRINT_ENABLED === 1 && $ASUS_PRINT_BASE !== '' && $ASUS_PRINT_PATH !== '') {

    $proxyEndpoint = $ASUS_PRINT_BASE . $ASUS_PRINT_PATH;

    $printProxySinCostos = $proxyEndpoint . '?url=' . urlencode($ticketAbsUrl);
    $printProxyConCostos = $proxyEndpoint . '?url=' . urlencode($ticketAbsUrl . '&costos=1');
}

// Layout
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.no-print.action-btns{gap:6px;}
/* Visibilidad por dispositivo (UA) */
.pc-only{display: <?php echo $isMobile ? 'none' : 'block'; ?>;}
.mobile-only{display: <?php echo $isMobile ? 'block' : 'none'; ?>;}

/* ✅ NUEVO: Header detalle orden responsive (solo visual) */
.order-head{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
}
.order-head-left .card-title{ margin-bottom:2px; }
.order-head-left .order-meta{ display:block; }

.order-head-actions{
  display:flex;
  flex-wrap:wrap;
  justify-content:flex-end;
  align-items:center;
}

/* Móvil: título arriba y botones abajo en grilla */
@media (max-width: 767.98px){
  .order-head{
    flex-direction:column;
    align-items:flex-start;
  }
  .order-head-left{
    width:100%;
  }
  .order-head-left .card-title{
    font-size:18px;
    line-height:1.15;
  }
  .order-head-left .order-meta{
    font-size:12px;
    line-height:1.25;
    margin-top:2px;
  }

  .order-head-actions{
    width:100%;
    justify-content:flex-start;
    gap:8px;
  }

  /* Grilla 2 columnas para botones */
  .order-head-actions .btn{
    flex:1 1 calc(50% - 8px);
    white-space:nowrap;
    text-align:center;
    padding:8px 10px;
    font-size:12px;
  }

  /* Volver full ancho y al final */
  .order-head-actions .btn-volver{
    flex:1 1 100%;
  }
}
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <h4 class="page-title no-print">Detalle de Orden</h4>

      <?php if ($ordenRecienCreada && $requiereFirma && empty($firmaRuta)): ?>
        <div class="alert alert-info no-print">
          Orden creada correctamente. Esta orden <strong>requiere la firma del cliente</strong>.
          Puede registrarla haciendo clic en <strong>"Registrar firma"</strong>.
        </div>
      <?php elseif ($ordenRecienCreada): ?>
        <div class="alert alert-success no-print">Orden creada correctamente.</div>
      <?php endif; ?>

      <div class="card">
        <!-- ✅ CAMBIO: header con layout responsive -->
        <div class="card-header order-head">
          <div class="order-head-left">
            <h4 class="card-title mb-0">Orden de Servicio N° <?php echo htmlspecialchars($numeroOrden); ?></h4>
            <small class="text-muted order-meta">
              Ingresada el <?php echo date('d-m-Y H:i', strtotime($orden['fecha_ingreso'])); ?>
              · Estado: <?php echo htmlspecialchars($estadoActualTexto); ?>
            </small>
          </div>

          <div class="no-print action-btns order-head-actions">

            <?php if (!$isMobile): ?>
              <!-- PC: imprime directo -->
              <a href="<?php
                echo url(
                  '/order/ticket_view.php?id='.(int)$orden['id']
                  .'&costos=0&autoprint=1'
                  .'&return=' . urlencode('order/orden_detalle.php?id='.(int)$orden['id'])
                );
              ?>"
              class="btn btn-secondary btn-sm"
              title="Imprimir Ticket (directo)">
                <i class="la la-print mr-1"></i> Imprimir Ticket
              </a>

              <a href="<?php
                echo url(
                  '/order/ticket_view.php?id='.(int)$orden['id']
                  .'&costos=1&autoprint=1'
                  .'&return=' . urlencode('order/orden_detalle.php?id='.(int)$orden['id'])
                );
              ?>"
              class="btn btn-outline-secondary btn-sm"
              title="Imprimir Ticket con costos">
                <i class="la la-money mr-1"></i> Ticket (costos)
              </a>
            <?php else: ?>
              <!-- MÓVIL: abre el modal (ASUS proxy) -->
              <button type="button"
                      class="btn btn-secondary btn-sm"
                      data-toggle="modal"
                      data-target="#modalPrintTicket"
                      title="Imprimir Ticket">
                <i class="la la-print mr-1"></i> Imprimir Ticket
              </button>
            <?php endif; ?>

            <a href="<?php echo htmlspecialchars($waUrl); ?>" target="_blank"
               class="btn btn-success btn-sm" title="Enviar por WhatsApp">
              <i class="la la-whatsapp mr-1"></i> WhatsApp
            </a>

            <a href="<?php echo url('/order/orden_diagnostico.php?id='.(int)$orden['id']); ?>"
               class="btn btn-primary btn-sm" title="Estado / costos">
              <i class="la la-edit mr-1"></i> Estado / costos
            </a>

            <a href="<?php echo url('/order/orden_pdf.php?id='.(int)$orden['id']); ?>" target="_blank"
               class="btn btn-info btn-sm" title="Abrir PDF">
              <i class="la la-file-pdf-o mr-1"></i> Ver PDF
            </a>

            <a href="<?php echo url('/order/entrega_pdf.php?id='.(int)$orden['id']); ?>" target="_blank"
               class="btn btn-success btn-sm" title="Comprobante entrega (PDF)">
              <i class="la la-check-circle mr-1"></i> Entrega (PDF)
            </a>

            <a href="<?php echo url('/order/orden_editar.php?id='.(int)$orden['id']); ?>"
               class="btn btn-warning btn-sm" title="Editar">
              <i class="la la-pencil mr-1"></i> Editar
            </a>            <?php
              // ✅ Return dinámico: vuelve a la pantalla desde donde llegó (si viene seteado)
              $return = trim((string)($_GET['return'] ?? ''));
              if ($return !== '') {
                  // Seguridad básica: solo rutas internas (sin http/https)
                  $return = ltrim($return, '/');
                  if (preg_match('~^(https?:)?//~i', $return)) $return = '';
              }

              $volverUrl = ($return !== '') ? url('/' . $return) : url('/order/ordenes_dia.php');
            ?>
            <?php if ($return !== ''): ?>
<a href="<?php echo htmlspecialchars($volverUrl); ?>"
   class="btn btn-outline-primary btn-sm btn-volver"
   title="Volver">
  <i class="la la-arrow-left mr-1"></i> Volver
</a>
<?php else: ?>
<a href="javascript:history.back();"
   class="btn btn-outline-primary btn-sm btn-volver"
   title="Volver">
  <i class="la la-arrow-left mr-1"></i> Volver
</a>
<?php endif; ?>
</div>
        </div>

        <div class="card-body">

          <style>
            .detalle-bloque{border-bottom:1px solid #eee;padding-bottom:12px;margin-bottom:14px;}
            .detalle-label{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#9ca3af;display:block;margin-bottom:2px;}
            .detalle-valor{font-size:14px;}
            .checklist-compact ul{font-size:13px;}
            .checklist-compact li{margin-bottom:2px;}
            .firma-cliente-box{
              border:1px solid #dddddd; border-radius:4px; background:#fff;
              padding:6px; max-width:320px; height:150px;
              display:flex; align-items:center; justify-content:center;
            }
            .firma-cliente-box img{max-width:100%; max-height:100%; object-fit:contain;}
            @media (max-width: 767.98px){.detalle-bloque{border-bottom:1px solid #f1f1f1;}}
          </style>

          <!-- DATOS CLIENTE + EQUIPO -->
          <div class="row detalle-bloque">
            <div class="col-md-6">
              <h5 class="mb-2">Datos del Cliente</h5>
              <div class="row">
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">Nombre</span>
                  <span class="detalle-valor"><strong><?php echo htmlspecialchars($clienteNombreFmt); ?></strong></span>
                </div>
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">Teléfono</span>
                  <span class="detalle-valor"><?php echo htmlspecialchars((string)($orden['cliente_telefono'] ?? '')); ?></span>
                </div>
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">RUT</span>
                  <span class="detalle-valor"><?php echo htmlspecialchars($rutFmt); ?></span>
                </div>
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">Correo</span>
                  <span class="detalle-valor"><?php echo htmlspecialchars((string)($orden['cliente_email'] ?? '')); ?></span>
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <h5 class="mb-2">Datos del Equipo</h5>
              <div class="row">
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">Marca</span>
                  <span class="detalle-valor"><strong><?php echo htmlspecialchars($marcaFmt); ?></strong></span>
                </div>
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">Modelo</span>
                  <span class="detalle-valor"><?php echo htmlspecialchars($modeloFmt); ?></span>
                </div>
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">IMEI 1</span>
                  <span class="detalle-valor"><?php echo $imei1Mostrar; ?></span>
                </div>
                <div class="col-sm-6 mb-2">
                  <span class="detalle-label">IMEI 2</span>
                  <span class="detalle-valor"><?php echo $imei2Mostrar; ?></span>
                </div>
                <div class="col-sm-12 mb-2">
                  <span class="detalle-label">Clave / Patrón</span>
                  <span class="detalle-valor"><?php echo htmlspecialchars((string)($orden['equipo_clave'] ?? '')); ?></span>
                </div>
              </div>
            </div>
          </div>

          <!-- MOTIVO + OBS -->
          <div class="row detalle-bloque">
            <div class="col-md-6">
              <span class="detalle-label">Motivo de ingreso</span>
              <p class="mb-0 detalle-valor"><?php echo nl2br(htmlspecialchars($motivoMostrar)); ?></p>
            </div>
            <div class="col-md-6">
              <span class="detalle-label">Observaciones de recepción</span>
              <p class="mb-0 detalle-valor"><?php echo nl2br(htmlspecialchars($obsMostrar)); ?></p>
            </div>
          </div>

          <!-- DIAGNÓSTICO + COSTOS (si aplica) -->
          <?php if ($hayDiagnostico): ?>
            <div class="row detalle-bloque">
              <div class="col-md-6">
                <span class="detalle-label">Diagnóstico técnico</span>
                <p class="mb-0 detalle-valor"><?php echo nl2br(htmlspecialchars((string)($orden['diagnostico'] ?? ''))); ?></p>
              </div>
              <div class="col-md-6">
                <span class="detalle-label">Costos</span>
                <ul class="list-unstyled mb-1 detalle-valor">
                  <li><strong>Repuestos:</strong> $<?php echo number_format((float)($orden['costo_repuestos'] ?? 0), 0, ',', '.'); ?></li>
                  <li><strong>Mano de obra:</strong> $<?php echo number_format((float)($orden['costo_mano_obra'] ?? 0), 0, ',', '.'); ?></li>
                  <li><strong>Total:</strong> $<?php echo number_format((float)($orden['costo_total'] ?? 0), 0, ',', '.'); ?></li>
                </ul>
                <small class="text-muted">Estos valores corresponden al diagnóstico técnico de la orden.</small>
              </div>
            </div>
          <?php endif; ?>

          <!-- CHECKLIST -->
          <div class="detalle-bloque checklist-compact">
            <h5 class="mb-1">Checklist del Equipo</h5>
            <p class="text-muted small mb-2">
              <span class="text-primary font-weight-bold">Azul:</span> estado normal ·
              <span class="text-danger font-weight-bold">Rojo:</span> problema ·
              <span class="text-warning font-weight-bold">Naranjo:</span> advertencia
            </p>
            <div class="row">
              <div class="col-md-4">
                <ul class="list-unstyled mb-0">
                  <li><strong>Enciende:</strong> <?php echo checklistLabel($orden['chk_enciende'] ?? 0, 'ok_if_yes'); ?></li>
                  <li><strong>Errores de inicio:</strong> <?php echo checklistLabel($orden['chk_error_inicio'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Rayas en pantalla:</strong> <?php echo checklistLabel($orden['chk_rayas'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Manchas:</strong> <?php echo checklistLabel($orden['chk_manchas'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Trizaduras:</strong> <?php echo checklistLabel($orden['chk_trizaduras'] ?? 0, 'alert_if_yes'); ?></li>
                </ul>
              </div>
              <div class="col-md-4">
                <ul class="list-unstyled mb-0">
                  <li><strong>Pantalla con líneas:</strong> <?php echo checklistLabel($orden['chk_lineas'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Abolladuras / golpes:</strong> <?php echo checklistLabel($orden['chk_golpes'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Signos de intervención:</strong> <?php echo checklistLabel($orden['chk_signos_intervencion'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Puertos con defectos físicos:</strong> <?php echo checklistLabel($orden['chk_puertos_defectuosos'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Faltan tornillos:</strong> <?php echo checklistLabel($orden['chk_tornillos'] ?? 0, 'alert_if_yes'); ?></li>
                </ul>
              </div>
              <div class="col-md-4">
                <ul class="list-unstyled mb-0">
                  <li><strong>Faltan soportes:</strong> <?php echo checklistLabel($orden['chk_faltan_soportes'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Falta tapa slot:</strong> <?php echo checklistLabel($orden['chk_falta_tapa_slot'] ?? 0, 'alert_if_yes'); ?></li>
                  <li><strong>Garantía de fábrica vigente:</strong> <?php echo checklistLabel($orden['chk_garantia_fabrica'] ?? 0, 'ok_if_yes'); ?></li>
                  <li><strong>Tiene patrón / password:</strong> <?php echo checklistLabel($orden['chk_tiene_patron'] ?? 0, 'warn_if_yes'); ?></li>
                </ul>
              </div>
            </div>
          </div>

          <!-- FIRMA + EVIDENCIAS -->
          <div class="row detalle-bloque no-print">
            <div class="col-md-6">
              <h5 class="mb-2">Firma del cliente</h5>

              <?php if (!empty($firmaRuta) && $firmaSrc !== ''): ?>
                <p class="mb-1"><small class="text-muted">Firmado por: <?php echo htmlspecialchars($clienteNombreFmt); ?></small></p>
                <div class="firma-cliente-box">
                  <img src="<?php echo htmlspecialchars($firmaSrc); ?>" alt="Firma del cliente">
                </div>
              <?php else: ?>
                <p class="text-muted mb-2">
                  No hay firma registrada para esta orden.
                  <?php if ($requiereFirma): ?>
                    Esta orden requiere firma del cliente. Regístrela antes de entregar el equipo.
                  <?php endif; ?>
                </p>

                <?php if ($requiereFirma): ?>
                  <a href="<?php echo url('/order/firma_registrar.php?id='.(int)$orden['id']); ?>" class="btn btn-success btn-sm no-print">
                    Registrar firma ahora
                  </a>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <div class="col-md-6">
              <h5 class="mb-2">Evidencias</h5>

              <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
  <a href="<?php echo url('/order/orden_evidencias.php?id='.(int)$orden['id']); ?>" class="btn btn-dark btn-sm">
    Abrir evidencias
  </a>

  <?php if (!$hayEvidencias): ?>
    <span class="text-muted small ml-2">Aún no hay fotos registradas.</span>
  <?php else: ?>
    <span class="text-muted small ml-2">Hay evidencias registradas (mostrando últimas <?php echo count($evidenciasMini); ?>).</span>
  <?php endif; ?>
</div>
<?php if ($hayEvidencias): ?>
                <div class="row">
                  <?php foreach ($evidenciasMini as $ev): ?>
                    <div class="col-12 col-md-6 mb-3">
                      <a href="<?php echo url('/order/evidencia_view.php?id='.(int)$ev['id']); ?>">
                        <img
                          src="<?php echo url('/order/evidencia_ver.php?id='.(int)$ev['id'].'&thumb=1'); ?>"
                          alt="Evidencia"
                          style="width:100%; height:190px; object-fit:cover; border-radius:10px; border:1px solid #eee; background:#f8f9fa;"
                        >
                      </a>

                      <div class="small mt-2">
                        <div>
                          <strong><?php echo htmlspecialchars((string)($ev['tipo'] ?? '')); ?></strong>
                          <?php if ((int)($ev['visible_cliente'] ?? 0) === 1): ?>
                            <span class="text-success">· Visible cliente</span>
                          <?php else: ?>
                            <span class="text-warning">· Solo interno</span>
                          <?php endif; ?>
                        </div>

                        <?php if (!empty($ev['comentario'])): ?>
                          <div class="text-muted"><?php echo htmlspecialchars((string)$ev['comentario']); ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

              <small class="text-muted">Consejo: Usa evidencias para humedad, sulfatación, golpes, rayas o intervención previa.</small>
            </div>
          </div>

          <!-- ESTADO + HISTORIAL -->
          <div class="row mb-4 no-print">
            <div class="col-md-6">
              <h5>Estado de la orden</h5>
              <p class="mb-1"><strong>Estado actual:</strong> <?php echo htmlspecialchars($estadoActualTexto); ?></p>
              <p class="text-muted small">Para modificar el estado o registrar diagnóstico y costos, usa <strong>"Estado / costos"</strong>.</p>
            </div>

            <div class="col-md-6">
              <h5>Historial de estados</h5>

              <?php if (empty($historial_estados)): ?>
                <p class="text-muted mb-0">Sin cambios de estado registrados.</p>
              <?php else: ?>
                <div class="table-responsive">
                  <table class="table table-sm mb-0">
                    <thead>
                      <tr>
                        <th style="width:120px;">Fecha</th>
                        <th style="width:160px;">Estado</th>
                        <th>Comentario</th>
                        <th style="width:120px;">Usuario</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($historial_estados as $h): ?>
                        <tr>
                          <td><?php echo date('d-m-Y H:i', strtotime((string)$h['fecha'])); ?></td>
                          <td><?php echo htmlspecialchars((string)$h['estado']); ?></td>
                          <td><?php echo htmlspecialchars((string)$h['comentario']); ?></td>
                          <td><?php echo htmlspecialchars((string)$h['usuario']); ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <hr>
          <small class="text-muted">Recibido por: <?php echo htmlspecialchars((string)($orden['usuario_recepcion'] ?? '')); ?></small>

        </div><!-- /card-body -->
      </div><!-- /card -->
    </div><!-- /container-fluid -->
  </div><!-- /content -->
</div><!-- /main-panel -->

<!-- MODAL: IMPRIMIR TICKET -->
<div class="modal fade" id="modalPrintTicket" tabindex="-1" role="dialog" aria-labelledby="modalPrintTicketLabel" aria-hidden="true">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <h6 class="modal-title" id="modalPrintTicketLabel">Imprimir Ticket</h6>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">

        <!-- MÓVIL: imprime vía ASUS proxy (solo si está habilitado por cliente) -->
        <?php if ($POS_PRINT_ENABLED === 1 && $ASUS_PRINT_BASE !== '' && $ASUS_PRINT_PATH !== '' && $printProxySinCostos !== ''): ?>
          <div class="mobile-only">
            <small class="text-muted d-block mb-2">
              Imprimir desde móvil vía POS80:
            </small>

            <a class="btn btn-dark btn-sm btn-block mb-2"
               href="<?php echo htmlspecialchars($printProxySinCostos); ?>"
               target="print_bg"
               onclick="return sendPrintBg(this.href);">
              <i class="la la-print mr-1"></i> Impresión — Sin costos
            </a>

            <a class="btn btn-outline-secondary btn-sm btn-block"
               href="<?php echo htmlspecialchars($printProxyConCostos); ?>"
               target="print_bg"
               onclick="return sendPrintBg(this.href);">
              <i class="la la-money mr-1"></i> Impresión — Con costos
            </a>

            <iframe name="print_bg" id="print_bg" style="display:none;width:0;height:0;border:0;"></iframe>

            <div id="print_bg_msg" class="alert alert-success mt-2 mb-0" style="display:none;">
              ✅ Enviado a imprimir POS80.
            </div>

            <script>
            function sendPrintBg(url){
              var f = document.getElementById('print_bg');
              if (f) f.src = url;

              var m = document.getElementById('print_bg_msg');
              if (m){
                m.style.display = 'block';
                setTimeout(function(){ m.style.display = 'none'; }, 2000);
              }
              return false;
            }
            </script>

            <small class="text-muted d-block mt-2">
              Base: <?php echo htmlspecialchars($ASUS_PRINT_BASE); ?><br>
              Path: <?php echo htmlspecialchars($ASUS_PRINT_PATH); ?>
            </small>
          </div>
        <?php endif; ?>

      </div><!-- /modal-body -->

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
