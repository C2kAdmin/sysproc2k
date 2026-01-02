<?php
// order/ordenes_dia.php
require_once __DIR__ . '/../config/config.php';

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// (Opcional) Rol
$usuarioRol = $_SESSION['usuario_rol'] ?? '';

// Detectar móvil
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);

/* --------------------------------------
   Helpers estado (robusta)
-------------------------------------- */
function normalizar_estado_simple($estado)
{
    $e = trim((string)$estado);
    if ($e === '') return '';

    $e = preg_replace('/\s+/', ' ', $e);
    $e = mb_strtoupper($e, 'UTF-8');

    if ($e === 'DIAGNOSTICO')   $e = 'DIAGNÓSTICO';
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

/* ✅ formateo bonito */
function title_case_smart($str) {
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

// 1) Fecha seleccionada (GET) o por defecto HOY
$fecha = isset($_GET['fecha']) && $_GET['fecha'] !== '' ? $_GET['fecha'] : date('Y-m-d');

// ✅ por defecto NO mostrar ENTREGADAS
$mostrar_entregadas = isset($_GET['mostrar_entregadas']) ? (int)$_GET['mostrar_entregadas'] : 0;

// Validación simple (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
    $fecha = date('Y-m-d');
}

// 2) Consultar órdenes de esa fecha
$sql = "
    SELECT *
    FROM ordenes
    WHERE DATE(fecha_ingreso) = :fecha
";

// ✅ Por defecto ocultar ENTREGADO
if ($mostrar_entregadas !== 1) {
    $sql .= "
      AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
            UPPER(TRIM(estado_actual)),
            'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U'
        ) <> 'ENTREGADO'
    ";
}

// ✅ Ordenar: ENTREGADO abajo (si se muestran) + más nuevas arriba
$sql .= "
    ORDER BY
      (REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(estado_actual)),
          'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U') = 'ENTREGADO') ASC,
      fecha_ingreso DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':fecha' => $fecha]);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3) Helper para badge de estado
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

// 4) Base URL real
$scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$baseUrl = $scheme . $_SERVER['HTTP_HOST'] . APP_URL;

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* Base */
.acciones-col{white-space:nowrap;}
.acciones-col .btn{padding:2px 6px;font-size:11px;margin-right:4px;}
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

  /* ✅ ocultar columna Estado y Acciones en móvil */
  table.table th:nth-child(5),
  table.table td:nth-child(5){ display:none; }

  table.table th:nth-child(6),
  table.table td:nth-child(6){ display:none; }

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

  /* ✅ Evitar "Google Note..." */
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
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <h4 class="page-title">Órdenes del día (<?php echo htmlspecialchars($fecha); ?>)</h4>

      <div class="card">
        <div class="card-body">

          <form method="get" class="form-row mb-3" action="<?php echo APP_URL; ?>/order/ordenes_dia.php">
            <div class="form-group col-6 col-md-3">
              <label class="d-none d-md-block">Fecha</label>
              <input type="date" id="fecha" name="fecha" class="form-control form-control-sm"
                     value="<?php echo htmlspecialchars($fecha); ?>">
            </div>

            <div class="form-group col-6 col-md-3 d-flex align-items-end">
              <div class="form-check">
                <input type="checkbox" class="form-check-input" id="mostrar_entregadas" name="mostrar_entregadas" value="1"
                       <?php echo ($mostrar_entregadas === 1 ? 'checked' : ''); ?>>
                <label class="form-check-label" for="mostrar_entregadas">Mostrar entregadas</label>
              </div>
            </div>

            <div class="form-group col-12 col-md-3 d-flex align-items-end">
              <button type="submit" class="btn btn-sm btn-primary">Ver órdenes</button>
            </div>
          </form>

          <?php if (empty($ordenes)): ?>
            <div class="alert alert-info mb-0">
              No hay órdenes para la fecha seleccionada.
            </div>
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
                    <th style="width:80px;">N°</th>
                    <th style="width:90px;">Hora</th>
                    <th>Cliente</th>
                    <th>Equipo</th>
                    <th style="width:140px;">Estado</th>
                    <th style="width:230px;">Acciones</th>
                  </tr>
                </thead>

                <tbody>
                  <?php foreach ($ordenes as $o): ?>
                    <?php
                    $hora = date('H:i', strtotime($o['fecha_ingreso']));

                    $clienteFmt = title_case_smart($o['cliente_nombre'] ?? '');
                    $marcaFmt   = title_case_smart($o['equipo_marca'] ?? '');
                    $modeloFmt  = title_case_smart($o['equipo_modelo'] ?? '');
                    $equipo     = trim($marcaFmt . ' ' . $modeloFmt);
                    if ($equipo === '') $equipo = 'Sin datos';

                    // PDF + Seguimiento (con token si existe)
                    if (!empty($o['token_publico'])) {
                        $pdfUrl = $baseUrl . '/order/orden_pdf_publico.php?token=' . urlencode($o['token_publico']);
                        $segUrl = $baseUrl . '/order/seguimiento_orden.php?token=' . urlencode($o['token_publico']);
                    } else {
                        $pdfUrl = $baseUrl . '/order/orden_pdf.php?id=' . (int)$o['id'];
                        $segUrl = $baseUrl . '/order/seguimiento_orden.php';
                    }

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

                    // WhatsApp
                    $waText =
                        "Orden de servicio N° {$numeroOrden}"
                        . "\nCliente: " . ($clienteFmt !== '' ? $clienteFmt : ($o['cliente_nombre'] ?? ''))
                        . "\nEquipo: " . $equipo
                        . "\nEstado: " . $estadoTxt
                        . "\n\nComprobante PDF: " . $pdfUrl
                        . "\nSeguimiento: " . $segUrl;

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

                    $oid = (int)$o['id'];
                    ?>

                    <tr class="order-row st-<?php echo htmlspecialchars($estadoKey); ?>"
                        <?php if ($isMobile): ?>
                          onclick="window.location.href='<?php echo APP_URL; ?>/order/orden_detalle.php?id=<?php echo $oid; ?>';"
                          style="cursor:pointer;"
                        <?php endif; ?>
                    >
                      <td><?php echo $numeroOrden; ?></td>
                      <td><?php echo htmlspecialchars($hora); ?></td>
                      <td><?php echo htmlspecialchars($clienteFmt !== '' ? $clienteFmt : (string)($o['cliente_nombre'] ?? '')); ?></td>

                      <td style="line-height:1.15">
                        <?php echo htmlspecialchars($equipo); ?>
                        <?php if ($isMobile): ?>
                          <div class="estado-mini"><?php echo htmlspecialchars($estadoShort); ?></div>
                        <?php endif; ?>
                      </td>

                      <td><?php echo badgeEstado($o['estado_actual'] ?? ''); ?></td>

                      <td class="acciones-col">
                        <a href="<?php echo APP_URL; ?>/order/orden_detalle.php?id=<?php echo $oid; ?>"
                           class="btn btn-sm btn-outline-primary">Ver</a>

                        <a href="<?php echo APP_URL; ?>/order/orden_editar.php?id=<?php echo $oid; ?>"
                           class="btn btn-sm btn-outline-secondary">Editar</a>

                        <a href="<?php echo htmlspecialchars($waUrl); ?>"
                           target="_blank" class="btn btn-sm btn-success">WhatsApp</a>
                      </td>
                    </tr>

                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

          <?php endif; ?>

        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
