<?php
// order/seguimiento_orden.php (PUBLICO)

// ✅ Config público multi-cliente (usa instance + respeta SYSTEC_APP_URL)
require_once __DIR__ . '/../config/config_publico.php';

// --- Helpers ---
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

// ✅ Formato SOLO UI (no toca BD)
function title_case_smart($str){
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}
function equipo_title($marca, $modelo){
    $marca = title_case_smart($marca);
    $modelo = title_case_smart($modelo);
    $txt = trim($marca . ' ' . $modelo);
    return $txt !== '' ? $txt : '—';
}

// Acción del form (misma página, pero por RUTA de instancia)
$actionUrl = url('/order/seguimiento_orden.php');

// ------------------------------
// 1) Token (GET o POST)
// ------------------------------
$token = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = trim($_POST['token'] ?? '');
} else {
    $token = trim($_GET['token'] ?? '');
}

if ($token === '' || strlen($token) < 16) {
    http_response_code(404);
    exit('Token no válido.');
}

// ------------------------------
// 2) Buscar orden por token
// ------------------------------
$stmt = $pdo->prepare("
    SELECT
        id, numero_orden, cliente_nombre, cliente_telefono,
        equipo_marca, equipo_modelo, fecha_ingreso,
        estado_actual, diagnostico, costo_repuestos, costo_mano_obra, costo_total,
        firma_ruta, requiere_firma
    FROM ordenes
    WHERE token_publico = :t
    LIMIT 1
");
$stmt->execute([':t' => $token]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    http_response_code(404);
    exit('Orden no encontrada.');
}

$ordenId = (int)$orden['id'];

// número orden robusto
$numeroOrden = (int)($orden['numero_orden'] ?? 0);
if ($numeroOrden <= 0) $numeroOrden = $ordenId;

// ✅ Cliente / equipo formateados para UI
$clienteNombreUi = title_case_smart($orden['cliente_nombre'] ?? '');
$equipoUi = equipo_title($orden['equipo_marca'] ?? '', $orden['equipo_modelo'] ?? '');

// fecha bonita
$fechaIng = '';
if (!empty($orden['fecha_ingreso'])) {
    $ts = strtotime($orden['fecha_ingreso']);
    $fechaIng = $ts ? date('d-m-Y H:i', $ts) : (string)$orden['fecha_ingreso'];
}

// ------------------------------
// 2.1) Evidencias visibles (público)
// ------------------------------
$stmtEv = $pdo->prepare("
    SELECT id, tipo, comentario, fecha
    FROM ordenes_evidencias
    WHERE orden_id = :id
      AND visible_cliente = 1
    ORDER BY id DESC
    LIMIT 12
");
$stmtEv->execute([':id' => $ordenId]);
$evidenciasPublicas = $stmtEv->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------
// 3) Parámetros (nombre/logo/whatsapp/etc)
// ------------------------------
function getParametro($clave, $default = '')
{
    global $pdo;
    try {
        $s = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :c LIMIT 1");
        $s->execute([':c' => $clave]);
        $f = $s->fetch(PDO::FETCH_ASSOC);
        if ($f && $f['valor'] !== '') return $f['valor'];
    } catch (Exception $e) {}
    return $default;
}

$nombreNegocio = getParametro('nombre_negocio', 'SysTec');
$whatsapp      = getParametro('whatsapp', '');
$direccion     = getParametro('direccion', '');
$horario       = getParametro('horario_taller', '');
$logoNegocio   = getParametro('logo_ruta', '');

// ✅ Logo: si es URL absoluta, úsalo. Si es ruta relativa, debe servirse desde el CORE (core_url)
$logoUrl = '';
if ($logoNegocio) {
    if (preg_match('#^https?://#i', $logoNegocio)) {
        $logoUrl = $logoNegocio;
    } else {
        $logoUrl = core_url('/' . ltrim($logoNegocio, '/'));
    }
}

// ------------------------------
// 4) Render
// ------------------------------

// costos CLP
$cRep = (float)($orden['costo_repuestos'] ?? 0);
$cMO  = (float)($orden['costo_mano_obra'] ?? 0);
$cTot = (float)($orden['costo_total'] ?? 0);

// WhatsApp link seguro
$waDigits = preg_replace('/\D+/', '', (string)$whatsapp);
$waLink = '';
if ($waDigits !== '') {
    $msg = 'Hola, vengo desde el seguimiento de mi orden #' . $numeroOrden;
    $waLink = 'https://wa.me/' . $waDigits . '?text=' . rawurlencode($msg);
}

?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?php echo h($nombreNegocio); ?> · Seguimiento de Orden</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;background:#f3f4f6;margin:0}
    .wrap{max-width:820px;margin:24px auto;padding:0 14px}
    .card{background:#fff;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.08);overflow:hidden}
    .hd{padding:18px 18px;border-bottom:1px solid #e5e7eb;display:flex;gap:14px;align-items:center}
    .hd img{max-height:52px}
    .hd h1{font-size:18px;margin:0}
    .bd{padding:18px}
    .row{display:flex;flex-wrap:wrap;gap:12px}
    .pill{background:#111827;color:#fff;border-radius:999px;padding:6px 12px;font-size:13px;display:inline-block}
    .muted{color:#6b7280;font-size:13px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    .box{border:1px solid #e5e7eb;border-radius:10px;padding:12px}
    .box h3{margin:0 0 8px;font-size:14px}
    .btn{display:inline-block;border-radius:10px;padding:10px 12px;text-decoration:none;border:0;cursor:pointer}
    .btnw{background:#16a34a;color:#fff}
    .btns{background:#111827;color:#fff}
    .mt{margin-top:12px}

    .evi-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
    .evi-grid img{width:100%;height:140px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb;background:#f8f9fa}
    .evi-meta{margin-top:6px;font-size:12px}
    .evi-meta strong{display:block}

    @media(max-width:700px){
      .grid{grid-template-columns:1fr}
      .evi-grid{grid-template-columns:repeat(2,1fr)}
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="hd">
        <?php if ($logoUrl): ?><img src="<?php echo h($logoUrl); ?>" alt="Logo"><?php endif; ?>
        <div>
          <h1><?php echo h($nombreNegocio); ?> · Seguimiento de Orden</h1>
          <div class="muted">
            Orden #<?php echo (int)$numeroOrden; ?> · Cliente: <?php echo h($clienteNombreUi); ?>
            <?php if ($fechaIng): ?> · Ingreso: <?php echo h($fechaIng); ?><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="bd">
        <div class="row">
          <span class="pill"><?php echo h($orden['estado_actual'] ?? ''); ?></span>
          <?php if ($direccion): ?><span class="muted"><?php echo h($direccion); ?></span><?php endif; ?>
          <?php if ($horario): ?><span class="muted">· <?php echo h($horario); ?></span><?php endif; ?>
        </div>

        <div class="grid mt">
          <div class="box">
            <h3>Equipo</h3>
            <div><?php echo h($equipoUi); ?></div>
          </div>
          <div class="box">
            <h3>Costos</h3>
            <div class="muted">
              Repuestos: <?php echo '$' . number_format($cRep, 0, ',', '.'); ?><br>
              Mano de obra: <?php echo '$' . number_format($cMO, 0, ',', '.'); ?><br>
              <strong>Total: <?php echo '$' . number_format($cTot, 0, ',', '.'); ?></strong>
            </div>
          </div>
        </div>

        <?php if (!empty($orden['diagnostico'])): ?>
          <div class="box mt">
            <h3>Diagnóstico</h3>
            <div class="muted"><?php echo nl2br(h($orden['diagnostico'])); ?></div>
          </div>
        <?php endif; ?>

        <?php if (!empty($evidenciasPublicas)): ?>
          <div class="box mt">
            <h3>Evidencias</h3>

            <div class="evi-grid">
              <?php foreach ($evidenciasPublicas as $ev): ?>
                <?php
                  $fullUrl  = url('/order/evidencia_publica.php?token=' . urlencode($token) . '&id=' . (int)$ev['id']);

                  $thumbUrl = url('/order/evidencia_publica.php?token=' . urlencode($token) . '&id=' . (int)$ev['id'] . '&thumb=1');
                ?>
                <a href="<?php echo h($fullUrl); ?>" target="_blank" style="display:block;text-decoration:none">
                  <img src="<?php echo h($thumbUrl); ?>" alt="Evidencia">
                  <div class="muted evi-meta">
                    <strong><?php echo h($ev['tipo'] ?? ''); ?></strong>
                    <?php if (!empty($ev['comentario'])): ?>
                      <div><?php echo h($ev['comentario']); ?></div>
                    <?php endif; ?>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <div class="mt">
          <?php if ($waLink): ?>
            <a class="btn btnw" target="_blank" href="<?php echo h($waLink); ?>">Hablar por WhatsApp</a>
          <?php endif; ?>

          <!-- volver a consultar (mismo token) -->
          <form method="post" action="<?php echo h($actionUrl); ?>" style="display:inline">
            <input type="hidden" name="token" value="<?php echo h($token); ?>">
            <button class="btn btns" type="submit">Actualizar estado</button>
          </form>
        </div>

      </div>
    </div>
  </div>
</body>
</html>
