<?php
// order/ordenes.php
require_once __DIR__ . '/../config/config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

// (Opcional) Rol
$usuarioRol = $_SESSION['usuario_rol'] ?? '';

// Detectar móvil
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);

/* --------------------------------------
   Helpers: estado (normalizar sin drama)
-------------------------------------- */
function normalizar_estado_simple($estado)
{
    $e = trim((string)$estado);
    if ($e === '') return '';

    $e = preg_replace('/\s+/', ' ', $e);
    $e = mb_strtoupper($e, 'UTF-8');

    // Unificamos variantes sin tilde -> con tilde (para UI)
    if ($e === 'DIAGNOSTICO')  $e = 'DIAGNÓSTICO';
    if ($e === 'EN REPARACION') $e = 'EN REPARACIÓN';

    return $e;
}

function estado_sin_tildes($txt)
{
    $txt = mb_strtoupper(trim((string)$txt), 'UTF-8');
    $buscar = ['Á','É','Í','Ó','Ú'];
    $reempl = ['A','E','I','O','U'];
    return str_replace($buscar, $reempl, $txt);
}

/* ✅ formateo "bonito" (Nombre/Marca/Modelo) */
function title_case_smart($str) {
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

/* --------------------------------------
   1) Filtros (GET)
-------------------------------------- */
$buscar          = trim($_GET['buscar']         ?? '');
$estado_filtro   = trim($_GET['estado']         ?? '');
$fecha_desde     = trim($_GET['fecha_desde']    ?? '');
$fecha_hasta     = trim($_GET['fecha_hasta']    ?? '');
$con_evidencias  = isset($_GET['con_evidencias']) ? (int)$_GET['con_evidencias'] : 0;
$con_firma       = isset($_GET['con_firma']) ? (int)$_GET['con_firma'] : 0;

$return_param = trim($_GET['return'] ?? '');
$return_url   = '';
if ($return_param !== '') {
    if (strpos($return_param, '://') === false && strpos($return_param, '//') !== 0 && !preg_match('/[\r\n]/', $return_param)) {
        if (preg_match('/^[a-zA-Z0-9_\/\.\?\=\&\-\%]+$/', $return_param)) {
            $return_url = rtrim(APP_URL, '/') . '/' . ltrim($return_param, '/');
        }
    }
}
// Validar fechas
if ($fecha_desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) $fecha_desde = '';
if ($fecha_hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) $fecha_hasta = '';

/* --------------------------------------
   2) Query dinámica (robusta)
-------------------------------------- */
$where  = [];
$params = [];

if ($buscar !== '') {
    $isNum = ctype_digit($buscar);

    $where[] = "(
        " . ($isNum ? "o.numero_orden = :num OR " : "") . "
        o.cliente_nombre      LIKE :b1
        OR o.cliente_telefono LIKE :b2
        OR o.equipo_marca     LIKE :b3
        OR o.equipo_modelo    LIKE :b4
        OR o.equipo_imei1     LIKE :b5
        OR o.equipo_imei2     LIKE :b6
    )";

    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $buscar) . '%';

    $params[':b1'] = $like;
    $params[':b2'] = $like;
    $params[':b3'] = $like;
    $params[':b4'] = $like;
    $params[':b5'] = $like;
    $params[':b6'] = $like;

    if ($isNum) {
        $params[':num'] = (int)$buscar;
    }

    // ESCAPE para todos los LIKE
    $where[count($where)-1] = str_replace("LIKE :b1", "LIKE :b1 ESCAPE '\\\\'", $where[count($where)-1]);
    $where[count($where)-1] = str_replace("LIKE :b2", "LIKE :b2 ESCAPE '\\\\'", $where[count($where)-1]);
    $where[count($where)-1] = str_replace("LIKE :b3", "LIKE :b3 ESCAPE '\\\\'", $where[count($where)-1]);
    $where[count($where)-1] = str_replace("LIKE :b4", "LIKE :b4 ESCAPE '\\\\'", $where[count($where)-1]);
    $where[count($where)-1] = str_replace("LIKE :b5", "LIKE :b5 ESCAPE '\\\\'", $where[count($where)-1]);
    $where[count($where)-1] = str_replace("LIKE :b6", "LIKE :b6 ESCAPE '\\\\'", $where[count($where)-1]);
}

// ✅ Estado filtro (soporta con/sin tilde)
if ($estado_filtro !== '') {
    $estadoNormUI = normalizar_estado_simple($estado_filtro);
    $estadoNorm   = estado_sin_tildes($estadoNormUI);

    if ($estadoNorm === 'EN_PROCESO') {
        $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        UPPER(TRIM(o.estado_actual)),
                        'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'
                   ) IN ('DIAGNOSTICO','EN REPARACION','EN ESPERA POR REPUESTOS')";
    } else {
        $where[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        UPPER(TRIM(o.estado_actual)),
                        'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'
                   ) = :estado_norm";
        $params[':estado_norm'] = $estadoNorm;
    }
}
if ($fecha_desde !== '') {
    $where[] = "DATE(o.fecha_ingreso) >= :fecha_desde";
    $params[':fecha_desde'] = $fecha_desde;
}

if ($fecha_hasta !== '') {
    $where[] = "DATE(o.fecha_ingreso) <= :fecha_hasta";
    $params[':fecha_hasta'] = $fecha_hasta;
}

/* ✅ filtro por evidencias */
if ($con_evidencias === 1) {
    $where[] = "EXISTS (
        SELECT 1
        FROM ordenes_evidencias ev
        WHERE ev.orden_id = o.id
    )";
}

/* ✅ filtro por firma */
if ($con_firma === 1) {
    $where[] = "(o.firma_ruta IS NOT NULL AND o.firma_ruta <> '')";
}

$sql = "SELECT o.* FROM ordenes o";
if (!empty($where)) $sql .= " WHERE " . implode(' AND ', $where);

// ✅ Paginación
$perPage = 10;
$page    = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
$offset  = ($page - 1) * $perPage;

// Total (para páginas)
$sqlCount = "SELECT COUNT(*) FROM ordenes o";
if (!empty($where)) $sqlCount .= " WHERE " . implode(' AND ', $where);

$stmtC = $pdo->prepare($sqlCount);
$stmtC->execute($params);
$totalRows  = (int)$stmtC->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$sql .= " ORDER BY
    (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(o.estado_actual)),
        'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U') = 'ENTREGADO') ASC,
    o.fecha_ingreso DESC
    LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind de filtros
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, PDO::PARAM_STR);
}

// Bind paginación
$stmt->bindValue(':limit',  (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', (int)$offset,  PDO::PARAM_INT);

$stmt->execute();
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* --------------------------------------
   3) Badge estado
-------------------------------------- */
function badgeEstado($estado_raw)
{
    $estadoUI = normalizar_estado_simple($estado_raw);
    $estadoN  = estado_sin_tildes($estadoUI);

    $class = 'badge-secondary';
    switch ($estadoN) {
        case 'INGRESADO':               $class = 'badge-secondary'; break;
        case 'DIAGNOSTICO':
        case 'REVISION':                $class = 'badge-warning';   break;
        case 'EN ESPERA POR REPUESTOS': $class = 'badge-info';      break;
        case 'EN REPARACION':           $class = 'badge-primary';   break;
        case 'REPARADO':                $class = 'badge-success';   break;
        case 'ENTREGADO':               $class = 'badge-dark';      break;
    }
    return '<span class="badge ' . $class . '">' . htmlspecialchars($estadoUI) . '</span>';
}

/* --------------------------------------
   4) Estados para filtro (UI)
-------------------------------------- */
$estados_posibles = [
    ''                        => 'Todos',
    'INGRESADO'               => 'INGRESADO',
    'DIAGNOSTICO'             => 'DIAGNÓSTICO',
    'EN ESPERA POR REPUESTOS' => 'EN ESPERA POR REPUESTOS',
    'EN REPARACION'           => 'EN REPARACIÓN',
    'REPARADO'                => 'REPARADO',
    'ENTREGADO'               => 'ENTREGADO',
];

/* --------------------------------------
   5) Base URL real para links (PDF/seguimiento)
-------------------------------------- */
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $scheme . $_SERVER['HTTP_HOST'] . APP_URL;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* Base */
.acciones-col{white-space:nowrap;}
.acciones-col .btn{padding:4px 8px;font-size:12px;margin-right:6px;}
.acciones-col .btn:last-child{margin-right:0;}
.order-row{cursor:pointer;}
.order-row:hover{background:#f8f9fa;}
.table td, .table th{ vertical-align: middle; }

/* ✅ MÓVIL: ganar ancho real */
@media (max-width: 767.98px){
  .main-panel .content{ padding-left:8px !important; padding-right:8px !important; }
  .container-fluid{ padding-left:8px !important; padding-right:8px !important; }
  .card{ margin-left:0 !important; margin-right:0 !important; }
  .card-body{ padding:12px !important; }

  .table td, .table th{ padding:.50rem .45rem; }
  .badge{ font-size:11px; padding:.30em .55em; border-radius:999px; }

  /* ✅ ocultar columna Estado en móvil */
  table.table th:nth-child(4),
  table.table td:nth-child(4){ display:none; }

  /* ✅ mini pill estado */
  .estado-mini{
    display:inline-block;
    font-size:10px;
    padding:2px 8px;
    border-radius:999px;
    margin-top:4px;
    font-weight:700;
    letter-spacing:.4px;
  }

  /* ✅ Evitar "Google Note..." selección */
  .order-row, .order-row *{
    -webkit-user-select:none;
    user-select:none;
    -webkit-touch-callout:none;
  }
  .order-row{
    -webkit-tap-highlight-color: transparent;
  }
}

/* ✅ COLORES POR FILA (solo móvil) */
@media (max-width: 767.98px){
  tr.st-INGRESADO td{ background:#f3f4f6 !important; }
  tr.st-DIAGNOSTICO td{ background:#e6f6ff !important; }
  tr.st-EN_ESPERA_POR_REPUESTOS td{ background:#fff6e5 !important; }
  tr.st-EN_REPARACION td{ background:#e9f2ff !important; }
  tr.st-REPARADO td{ background:#e9fbef !important; }
  tr.st-ENTREGADO td{ background:#eeeafc !important; }

  tr.st-INGRESADO .estado-mini{ background:#e5e7eb; color:#374151; }
  tr.st-DIAGNOSTICO .estado-mini{ background:#cfefff; color:#075985; }
  tr.st-EN_ESPERA_POR_REPUESTOS .estado-mini{ background:#fde7c2; color:#92400e; }
  tr.st-EN_REPARACION .estado-mini{ background:#cfe0ff; color:#1e3a8a; }
  tr.st-REPARADO .estado-mini{ background:#c9f7d7; color:#166534; }
  tr.st-ENTREGADO .estado-mini{ background:#ddd6fe; color:#4c1d95; }
}

/* ✅ Leyenda legible */
.legend-card{ background:#fff; border:1px solid #eee; border-radius:8px; }
.legend-title{ font-weight:700; font-size:13px; color:#2b2f33; margin-bottom:6px; }
.legend-grid{ display:grid; grid-template-columns:1fr 1fr; gap:6px 10px; }
.legend-item{ display:flex; align-items:center; gap:8px; font-size:12px; color:#2b2f33; line-height:1.1; }
.sw{ width:14px; height:14px; border-radius:999px; border:1px solid rgba(0,0,0,.12); flex:0 0 14px; }
.sw-ing{ background:#f3f4f6; }
.sw-dia{ background:#e6f6ff; }
.sw-esp{ background:#fff6e5; }
.sw-rep{ background:#e9f2ff; }
.sw-ok { background:#e9fbef; }
.sw-ent{ background:#eeeafc; }

/* PC: como lo tenías */
@media (min-width: 768px){
  table.table{ table-layout: fixed; }
  table.table th:nth-child(1), table.table td:nth-child(1){ width:80px; }
  table.table th:nth-child(2), table.table td:nth-child(2){ width:110px; }
  table.table th:nth-child(3), table.table td:nth-child(3){ width:70px; }
  table.table th:nth-child(6), table.table td:nth-child(6){ width:150px; }
  table.table th:nth-child(7), table.table td:nth-child(7){ width:310px; }

  table.table td:nth-child(4),
  table.table td:nth-child(5){
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
}
</style>

<div class="main-panel">
<div class="content">
<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center flex-wrap mb-2">
  <div>
    <h4 class="page-title mb-0">Órdenes</h4>
    <small class="text-muted">Filtra por estado/fecha o busca por cliente / IMEI.</small>
  </div>

  <div class="d-flex align-items-center" style="gap:8px;">
    <?php if ($return_url !== ''): ?>
      <a href="<?php echo htmlspecialchars($return_url); ?>" class="btn btn-sm btn-outline-secondary">
        <i class="la la-arrow-left"></i> Volver
      </a>
    <?php elseif (!empty($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false): ?>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="history.back()">
        <i class="la la-arrow-left"></i> Volver
      </button>
    <?php else: ?>
      <a href="<?php echo APP_URL; ?>/dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="la la-home"></i> Inicio
      </a>
    <?php endif; ?>
  </div>
</div>
<div class="card">
<div class="card-body">

<!-- Filtros -->
<form method="get" class="form-row mb-3" action="<?php echo APP_URL; ?>/order/ordenes.php">
  <?php if ($return_param !== ''): ?>
    <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_param); ?>">
  <?php endif; ?>
<div class="form-group col-6 col-md-3">
    <label class="d-none d-md-block">Buscar</label>
    <input type="text" name="buscar" class="form-control form-control-sm"
           placeholder="Buscar (cliente, IMEI...)"
           value="<?php echo htmlspecialchars($buscar); ?>">
  </div>

  <div class="form-group col-6 col-md-2">
    <label class="d-none d-md-block">Estado</label>
    <select name="estado" class="form-control form-control-sm" aria-label="Estado">
      <?php foreach ($estados_posibles as $v => $t): ?>
        <option value="<?php echo htmlspecialchars($v); ?>" <?php echo ($estado_filtro === $v ? 'selected' : ''); ?>>
          <?php echo htmlspecialchars($t); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group col-6 col-md-2">
    <label class="d-none d-md-block">Desde</label>
    <input type="date" name="fecha_desde" class="form-control form-control-sm"
           value="<?php echo htmlspecialchars($fecha_desde); ?>">
  </div>

  <div class="form-group col-6 col-md-2">
    <label class="d-none d-md-block">Hasta</label>
    <input type="date" name="fecha_hasta" class="form-control form-control-sm"
           value="<?php echo htmlspecialchars($fecha_hasta); ?>">
  </div>

  <div class="form-group col-12 col-md-3 d-flex align-items-end">
    <button class="btn btn-sm btn-primary mr-2">Filtrar</button>
    <a href="<?php echo APP_URL; ?>/order/ordenes.php" class="btn btn-sm btn-outline-secondary mr-2">
      Limpiar
    </a>

    <?php if ($con_evidencias === 1): ?>
      <span class="badge badge-dark align-self-center">Con evidencias</span>
    <?php endif; ?>
    <?php if ($con_firma === 1): ?>
      <span class="badge badge-success align-self-center">Con firma</span>
    <?php endif; ?>
  </div>

</form>

<?php if (empty($ordenes)): ?>
    <div class="alert alert-info">No se encontraron órdenes.</div>
<?php else: ?>

<?php if ($isMobile): ?>
  <div class="legend-card mb-2">
    <div class="card-body py-2">
      <div class="legend-title">Leyenda de estados (color de fila)</div>
      <div class="legend-grid">
        <div class="legend-item"><span class="sw sw-ing"></span>Ingresado</div>
        <div class="legend-item"><span class="sw sw-dia"></span>Diagnóstico</div>
        <div class="legend-item"><span class="sw sw-esp"></span>Espera rep.</div>
        <div class="legend-item"><span class="sw sw-rep"></span>Reparación</div>
        <div class="legend-item"><span class="sw sw-ok"></span>Reparado</div>
        <div class="legend-item"><span class="sw sw-ent"></span>Entregado</div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="table-responsive">
<table class="table table-striped table-hover table-sm">
<thead>
<tr>
  <th style="width:70px;">N°</th>
  <th>Cliente</th>
  <th>Equipo</th>
  <th style="width:120px;">Estado</th>

  <?php if (!$isMobile): ?>
    <th style="width:90px;">Fecha</th>
    <th style="width:70px;">Hora</th>
    <th style="width:280px;">Acciones</th>
  <?php endif; ?>
</tr>
</thead>

<tbody>

<?php
$q = $_GET;
unset($q['p']);
$qsBase = http_build_query($q);
$qsBase = ($qsBase !== '') ? ($qsBase . '&') : '';
?>

<?php foreach ($ordenes as $o): ?>
<?php
$clienteFmt = title_case_smart($o['cliente_nombre'] ?? '');
$marcaFmt   = title_case_smart($o['equipo_marca'] ?? '');
$modeloFmt  = title_case_smart($o['equipo_modelo'] ?? '');

$equipo = trim($marcaFmt . ' ' . $modeloFmt);
if ($equipo === '') $equipo = 'Sin datos';

$fechaTs   = strtotime($o['fecha_ingreso']);
$hora_ing  = date('H:i', $fechaTs);

// PDF + Seguimiento (usa token si existe)
if (!empty($o['token_publico'])) {
    $pdfUrl = $baseUrl . '/order/orden_pdf_publico.php?token=' . urlencode($o['token_publico']);
    $segUrl = $baseUrl . '/order/seguimiento_orden.php?token=' . urlencode($o['token_publico']);
} else {
    $pdfUrl = $baseUrl . '/order/orden_pdf.php?id=' . (int)$o['id'];
    $segUrl = $baseUrl . '/order/seguimiento_orden.php';
}

// ✅ Link del comprobante de entrega (interno)
$entregaUrl = $baseUrl . '/order/entrega_pdf.php?id=' . (int)$o['id'];

// WhatsApp
$numeroOrden = str_pad((string)$o['numero_orden'], 4, '0', STR_PAD_LEFT);
$estadoTxt   = normalizar_estado_simple($o['estado_actual'] ?? '');

// ✅ Clases por estado
$estadoKey = estado_sin_tildes($estadoTxt);
$estadoKey = str_replace(' ', '_', $estadoKey);

$estadoShort = '—';
switch ($estadoKey) {
    case 'INGRESADO': $estadoShort = 'ING'; break;
    case 'DIAGNOSTICO': $estadoShort = 'DIA'; break;
    case 'EN_ESPERA_POR_REPUESTOS': $estadoShort = 'ESP'; break;
    case 'EN_REPARACION': $estadoShort = 'REP'; break;
    case 'REPARADO': $estadoShort = 'OK'; break;
    case 'ENTREGADO': $estadoShort = 'ENT'; break;
}

$waText =
    "Orden de servicio N° {$numeroOrden}"
    . "\nCliente: " . ($clienteFmt !== '' ? $clienteFmt : ($o['cliente_nombre'] ?? ''))
    . "\nEquipo: " . $equipo
    . "\nEstado: " . $estadoTxt
    . "\n\nComprobante PDF: " . $pdfUrl
    . "\nSeguimiento: " . $segUrl;

if ($estadoKey === 'ENTREGADO') {
    $waText .= "\nComprobante de entrega (PDF): " . $entregaUrl;
}

// Teléfono -> wa.me si se puede
$telBruto  = $o['cliente_telefono'] ?? '';
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

$cid = (int)$o['id'];
$showEntregaBtn = ($estadoKey === 'ENTREGADO');
?>

<tr class="order-row st-<?php echo htmlspecialchars($estadoKey); ?>"
    <?php if ($isMobile): ?>      onclick="window.location.href='<?php echo APP_URL; ?>/order/orden_detalle.php?id=<?php echo $cid; ?>&return=<?php echo urlencode('order/ordenes.php?' . $qsBase . 'p=' . (int)$page); ?>';"
style="cursor:pointer;"
    <?php endif; ?>
>
  <td><?php echo $numeroOrden; ?></td>

  <td style="line-height:1.15">
    <?php echo htmlspecialchars($clienteFmt !== '' ? $clienteFmt : (string)($o['cliente_nombre'] ?? '')); ?>
  </td>

  <td style="line-height:1.15">
    <?php echo htmlspecialchars($equipo); ?>
    <?php if ($isMobile): ?>
      <div class="estado-mini"><?php echo htmlspecialchars($estadoShort); ?></div>
    <?php endif; ?>
  </td>

  <td><?php echo badgeEstado($o['estado_actual']); ?></td>

  <?php if (!$isMobile): ?>
    <td><?php echo htmlspecialchars(date('Y-m-d', $fechaTs)); ?></td>
    <td><?php echo htmlspecialchars($hora_ing); ?></td>

    <td class="acciones-col">      <a href="<?php echo url('/order/orden_detalle.php?id='.(int)$cid.'&return='.urlencode('order/ordenes.php?'.$qsBase.'p='.(int)$page)); ?>"
         class="btn btn-outline-primary btn-sm" title="Ver">
        <i class="la la-eye"></i> <span class="txt">Ver</span>
      </a>
<a href="<?php echo APP_URL; ?>/order/orden_editar.php?id=<?php echo $cid; ?>"
         class="btn btn-outline-warning btn-sm" title="Editar">
        <i class="la la-edit"></i> <span class="txt">Editar</span>
      </a>

      <?php if ($showEntregaBtn): ?>
      <a href="<?php echo APP_URL; ?>/order/entrega_pdf.php?id=<?php echo $cid; ?>"
         target="_blank" class="btn btn-outline-success btn-sm" title="Entrega">
        <i class="la la-check-circle"></i> <span class="txt">Entrega</span>
      </a>
      <?php endif; ?>

      <a href="<?php echo htmlspecialchars($waUrl); ?>"
         target="_blank" class="btn btn-success btn-sm" title="WhatsApp">
        <i class="la la-whatsapp"></i> <span class="txt">WhatsApp</span>
      </a>
    </td>
  <?php endif; ?>
</tr>

<?php endforeach; ?>

</tbody>
</table>
</div>

<p class="text-muted mt-2">
  Mostrando <?php echo count($ordenes); ?> de <?php echo (int)$totalRows; ?> · Página <?php echo (int)$page; ?> / <?php echo (int)$totalPages; ?>
</p>

<nav aria-label="Paginación">
  <ul class="pagination pagination-sm mb-0">
    <li class="page-item <?php echo ($page <= 1 ? 'disabled' : ''); ?>">
      <a class="page-link" href="?<?php echo $qsBase; ?>p=<?php echo max(1, $page - 1); ?>">«</a>
    </li>

    <?php
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    for ($i = $start; $i <= $end; $i++):
    ?>
      <li class="page-item <?php echo ($i === $page ? 'active' : ''); ?>">
        <a class="page-link" href="?<?php echo $qsBase; ?>p=<?php echo $i; ?>"><?php echo $i; ?></a>
      </li>
    <?php endfor; ?>

    <li class="page-item <?php echo ($page >= $totalPages ? 'disabled' : ''); ?>">
      <a class="page-link" href="?<?php echo $qsBase; ?>p=<?php echo min($totalPages, $page + 1); ?>">»</a>
    </li>
  </ul>
</nav>

<?php endif; // empty ordenes ?>
</div>
</div>

</div>
</div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
