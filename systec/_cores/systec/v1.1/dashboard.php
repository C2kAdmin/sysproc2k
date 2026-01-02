<?php
// dashboard.php
require_once __DIR__ . '/config/config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

// -------------------- FECHAS --------------------
$hoy    = date('Y-m-d');
$hace30 = date('Y-m-d', strtotime('-30 days'));

/**
 * Normaliza un estado:
 * - UPPER
 * - quita tildes (ÁÉÍÓÚ -> AEIOU)
 * - colapsa espacios
 */
function estado_norm($txt)
{
    $txt = mb_strtoupper(trim((string)$txt), 'UTF-8');
    $txt = preg_replace('/\s+/', ' ', $txt);

    $buscar = ['Á','É','Í','Ó','Ú'];
    $reempl = ['A','E','I','O','U'];
    return str_replace($buscar, $reempl, $txt);
}

// -------------------- CONTADORES PRINCIPALES --------------------

// 1) Ingresadas hoy
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM ordenes
    WHERE DATE(fecha_ingreso) = :hoy
");
$stmt->execute([':hoy' => $hoy]);
$ingresadasHoy = (int)$stmt->fetchColumn();

// 2) Pendientes por diagnóstico (INGRESADO)
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE UPPER(TRIM(estado_actual)) = 'INGRESADO'
");
$pendientesDiagnostico = (int)$stmt->fetchColumn();

// Helper SQL: normaliza estado_actual quitando tildes
$SQL_ESTADO_NORM = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(estado_actual)),
                    'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U')";

// 3) En diagnóstico (con y sin tilde)
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE {$SQL_ESTADO_NORM} = 'DIAGNOSTICO'
");
$enDiagnostico = (int)$stmt->fetchColumn();

// 4) En reparación / repuestos
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE {$SQL_ESTADO_NORM} IN ('EN REPARACION','EN ESPERA POR REPUESTOS')
");
$enReparacion = (int)$stmt->fetchColumn();

// 5) Listos para entregar
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE {$SQL_ESTADO_NORM} = 'REPARADO'
");
$listosEntregar = (int)$stmt->fetchColumn();

// 6) Entregadas
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE {$SQL_ESTADO_NORM} = 'ENTREGADO'
");
$entregadas = (int)$stmt->fetchColumn();

// 7) Órdenes con evidencias
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT orden_id)
    FROM ordenes_evidencias
");
$conEvidencias = (int)$stmt->fetchColumn();

// 8) Órdenes con firma registrada
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE firma_ruta IS NOT NULL
      AND firma_ruta <> ''
");
$conFirma = (int)$stmt->fetchColumn();

// 9) Firmas pendientes (✅ SOLO si requiere firma + no tiene firma + NO entregadas)
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM ordenes
    WHERE requiere_firma = 1
      AND (firma_ruta IS NULL OR firma_ruta = '')
      AND {$SQL_ESTADO_NORM} <> 'ENTREGADO'
");
$firmasPendientes = (int)$stmt->fetchColumn();

// -------------------- RECORDATORIOS (ANTIGÜEDAD) --------------------
$cut24h = date('Y-m-d H:i:s', strtotime('-24 hours'));
$cut48h = date('Y-m-d H:i:s', strtotime('-48 hours'));
$cut72h = date('Y-m-d H:i:s', strtotime('-72 hours'));
$cut7d  = date('Y-m-d H:i:s', strtotime('-7 days'));

$ingresado24   = 0;
$diagnostico48 = 0;
$reparacion72  = 0;
$espera7d      = 0;
$reparado72    = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE UPPER(TRIM(estado_actual)) = 'INGRESADO'
          AND fecha_ingreso <= :cut
    ");
    $stmt->execute([':cut' => $cut24h]);
    $ingresado24 = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE {$SQL_ESTADO_NORM} = 'DIAGNOSTICO'
          AND fecha_ingreso <= :cut
    ");
    $stmt->execute([':cut' => $cut48h]);
    $diagnostico48 = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE {$SQL_ESTADO_NORM} = 'EN REPARACION'
          AND fecha_ingreso <= :cut
    ");
    $stmt->execute([':cut' => $cut72h]);
    $reparacion72 = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE {$SQL_ESTADO_NORM} = 'EN ESPERA POR REPUESTOS'
          AND fecha_ingreso <= :cut
    ");
    $stmt->execute([':cut' => $cut7d]);
    $espera7d = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE {$SQL_ESTADO_NORM} = 'REPARADO'
          AND fecha_ingreso <= :cut
    ");
    $stmt->execute([':cut' => $cut72h]);
    $reparado72 = (int)$stmt->fetchColumn();

} catch (Exception $e) {}

$hasta_1d = date('Y-m-d', strtotime('-1 day'));
$hasta_2d = date('Y-m-d', strtotime('-2 days'));
$hasta_3d = date('Y-m-d', strtotime('-3 days'));
$hasta_7d = date('Y-m-d', strtotime('-7 days'));

/* ✅ formateo "bonito" (Nombre) */
function title_case_smart($str) {
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

/* ✅ helper: link de vuelta (dashboard) */
function with_return($u, $return = 'dashboard.php') {
    $sep = (strpos($u, '?') === false) ? '?' : '&';
    return $u . $sep . 'return=' . urlencode($return);
}

/* ✅ WhatsApp helpers */
function wa_clean_chile($tel){
    $t = preg_replace('/\D+/', '', (string)$tel);
    if ($t === '') return '';
    if (strpos($t, '56') !== 0) {
        if (strlen($t) === 9 && $t[0] === '9') $t = '56' . $t;
        elseif (strlen($t) === 8) $t = '569' . $t;
    }
    return $t;
}

function wa_abs_url($u){
    $u = (string)$u;
    if ($u === '') return '';
    if (preg_match('~^https?://~i', $u)) return $u;

    $proto = (string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = ($proto !== '' ? $proto : ($isHttps ? 'https' : 'http')) . '://';

    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host === '') return $u;

    if ($u[0] !== '/') $u = '/' . $u;
    return $scheme . $host . $u;
}

/* --------------------
   ✅ RECORDATORIOS: TOP 5 (global)
   - junta hasta 5 por tipo
   - ordena por más antiguo
   - muestra solo 5 en la tabla
-------------------- */
$recAll  = [];
$recTop  = [];
$recItems = [];

function rec_fetch($estadoNorm, $cutDT, $tipoLabel, $badgeClass, $listUrl, &$dest) {
    global $pdo, $SQL_ESTADO_NORM;

    try {
        $stmt = $pdo->prepare("            SELECT id, numero_orden, cliente_nombre, cliente_telefono, token_publico, fecha_ingreso
            FROM ordenes
WHERE {$SQL_ESTADO_NORM} = :estado
              AND fecha_ingreso <= :cut
            ORDER BY fecha_ingreso ASC
            LIMIT 5
        ");
        $stmt->execute([
            ':estado' => $estadoNorm,
            ':cut'    => $cutDT
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $r) {            $dest[] = [
                'tipo'          => $tipoLabel,
                'badge'         => $badgeClass,
                'list_url'      => $listUrl,
                'id'            => (int)($r['id'] ?? 0),
                'numero_orden'  => (int)($r['numero_orden'] ?? 0),
                'cliente'       => title_case_smart($r['cliente_nombre'] ?? ''),
                'telefono'      => (string)($r['cliente_telefono'] ?? ''),
                'token_publico' => (string)($r['token_publico'] ?? ''),
                'fecha_ingreso' => (string)($r['fecha_ingreso'] ?? '')
            ];
}
    } catch (Exception $e) {}
}

rec_fetch('INGRESADO',               $cut24h, 'Ingresado +24h',     'primary',  with_return(url('/order/ordenes.php?estado=INGRESADO&fecha_hasta='.$hasta_1d)), $recAll);
rec_fetch('DIAGNOSTICO',             $cut48h, 'Diagnóstico +48h',   'warning',  with_return(url('/order/ordenes.php?estado=DIAGNOSTICO&fecha_hasta='.$hasta_2d)), $recAll);
rec_fetch('EN REPARACION',           $cut72h, 'Reparación +72h',    'info',     with_return(url('/order/ordenes.php?estado=EN%20REPARACION&fecha_hasta='.$hasta_3d)), $recAll);
rec_fetch('EN ESPERA POR REPUESTOS', $cut7d,  'Repuestos +7d',      'dark',     with_return(url('/order/ordenes.php?estado=EN%20ESPERA%20POR%20REPUESTOS&fecha_hasta='.$hasta_7d)), $recAll);
rec_fetch('REPARADO',                $cut72h, 'Listas +72h',        'success',  with_return(url('/order/ordenes.php?estado=REPARADO&fecha_hasta='.$hasta_3d)), $recAll);

if (!empty($recAll)) {
    usort($recAll, function($a, $b){
        return strcmp((string)$a['fecha_ingreso'], (string)$b['fecha_ingreso']);
    });
    $recTop = array_slice($recAll, 0, 5);
}

/* ✅ Adaptar a la estructura usada en la tabla (recItems) */
// ✅ BASE URL ABSOLUTA (para links en WhatsApp)
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

foreach ($recTop as $r) {
$rid = (int)($r['id'] ?? 0);
    $num = (int)($r['numero_orden'] ?? 0);
    if ($num <= 0) $num = $rid;    $numFmt = str_pad((string)$num, 4, '0', STR_PAD_LEFT);

    $segUrl = '';
$tok = (string)($r['token_publico'] ?? '');
if ($tok !== '') {
    $segUrl = wa_abs_url(url('/order/seguimiento_orden.php?token=' . urlencode($tok)));
}

$tipo = (string)($r['tipo'] ?? '');

$estadoCliente = 'En proceso';
if (stripos($tipo, 'Ingresado') !== false) {
    $estadoCliente = 'Recibido';
} elseif (stripos($tipo, 'Diagn') !== false) {
    $estadoCliente = 'En diagnóstico';
} elseif (stripos($tipo, 'Repar') !== false) {
    $estadoCliente = 'En reparación';
} elseif (stripos($tipo, 'Repuestos') !== false) {
    $estadoCliente = 'En espera de repuestos';
} elseif (stripos($tipo, 'Listas') !== false) {
    $estadoCliente = 'Listo para entrega';
}

$tiempoTxt = '';
if (preg_match('/\+(\d+)\s*([hd])/i', $tipo, $m)) {
    $n = (int)$m[1];
    $u = strtolower($m[2]);
    if ($u === 'h') $tiempoTxt = "hace más de {$n} " . ($n === 1 ? 'hora' : 'horas');
    if ($u === 'd') $tiempoTxt = "hace más de {$n} " . ($n === 1 ? 'día' : 'días');
}

$msg = "Orden N° {$numFmt}\nEstado: {$estadoCliente}";
if ($tiempoTxt !== '') $msg .= " ({$tiempoTxt})";
if ($segUrl !== '') $msg .= "\nSeguimiento: {$segUrl}";
$tel = wa_clean_chile((string)($r['telefono'] ?? ''));
    $wa  = ($tel !== '')
        ? ("https://wa.me/{$tel}?text=" . urlencode($msg))
        : ("https://wa.me/?text=" . urlencode($msg));

    $recItems[] = [
        'badge'   => (string)($r['badge'] ?? 'secondary'),
        'label'   => (string)($r['tipo'] ?? ''),
        'filter'  => (string)($r['list_url'] ?? ''),
        'id'      => $rid,
        'num'     => $num,
        'cliente' => (string)($r['cliente'] ?? ''),
        'wa_url'  => $wa,
    ];
}
// -------------------- REPORTE 30 DÍAS (NO DUPLICADO) --------------------
$rep_ingresadas  = 0;
$rep_en_proceso  = 0;
$rep_listas      = 0;
$rep_entregadas  = 0;

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE DATE(fecha_ingreso) >= :d
    ");
    $stmt->execute([':d' => $hace30]);
    $rep_ingresadas = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE DATE(fecha_ingreso) >= :d
          AND {$SQL_ESTADO_NORM} IN ('DIAGNOSTICO','EN REPARACION','EN ESPERA POR REPUESTOS')
    ");
    $stmt->execute([':d' => $hace30]);
    $rep_en_proceso = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE DATE(fecha_ingreso) >= :d
          AND {$SQL_ESTADO_NORM} = 'REPARADO'
    ");
    $stmt->execute([':d' => $hace30]);
    $rep_listas = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM ordenes
        WHERE DATE(fecha_ingreso) >= :d
          AND {$SQL_ESTADO_NORM} = 'ENTREGADO'
    ");
    $stmt->execute([':d' => $hace30]);
    $rep_entregadas = (int)$stmt->fetchColumn();

} catch (Exception $e) {}

$porc_listas     = ($rep_ingresadas > 0) ? (int)round(($rep_listas / $rep_ingresadas) * 100) : 0;
$porc_entregadas = ($rep_ingresadas > 0) ? (int)round(($rep_entregadas / $rep_ingresadas) * 100) : 0;

$mensajeRend = 'Sin datos suficientes aún.';
$claseRend   = 'text-muted';

if ($rep_ingresadas > 0) {
    if ($rep_listas >= 5 && $rep_entregadas === 0) {
        $mensajeRend = 'Tienes varias listas… falta coordinar entregas 📦';
        $claseRend   = 'text-warning';
    } else {
        if ($porc_entregadas >= 70) {
            $mensajeRend = 'Excelente: se está cerrando trabajo ✅';
            $claseRend   = 'text-success';
        } elseif ($rep_en_proceso > $rep_listas && $rep_en_proceso >= 5) {
            $mensajeRend = 'Hay carga en proceso 🔧 toca empujar diagnósticos/reparación.';
            $claseRend   = 'text-warning';
        } else {
            $mensajeRend = 'Ritmo estable 👍 mantén el flujo.';
            $claseRend   = 'text-primary';
        }
    }
}

// -------------------- SEMÁFORO DEL TALLER --------------------
$criticos = 0;
if ($ingresado24 > 0)   $criticos++;
if ($diagnostico48 > 0) $criticos++;
if ($reparado72 > 0)    $criticos++;

$semaforo_clase = 'success';
$semaforo_txt   = 'Estado: OK';

if ($criticos >= 3) {
    $semaforo_clase = 'danger';
    $semaforo_txt   = 'Estado: Urgente';
} elseif ($criticos >= 1) {
    $semaforo_clase = 'warning';
    $semaforo_txt   = 'Estado: Atención';
}
// -------------------- ÚLTIMOS MOVIMIENTOS (5) --------------------
$ultMov = [];
try {
    $stmt = $pdo->query("
        SELECT
            oe.orden_id,
            oe.estado,
            oe.usuario,
            oe.comentario,
            o.numero_orden
        FROM ordenes_estados oe
        LEFT JOIN ordenes o ON o.id = oe.orden_id
        ORDER BY oe.id DESC
        LIMIT 5
    ");
    $ultMov = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>
<style>
/* ---------- Cards clickeables ---------- */
.card-link{ display:block; color:inherit; text-decoration:none; }
.card-link:hover{ color:inherit; text-decoration:none; }
.card-link .card{ cursor:pointer; transition: transform .12s ease; }
.card-link .card:hover{ transform: translateY(-2px); }

/* ---------- DENSIDAD VISUAL CONTROLADA ---------- */
.card-stats .card-body{ padding:14px 16px; }
.card-stats .card-category{ font-size:13px; margin-bottom:4px; }
.card-stats .card-title{ font-size:20px; margin:0; line-height:1.2; }

@media (max-width: 1400px){
  .card-stats .card-body{ padding:10px 12px; }
  .card-stats .card-category{ font-size:12px; }
  .card-stats .card-title{ font-size:18px; }
}
@media (max-width: 1200px){
  .card-stats .card-body{ padding:8px 10px; }
  .card-stats .card-category{ font-size:11px; }
  .card-stats .card-title{ font-size:16px; }
}

/* ---------- Secciones ---------- */
.section-title{ margin: 6px 0 10px; }
.section-sub{ margin-top:-6px; }

/* ---------- Badges ---------- */
.badge-link{ text-decoration:none; }
.badge-link:hover{ text-decoration:none; opacity:.92; }

/* ---------- Barras ---------- */
.mini-bar{ height:10px; background:#e9ecef; border-radius:999px; overflow:hidden; }
.mini-bar > div{ height:100%; background:#0d6efd; }
.mini-bar.success > div{ background:#198754; }
.mini-bar.warn > div{ background:#ffc107; }
.mini-bar.danger > div{ background:#dc3545; }

/* ---------- Layout ---------- */
.row.tight { margin-left:-8px; margin-right:-8px; }
.row.tight > [class*="col-"]{ padding-left:8px; padding-right:8px; }
.card{ margin-bottom:16px; }

/* ✅ Header dashboard móvil */
.dash-title .page-title{ margin-bottom:0; }
.dash-title small{ display:block; }

.dash-actions{
  display:flex;
  align-items:center;
  gap:10px;
  flex-wrap:wrap;
}

@media (max-width: 767.98px){
  .dash-header{ display:block !important; }

  .dash-title{ margin-bottom:10px; }
  .dash-title .page-title{ font-size:18px; line-height:1.1; }
  .dash-title small{ font-size:12px; line-height:1.2; margin-top:2px; }

  .dash-actions{ width:100%; gap:8px; }
  .dash-actions .btn{
    flex:1 1 calc(50% - 8px);
    text-align:center;
    white-space:nowrap;
    padding:8px 10px;
    font-size:12px;
  }
  .dash-actions .btn i{ margin-right:6px; }
}

@media (max-width: 360px){
  .dash-actions .btn{ font-size:11px; padding:7px 8px; }
}

/* ✅ Compactación: Últimos movimientos */
.card-compact .card-header{ padding:10px 14px; }
.card-compact .card-body{ padding:0; }
.mov-list{ max-height: none; overflow: visible; }
.mov-list .list-group-item{ padding:8px 12px; }
.mov-list strong{ font-size:13px; }
.mov-list span{ font-size:13px; }
.mov-list small{ font-size:12px; }

@media (max-width: 767.98px){
  .mov-list{ max-height: none; }
}
/* ✅ Compactación: tabla Recordatorios */
.rec-table th{
  font-size:12px;
  color:#6c757d;
  text-transform:uppercase;
  letter-spacing:.4px;
}
.rec-table td, .rec-table th{ padding:.45rem .55rem; }
.rec-table td{ font-size:13px; }
.rec-table a{ text-decoration:none; }
.rec-table a:hover{ text-decoration:none; opacity:.92; }
/* ✅ Recordatorios: tabla compacta */
.rec-table thead th{ border-top:0; font-size:12px; color:#6c757d; }
.rec-table td{ font-size:12px; }
.rec-table td, .rec-table th{ padding:.35rem .55rem; }

/* ✅ Acciones: iconos en línea (PC + móvil) */
.rec-actions{
  display:flex;
  justify-content:flex-end;
  gap:6px;
}
.rec-actions .btn{
  width:32px;
  height:32px;
  padding:0;
  display:inline-flex;
  align-items:center;
  justify-content:center;
}

/* ✅ En móvil: fila completa clickeable + sin scroll horizontal + color por tipo */
@media (max-width: 767.98px){
  .rec-table tbody tr.rec-row{ cursor:pointer; }

  /* ocultar columna TIPO en móvil */
  .rec-col-tipo{ display:none; }

  /* fondos por tipo (badge) */  .rec-row.rec-badge-primary td{ background: rgba(13,110,253,.16) !important; }
  .rec-row.rec-badge-success td{ background: rgba(25,135,84,.16) !important; }
.rec-row.rec-badge-warning td{ background: rgba(255,193,7,.12); }
  .rec-row.rec-badge-info td{ background: rgba(13,202,240,.10); }
  .rec-row.rec-badge-dark td{ background: rgba(33,37,41,.06); }
  .rec-row.rec-badge-secondary td{ background: rgba(108,117,125,.06); }

  .rec-actions .btn{ width:30px; height:30px; }
  .rec-actions i{ font-size:14px; }
}
</style>
<div class="main-panel">
<div class="content">
<div class="container-fluid">

  <!-- Header + Acciones -->
  <div class="d-flex justify-content-between align-items-start mb-3 dash-header">
    <div class="dash-title">
      <h4 class="page-title">Inicio</h4>      <small class="text-muted">
        Resumen operativo del taller ·
        <span class="badge badge-<?php echo $semaforo_clase; ?>">
          <?php echo htmlspecialchars($semaforo_txt); ?>
        </span>
      </small>
</div>

    <div class="dash-actions">
      <a href="<?php echo url('/order/orden_nueva.php'); ?>" class="btn btn-primary btn-sm">
        <i class="la la-plus mr-1"></i> Nueva Orden
      </a><button type="button" class="btn btn-outline-secondary btn-sm" data-toggle="modal" data-target="#modalAcercaSistema">
        Acerca de
      </button>
    </div>  </div><!-- FLUJO -->
  <div class="row tight mb-2">
    <div class="col-12">
      <h5 class="section-title mb-0">Flujo del taller</h5>
      <small class="text-muted section-sub">Lo que entra → se diagnostica → se repara → se entrega</small>
    </div>
  </div>

  <div class="row tight">
    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes_dia.php'); ?>">
        <div class="card card-stats card-default">
          <div class="card-body">
            <p class="card-category">Ingresadas hoy</p>
            <h4 class="card-title"><?php echo (int)$ingresadasHoy; ?></h4>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=INGRESADO'); ?>">
        <div class="card card-stats card-primary">
          <div class="card-body">
            <p class="card-category">Pendientes diagnóstico</p>
            <h4 class="card-title"><?php echo (int)$pendientesDiagnostico; ?></h4>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=DIAGNOSTICO'); ?>">
        <div class="card card-stats card-warning">
          <div class="card-body">
            <p class="card-category">En diagnóstico</p>
            <h4 class="card-title"><?php echo (int)$enDiagnostico; ?></h4>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=EN_PROCESO'); ?>">
<div class="card card-stats card-info">
          <div class="card-body">
            <p class="card-category">En reparación / repuestos</p>
            <h4 class="card-title"><?php echo (int)$enReparacion; ?></h4>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="row tight">
    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=REPARADO'); ?>">
        <div class="card card-stats card-success">
          <div class="card-body">
            <p class="card-category">Listos para entregar (total)</p>
            <h4 class="card-title"><?php echo (int)$listosEntregar; ?></h4>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=ENTREGADO'); ?>">
        <div class="card card-stats card-secondary">
          <div class="card-body">
            <p class="card-category">Entregadas</p>
            <h4 class="card-title"><?php echo (int)$entregadas; ?></h4>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?con_evidencias=1'); ?>">
        <div class="card card-stats card-dark">
          <div class="card-body">
            <p class="card-category">Órdenes con evidencias</p>
            <h4 class="card-title"><?php echo (int)$conEvidencias; ?></h4>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-3 col-sm-6">
      <a class="card-link" href="<?php echo url('/order/ordenes.php?con_firma=1'); ?>">
        <div class="card card-stats card-success">
          <div class="card-body">
            <p class="card-category">Órdenes con firma</p>
            <h4 class="card-title"><?php echo (int)$conFirma; ?></h4>
          </div>
        </div>
      </a>
    </div>
  </div>

  <!-- PENDIENTES CRÍTICOS -->
  <div class="row tight mt-2 mb-2">
    <div class="col-12">
      <h5 class="section-title mb-0">Pendientes críticos</h5>
      <small class="text-muted section-sub">Lo que requiere atención hoy</small>
    </div>
  </div>

  <div class="row tight mb-3">

    <?php if ($diagnostico48 > 0): ?>
      <div class="col-md-3 col-sm-6">
        <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=DIAGNOSTICO&fecha_hasta='.$hasta_2d); ?>">
          <div class="card card-stats card-warning">
            <div class="card-body">
              <p class="card-category">Diagnósticos +48h</p>
              <h4 class="card-title"><?php echo (int)$diagnostico48; ?></h4>
            </div>
          </div>
        </a>
      </div>
    <?php endif; ?>

    <?php if ($reparado72 > 0): ?>
      <div class="col-md-3 col-sm-6">
        <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=REPARADO&fecha_hasta='.$hasta_3d); ?>">
          <div class="card card-stats card-success">
            <div class="card-body">
              <p class="card-category">Listas sin entregar +72h</p>
              <h4 class="card-title"><?php echo (int)$reparado72; ?></h4>
            </div>
          </div>
        </a>
      </div>
    <?php endif; ?>

    <?php if ($firmasPendientes > 0): ?>
      <div class="col-md-3 col-sm-6">
        <a class="card-link" href="<?php echo url('/order/firmas_pendientes.php'); ?>">
          <div class="card card-stats card-secondary">
            <div class="card-body">
              <p class="card-category">Firmas pendientes (requiere firma)</p>
              <h4 class="card-title"><?php echo (int)$firmasPendientes; ?></h4>
            </div>
          </div>
        </a>
      </div>
    <?php endif; ?>

    <?php if ($espera7d > 0): ?>
      <div class="col-md-3 col-sm-6">
        <a class="card-link" href="<?php echo url('/order/ordenes.php?estado=EN%20ESPERA%20POR%20REPUESTOS&fecha_hasta='.$hasta_7d); ?>">
          <div class="card card-stats card-info">
            <div class="card-body">
              <p class="card-category">Repuestos +7d</p>
              <h4 class="card-title"><?php echo (int)$espera7d; ?></h4>
            </div>
          </div>
        </a>
      </div>
    <?php endif; ?>

    <?php if (
      $diagnostico48 == 0 &&
      $reparado72 == 0 &&
      $firmasPendientes == 0 &&
      $espera7d == 0
    ): ?>
      <div class="col-12">
        <div class="alert alert-success mb-0">
          Todo bajo control 👌 No hay pendientes críticos.
        </div>
      </div>
    <?php endif; ?>

  </div>
<!-- Recordatorios + Movimientos + Reporte -->
  <div class="row tight mt-2">

    <!-- IZQUIERDA: MOVIMIENTOS + REPORTE (PC: uno debajo del otro) -->
    <div class="col-lg-5 col-md-12 order-lg-1 order-2">

      <div class="card card-compact">
        <div class="card-header">
          <h5 class="card-title mb-0">Últimos movimientos</h5>
          <small class="text-muted">Últimos 5</small>
        </div>

        <div class="card-body">
          <?php if (empty($ultMov)): ?>
            <div class="text-muted small px-3 py-2">Sin movimientos recientes.</div>
          <?php else: ?>
            <ul class="list-group list-group-flush mov-list">
              <?php foreach ($ultMov as $m): ?>
                <?php
                  $oid = (int)($m['orden_id'] ?? 0);

                  $n = (int)($m['numero_orden'] ?? 0);
                  if ($n <= 0) $n = $oid;
                  $nFmt = str_pad((string)$n, 4, '0', STR_PAD_LEFT);

                  $estado = (string)($m['estado'] ?? '');
                  $usuario = (string)($m['usuario'] ?? '');
                ?>                <li class="list-group-item d-flex justify-content-between align-items-center">
                  <div class="text-truncate" style="max-width: 75%;">
                    <strong>#<?php echo htmlspecialchars($nFmt); ?></strong>
                    <span class="ml-2"><?php echo htmlspecialchars($estado); ?></span>
                  </div>
                  <small class="text-muted"><?php echo htmlspecialchars($usuario); ?></small>
                </li>
<?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Reporte rápido (últimos 30 días)</h5>
          <small class="text-muted">Sin números duplicados, por estado real</small>
        </div>

        <div class="card-body">

          <div class="mb-2 d-flex justify-content-between">
            <div class="text-muted">Órdenes ingresadas</div>
            <strong><?php echo (int)$rep_ingresadas; ?></strong>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <div class="text-muted">En proceso (diagnóstico / reparación / repuestos)</div>
              <strong><?php echo (int)$rep_en_proceso; ?></strong>
            </div>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <div class="text-muted">Listas (reparadas)</div>
              <strong><?php echo (int)$rep_listas; ?> (<?php echo (int)$porc_listas; ?>%)</strong>
            </div>
            <div class="mini-bar <?php echo ($porc_listas >= 70 ? 'success' : ($porc_listas >= 45 ? 'warn' : 'danger')); ?>">
              <div style="width: <?php echo (int)$porc_listas; ?>%;"></div>
            </div>
          </div>

          <div class="mb-3">
            <div class="d-flex justify-content-between">
              <div class="text-muted">Entregadas</div>
              <strong><?php echo (int)$rep_entregadas; ?> (<?php echo (int)$porc_entregadas; ?>%)</strong>
            </div>
            <div class="mini-bar <?php echo ($porc_entregadas >= 60 ? 'success' : ($porc_entregadas >= 30 ? 'warn' : 'danger')); ?>">
              <div style="width: <?php echo (int)$porc_entregadas; ?>%;"></div>
            </div>
          </div>

          <div class="<?php echo $claseRend; ?> font-weight-bold mb-3">
            <?php echo htmlspecialchars($mensajeRend); ?>
          </div>

          <div class="d-flex flex-wrap" style="gap:8px;">
            <a href="<?php echo url('/order/ordenes.php'); ?>" class="btn btn-outline-primary btn-sm">
              <i class="la la-list mr-1"></i> Ver todas
            </a>
            <a href="<?php echo url('/order/ordenes.php?estado=DIAGNOSTICO'); ?>" class="btn btn-outline-warning btn-sm">
              <i class="la la-stethoscope mr-1"></i> Diagnóstico
            </a>
            <a href="<?php echo url('/order/ordenes.php?estado=REPARADO'); ?>" class="btn btn-outline-success btn-sm">
              <i class="la la-check mr-1"></i> Listas
            </a>
            <a href="<?php echo url('/order/ordenes.php?estado=ENTREGADO'); ?>" class="btn btn-outline-secondary btn-sm">
              <i class="la la-truck mr-1"></i> Entregadas
            </a>
          </div>

        </div>
      </div>

    </div>

    <!-- DERECHA: ATRASOS (PC: a la derecha) -->
    <div class="col-lg-7 col-md-12 order-lg-2 order-1">
      <div class="card">
        <div class="card-header">
          <h5 class="card-title mb-0">Atrasos por tiempo</h5>
          <small class="text-muted">Top 5 más antiguos · tap en la fila para abrir la orden</small>
        </div>

        <div class="card-body">
          <div class="d-flex flex-wrap mb-2" style="gap:10px;">

            <?php if ($ingresado24 > 0): ?>
              <a class="badge-link" href="<?php echo url('/order/ordenes.php?estado=INGRESADO&fecha_hasta='.$hasta_1d.'&return=dashboard.php'); ?>">
                <span class="badge badge-primary">Ingresado +24h: <?php echo (int)$ingresado24; ?></span>
              </a>
            <?php endif; ?>

            <?php if ($diagnostico48 > 0): ?>
              <a class="badge-link" href="<?php echo url('/order/ordenes.php?estado=DIAGNOSTICO&fecha_hasta='.$hasta_2d.'&return=dashboard.php'); ?>">
                <span class="badge badge-warning">Diagnóstico +48h: <?php echo (int)$diagnostico48; ?></span>
              </a>
            <?php endif; ?>

            <?php if ($reparacion72 > 0): ?>
              <a class="badge-link" href="<?php echo url('/order/ordenes.php?estado=EN%20REPARACION&fecha_hasta='.$hasta_3d.'&return=dashboard.php'); ?>">
                <span class="badge badge-info">Reparación +72h: <?php echo (int)$reparacion72; ?></span>
              </a>
            <?php endif; ?>

            <?php if ($espera7d > 0): ?>
              <a class="badge-link" href="<?php echo url('/order/ordenes.php?estado=EN%20ESPERA%20POR%20REPUESTOS&fecha_hasta='.$hasta_7d.'&return=dashboard.php'); ?>">
                <span class="badge badge-dark">Repuestos +7d: <?php echo (int)$espera7d; ?></span>
              </a>
            <?php endif; ?>

            <?php if ($reparado72 > 0): ?>
              <a class="badge-link" href="<?php echo url('/order/ordenes.php?estado=REPARADO&fecha_hasta='.$hasta_3d.'&return=dashboard.php'); ?>">
                <span class="badge badge-success">Listas +72h: <?php echo (int)$reparado72; ?></span>
              </a>
            <?php endif; ?>

            <?php if ($ingresado24 == 0 && $diagnostico48 == 0 && $reparacion72 == 0 && $espera7d == 0 && $reparado72 == 0): ?>
              <span class="text-muted small">Sin atrasos por tiempo 🎉</span>
            <?php endif; ?>

          </div>

          <?php if (!empty($recItems)): ?>
            <div class="table-responsive mt-2">
              <table class="table table-sm rec-table mb-0">
                <thead>
                  <tr>
                    <th class="rec-col-tipo">Tipo</th>
                    <th>Orden</th>
                    <th>Cliente</th>
                    <th class="text-right" style="width:84px;">Acc.</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recItems as $it): ?>
                    <?php
                      $numFmt = str_pad((string)($it['num'] ?? 0), 4, '0', STR_PAD_LEFT);
                      $detailUrl = url('/order/orden_detalle.php?id='.(int)($it['id'] ?? 0).'&return=dashboard.php');
                      $waUrl = (string)($it['wa_url'] ?? '');
                      $badge = (string)($it['badge'] ?? 'secondary');
                    ?>
                    <tr class="rec-row rec-badge-<?php echo htmlspecialchars($badge); ?>" data-href="<?php echo htmlspecialchars($detailUrl); ?>">
                      <td class="rec-col-tipo">
                        <a class="badge-link" href="<?php echo htmlspecialchars($it['filter']); ?>">
                          <span class="badge badge-<?php echo htmlspecialchars($badge); ?>">
                            <?php echo htmlspecialchars($it['label']); ?>
                          </span>
                        </a>
                      </td>                      <td>
                        <a href="<?php echo $detailUrl; ?>">
                          #<?php echo htmlspecialchars($numFmt); ?>
                        </a>
                      </td>
<td class="text-truncate" style="max-width:260px;">
                        <?php echo htmlspecialchars(($it['cliente'] ?? '') !== '' ? $it['cliente'] : '—'); ?>
                      </td>
                      <td class="text-right rec-actions">
                        <a href="<?php echo $detailUrl; ?>" class="btn btn-outline-primary btn-sm" title="Ver">
                          <i class="la la-eye"></i>
                        </a>
                        <?php if ($waUrl !== ''): ?>
                          <a href="<?php echo htmlspecialchars($waUrl); ?>" target="_blank" class="btn btn-success btn-sm" title="WhatsApp">
                            <i class="la la-whatsapp"></i>
                          </a>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>

        </div>
      </div>
    </div>  </div>
<!-- MODAL ACERCA -->
<div class="modal fade" id="modalAcercaSistema" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">SysTec <?php echo SYSTEC_VERSION; ?> — Versión estable</h5>
        <button class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p><strong>Estado:</strong> Sistema operativo y congelado.</p>
        <ul>
          <li>Gestión de órdenes</li>
          <li>Diagnóstico y costos</li>
          <li>Evidencias con control de visibilidad</li>
          <li>PDF internos y públicos</li>
          <li>Seguimiento por token</li>
          <li>Firma digital</li>
        </ul>
        <p class="text-muted mb-0">Cambios y mejoras solo en nuevas versiones.</p>
      </div>
      <div class="modal-footer">
        <small class="text-muted">C2K · Mikel DNG</small>
        <button class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

</div>

<script>
(function(){
  function bindRecRows(){
    if (!window.matchMedia('(max-width: 767.98px)').matches) return;

    var rows = document.querySelectorAll('.rec-table tbody tr.rec-row');
    rows.forEach(function(r){
      r.addEventListener('click', function(e){
        if (e.target.closest('a')) return; // no romper links (badge/whatsapp/ver)
        var href = r.getAttribute('data-href');
        if (href) window.location.href = href;
      });
    });
  }
  document.addEventListener('DOMContentLoaded', bindRecRows);
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
