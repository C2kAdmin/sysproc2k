<?php
// order/firmas_pendientes.php

require_once __DIR__ . '/../config/auth.php';

// ✅ ADMIN y RECEPCION (SUPER_ADMIN siempre pasa por auth.php)
require_role(['ADMIN', 'RECEPCION']);

// ✅ Helper robusto (soporta latin1/utf8 y deja Title Case)
if (!function_exists('title_case_smart')) {
    function title_case_smart($str) {
        $str = trim((string)$str);
        if ($str === '') return '';

        // Si viene en latin1 (común), lo pasamos a UTF-8
        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }

        // Normaliza espacios
        $str = preg_replace('/\s+/', ' ', $str);

        // Title Case real
        $str = mb_strtolower($str, 'UTF-8');
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';

// Detectar móvil vs PC (simple y suficiente)
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = (bool)preg_match('/Android|iPhone|iPad|iPod|Mobile/i', $ua);

// ✅ Paginación
$perPage = 10;
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

// Helper SQL: normaliza estado_actual quitando tildes (igual que dashboard)
$SQL_ESTADO_NORM = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(estado_actual)),
                    'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U')";

$whereSql = "
    FROM ordenes
    WHERE requiere_firma = 1
      AND (firma_ruta IS NULL OR firma_ruta = '')
      AND {$SQL_ESTADO_NORM} <> 'ENTREGADO'
";
// Total
$stmt = $pdo->prepare("SELECT COUNT(*) " . $whereSql);
$stmt->execute();
$total = (int)$stmt->fetchColumn();

$totalPages = (int)ceil($total / $perPage);
if ($totalPages < 1) $totalPages = 1;
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Datos paginados
$stmt = $pdo->prepare("
    SELECT
        id,
        numero_orden,
        cliente_nombre,
        cliente_telefono,
        equipo_marca,
        equipo_modelo,
        fecha_ingreso
    " . $whereSql . "
    ORDER BY fecha_ingreso DESC
    LIMIT :lim OFFSET :off
");
$stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
$stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
$stmt->execute();
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// URL de paginación
function firmas_pendientes_url($p) {
    return url('/order/firmas_pendientes.php?p=' . (int)$p);
}

// Render paginación simple (tipo “orden_detalle”)
function render_pagination($page, $totalPages) {
    if ($totalPages <= 1) return;

    $prev = $page - 1;
    $next = $page + 1;

    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);

    echo '<nav aria-label="Paginación" class="mt-2">';
    echo '<ul class="pagination pagination-sm mb-0">';

    // Prev
    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(firmas_pendientes_url($prev)) . '">«</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">«</span></li>';
    }

    // 1 + dots
    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(firmas_pendientes_url(1)) . '">1</a></li>';
        if ($start > 2) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
    }

    // Window
    for ($i = $start; $i <= $end; $i++) {
        if ($i === (int)$page) {
            echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(firmas_pendientes_url($i)) . '">' . $i . '</a></li>';
        }
    }

    // dots + last
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(firmas_pendientes_url($totalPages)) . '">' . $totalPages . '</a></li>';
    }

    // Next
    if ($page < $totalPages) {
        echo '<li class="page-item"><a class="page-link" href="' . htmlspecialchars(firmas_pendientes_url($next)) . '">»</a></li>';
    } else {
        echo '<li class="page-item disabled"><span class="page-link">»</span></li>';
    }

    echo '</ul>';
    echo '</nav>';
}
?>

<style>
/* Header responsive */
.fp-head{ display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.fp-title h4{ margin:0; }
.fp-actions{ display:flex; gap:8px; flex-wrap:wrap; justify-content:flex-end; }
@media (max-width: 767.98px){
  .fp-head{ flex-direction:column; }
  .fp-actions{ width:100%; justify-content:flex-start; }
}

/* Cards móvil para evitar scroll horizontal */
.firma-card{
  border:1px solid #eee;
  border-radius:12px;
  background:#fff;
  padding:12px;
  margin-bottom:10px;
}
.firma-card .f-top{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
}
.firma-card .f-ord{
  font-weight:700;
  margin:0;
  line-height:1.15;
}
.firma-card .f-sub{
  font-size:12px;
  color:#6b7280;
}
.firma-card .f-meta{
  margin-top:8px;
  font-size:13px;
}
.firma-card .f-meta div{ margin-bottom:4px; }
.firma-card .f-actions{
  margin-top:10px;
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}
.firma-card .f-actions .btn{
  flex:1 1 calc(50% - 8px);
}
</style>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <div class="fp-head mb-3">
                <div class="fp-title">
                    <h4 class="page-title mb-0">Firmas pendientes</h4>
                    <small class="text-muted">
                        Órdenes que requieren la firma del cliente en pantalla.
                        <?php if ($total > 0): ?>
                            · Total: <?php echo (int)$total; ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="fp-actions">
                    <?php render_pagination($page, $totalPages); ?>
                </div>
            </div>

            <div class="card">
                <div class="card-body">

                    <?php if (!$isMobile): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th># Orden</th>
                                        <th>Cliente</th>
                                        <th>Equipo</th>
                                        <th>Fecha ingreso</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>

                                    <?php if (empty($ordenes)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">
                                                No hay órdenes pendientes de firma.
                                            </td>
                                        </tr>
                                    <?php else: ?>

                                        <?php foreach ($ordenes as $o): ?>
                                            <?php
                                                $clienteFmt = title_case_smart($o['cliente_nombre'] ?? '');
                                                $marcaFmt   = title_case_smart($o['equipo_marca'] ?? '');
                                                $modeloFmt  = title_case_smart($o['equipo_modelo'] ?? '');
                                                $equipoFmt  = trim($marcaFmt . ' ' . $modeloFmt);
                                                if ($equipoFmt === '') $equipoFmt = 'Sin datos';

                                                $fechaFmt = '—';
                                                if (!empty($o['fecha_ingreso'])) {
                                                    $fechaFmt = date('d-m-Y H:i', strtotime((string)$o['fecha_ingreso']));
                                                }
                                            ?>
                                            <tr>
                                                <td><?php echo (int)$o['numero_orden']; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($clienteFmt !== '' ? $clienteFmt : (string)($o['cliente_nombre'] ?? '')); ?><br>
                                                    <small><?php echo htmlspecialchars((string)($o['cliente_telefono'] ?? '')); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($equipoFmt); ?></td>
                                                <td><?php echo htmlspecialchars($fechaFmt); ?></td>
                                                <td>
                                                    <a href="<?php echo url('/order/orden_detalle.php?id=' . (int)$o['id'] . '&return=' . urlencode('order/firmas_pendientes.php?p=' . (int)($_GET['p'] ?? 1))); ?>"
   class="btn btn-sm btn-secondary mb-1">
    Ver
</a>
<a href="<?php echo url('/order/firma_registrar.php?id=' . (int)$o['id'] . '&return=' . urlencode('order/firmas_pendientes.php?p=' . (int)($_GET['p'] ?? 1))); ?>"
                                                       class="btn btn-sm btn-success mb-1">
                                                        Registrar firma
                                                    </a>
</td>
                                            </tr>
                                        <?php endforeach; ?>

                                    <?php endif; ?>

                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($isMobile): ?>
                        <?php if (empty($ordenes)): ?>
                            <div class="text-center text-muted">
                                No hay órdenes pendientes de firma.
                            </div>
                        <?php else: ?>

                            <?php foreach ($ordenes as $o): ?>
                                <?php
                                    $clienteFmt = title_case_smart($o['cliente_nombre'] ?? '');
                                    $marcaFmt   = title_case_smart($o['equipo_marca'] ?? '');
                                    $modeloFmt  = title_case_smart($o['equipo_modelo'] ?? '');
                                    $equipoFmt  = trim($marcaFmt . ' ' . $modeloFmt);
                                    if ($equipoFmt === '') $equipoFmt = 'Sin datos';

                                    $ordenNum = (int)($o['numero_orden'] ?? 0);
                                    $telefono = (string)($o['cliente_telefono'] ?? '');

                                    $fechaFmt = '—';
                                    if (!empty($o['fecha_ingreso'])) {
                                        $fechaFmt = date('d-m-Y H:i', strtotime((string)$o['fecha_ingreso']));
                                    }
                                ?>

                                <div class="firma-card">
                                    <div class="f-top">
                                        <div>
                                            <p class="f-ord">Orden #<?php echo $ordenNum; ?></p>
                                            <div class="f-sub"><?php echo htmlspecialchars($telefono); ?></div>
                                        </div>
                                        <span class="badge badge-info">Firma</span>
                                    </div>

                                    <div class="f-meta">
                                        <div><strong>Cliente:</strong> <?php echo htmlspecialchars($clienteFmt !== '' ? $clienteFmt : (string)($o['cliente_nombre'] ?? '')); ?></div>
                                        <div><strong>Equipo:</strong> <?php echo htmlspecialchars($equipoFmt); ?></div>
                                        <div><strong>Ingreso:</strong> <?php echo htmlspecialchars($fechaFmt); ?></div>
                                    </div>

                                    <div class="f-actions">
                                        <a href="<?php echo url('/order/orden_detalle.php?id=' . (int)$o['id'] . '&return=' . urlencode('order/firmas_pendientes.php?p=' . (int)($_GET['p'] ?? 1))); ?>"
                                           class="btn btn-secondary btn-sm">
                                            Ver
                                        </a>
<a href="<?php echo url('/order/firma_registrar.php?id=' . (int)$o['id'] . '&return=' . urlencode('order/firmas_pendientes.php?p=' . (int)($_GET['p'] ?? 1))); ?>"
                                           class="btn btn-success btn-sm">
                                            Registrar firma
                                        </a>
</div>
                                </div>

                            <?php endforeach; ?>

                            <div class="d-flex justify-content-center">
                                <?php render_pagination($page, $totalPages); ?>
                            </div>

                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (!$isMobile): ?>
                        <div class="d-flex justify-content-end">
                            <?php render_pagination($page, $totalPages); ?>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
