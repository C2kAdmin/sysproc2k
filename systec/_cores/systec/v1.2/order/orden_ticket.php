<?php
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('America/Santiago');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* ==========================================================
   🔠 CONTROL DE TAMAÑO DE LETRA (REFERENCIA DISEÑO)
   ----------------------------------------------------------
   Referencia mental:
   - Antes ≈ 10pt
   - Ahora ≈ 12pt (correcto para ticket térmico)
   ----------------------------------------------------------
   👉 Si quieres MÁS grande: sube +1
   👉 Si quieres MÁS chico: baja -1
   ========================================================== */

$FONT_BASE_80 = 18.5; // texto normal 80mm  (≈12pt)
$FONT_BASE_58 = 17.0; // texto normal 58mm

$FONT_SMALL_80 = 15.0; // textos secundarios
$FONT_SMALL_58 = 14.0;

/* ========================================================== */

// Title Case SOLO visual
function title_case($str){
    $str = trim((string)$str);
    if ($str === '') return '';
    $str = mb_strtolower($str, 'UTF-8');
    return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
}

// =======================
// PARAMETROS
// =======================
function getParametro($clave, $default = ''){
    global $pdo;
    try{
        $s = $pdo->prepare("SELECT valor FROM parametros WHERE clave = :c LIMIT 1");
        $s->execute([':c' => $clave]);
        $f = $s->fetch(PDO::FETCH_ASSOC);
        return ($f && $f['valor'] !== '') ? $f['valor'] : $default;
    }catch(Exception $e){
        return $default;
    }
}

// =======================
// INPUT
// =======================
$id     = (int)($_GET['id'] ?? 0);
$token  = trim((string)($_GET['token'] ?? ''));
$w      = (int)($_GET['w'] ?? 80);
$w      = ($w === 58) ? 58 : 80;
$costos = (int)($_GET['costos'] ?? 0);

if ($id <= 0 && $token === '') {
    exit('Falta id o token');
}

// =======================
// ORDEN
// =======================
if ($token !== '') {
    // acceso público / iframe / modal
    $stmt = $pdo->prepare("SELECT * FROM ordenes WHERE token_publico = :t LIMIT 1");
    $stmt->execute([':t' => $token]);
} else {
    // acceso interno (dashboard)
    if (!isset($_SESSION['usuario_id'])) {
        http_response_code(403);
        exit;
    }
    $stmt = $pdo->prepare("SELECT * FROM ordenes WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
}
$orden = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$orden) exit('Orden no encontrada');

// =======================
// FLAGS
// =======================
$autoprint = (($_GET['print'] ?? '') === '1') || (($_GET['autoprint'] ?? '') === '1');

// =======================
// DATOS (FORMATO VISUAL)
// =======================
$numeroOrden = (string)($orden['numero_orden'] ?? '');
$cliente     = title_case($orden['cliente_nombre'] ?? '');
$telefono    = (string)($orden['cliente_telefono'] ?? '');
$equipo      = title_case(trim(($orden['equipo_marca'] ?? '') . ' ' . ($orden['equipo_modelo'] ?? '')));
$estado      = title_case($orden['estado_actual'] ?? '');
$diag        = trim((string)($orden['diagnostico'] ?? ''));

$rep   = (float)($orden['costo_repuestos'] ?? 0);
$mano  = (float)($orden['costo_mano_obra'] ?? 0);
$total = (float)($orden['costo_total'] ?? 0);

function money_clp($n){
    return '$' . number_format((float)$n, 0, ',', '.');
}

// =======================
// NEGOCIO
// =======================
$nombreNegocio = getParametro('nombre_negocio', 'Servicio Técnico');
$direccion     = getParametro('direccion', '');
$whatsapp      = getParametro('whatsapp', '');
$logoRuta      = getParametro('logo_ruta', '');
$pieOrden      = getParametro('pie_orden', 'Gracias por confiar en nosotros.');

// Logo ABS (respeta subcarpeta/instancia)
$logoUrl = '';
if ($logoRuta !== '') {

    // si ya viene absoluto, listo
    if (preg_match('#^https?://#i', $logoRuta)) {
        $logoUrl = $logoRuta;

    } else {

        // 1) armamos URL "correcta" con helper del sistema (incluye APP_URL / subcarpeta)
        $rel = ltrim($logoRuta, '/');
        $u = function_exists('url') ? url('/' . $rel) : ('/' . $rel);

        // 2) la convertimos a absoluta (scheme + host + path)
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // si url() ya devolvió absoluto, lo respetamos
        if (preg_match('#^https?://#i', $u)) {
            $logoUrl = $u;
        } else {
            $logoUrl = $scheme . $host . $u;
        }
    }
}
// =======================
// QR WhatsApp
// =======================
$waDigits = preg_replace('/\D+/', '', $whatsapp);
$qrUrl = '';
if ($waDigits !== '') {
    $waLink = 'https://wa.me/'.$waDigits;
    $qrUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data='.urlencode($waLink);
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Ticket</title>

<style>
@page { margin:0; }

body{
  font-family: monospace;
  font-size: <?php echo ($w === 58 ? $FONT_BASE_58 : $FONT_BASE_80); ?>px;
  font-weight: normal;
  margin:0;
}

.ticket{
  width: <?php echo (int)$w; ?>mm;
  padding: 3mm;
}

.center{ text-align:center; }

.sep{
  border-top:1px dashed #000;
  margin:7px 0;
}

.small{
  font-size: <?php echo ($w === 58 ? $FONT_SMALL_58 : $FONT_SMALL_80); ?>px;
}

/* LOGO (alto contraste para ticket térmico) */
.logo{
  max-width: <?php echo ($w === 58 ? 28 : 36); ?>mm;
  margin:0 auto 4px;
  display:block;

  /* 🔥 magia ticket */
  filter: grayscale(100%) contrast(160%) brightness(85%);
}
/* QR (reducido) */
.qr{
  width: <?php echo ($w === 58 ? 24 : 30); ?>mm;
  margin:4px auto;
  display:block;
}
</style>
</head>

<body>
<div class="ticket">

  <?php if ($logoUrl): ?>
    <img src="<?php echo h($logoUrl); ?>" class="logo" alt="Logo">
  <?php endif; ?>

  <div class="center"><?php echo h($nombreNegocio); ?></div>
  <?php if ($direccion): ?><div class="center small"><?php echo h($direccion); ?></div><?php endif; ?>
  <?php if ($waDigits): ?><div class="center small">WhatsApp: <?php echo h($waDigits); ?></div><?php endif; ?>

  <div class="sep"></div>

  <div class="center">ORDEN N° <?php echo h($numeroOrden); ?></div>
  <div class="center small"><?php echo date('d-m-Y H:i'); ?></div>
  <div class="center small">Estado: <?php echo h($estado); ?></div>

  <div class="sep"></div>

  <div>Cliente: <?php echo h($cliente); ?></div>
  <?php if ($telefono): ?><div>Tel: <?php echo h($telefono); ?></div><?php endif; ?>
  <div>Equipo: <?php echo h($equipo); ?></div>

  <?php if ($diag !== ''): ?>
    <div class="sep"></div>
    <div>Diagnóstico</div>
    <div class="small"><?php echo nl2br(h($diag)); ?></div>
  <?php endif; ?>

  <?php if ($costos === 1): ?>
    <div class="sep"></div>
    <div>Costos</div>
    <div>Repuestos: <?php echo money_clp($rep); ?></div>
    <div>Mano obra: <?php echo money_clp($mano); ?></div>
    <div>Total: <?php echo money_clp($total); ?></div>
  <?php endif; ?>

  <?php if ($qrUrl): ?>
    <div class="sep"></div>
    <div class="center small">WhatsApp (escanea)</div>
    <img src="<?php echo h($qrUrl); ?>" class="qr" alt="QR WhatsApp">
    <div class="center small">wa.me/<?php echo h($waDigits); ?></div>
  <?php endif; ?>

  <div class="sep"></div>
  <div class="center small"><?php echo h($pieOrden); ?></div>

</div>

<?php if ($autoprint): ?>
<script>
window.onload = function(){ window.print(); }
</script>
<?php endif; ?>

</body>
</html>
