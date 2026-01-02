<?php
// order/superadmin_pos_print.php

require_once __DIR__ . '/../config/auth.php'; // incluye config.php / session / url()
require_super_admin();

date_default_timezone_set('America/Santiago');

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* =========================
   PARAMETROS (DB)
========================= */
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

function param_set(string $clave, string $valor, &$errMsg = ''): bool {
    global $pdo;
    try {
        // UP SERT seguro (tu tabla tiene UNIQUE en clave)
        $sql = "INSERT INTO parametros (clave, valor)
                VALUES (:c, :v)
                ON DUPLICATE KEY UPDATE valor = :v2";
        $s = $pdo->prepare($sql);
        return $s->execute([':c' => $clave, ':v' => $valor, ':v2' => $valor]);
    } catch (Exception $e) {
        $errMsg = $e->getMessage();
        return false;
    }
}

/* =========================
   NORMALIZADORES / VALIDACIONES
========================= */
function is_valid_ipv4($ip): bool {
    return (bool)filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
}

function normalize_base_url(string $base): string {
    $base = trim($base);
    if ($base === '') return '';

    // permitir "192.168.2.157" sin http
    if (!preg_match('#^https?://#i', $base)) {
        $base = 'http://' . $base;
    }

    $p = @parse_url($base);
    if (!$p || empty($p['host'])) return '';

    $scheme = strtolower($p['scheme'] ?? 'http');
    if ($scheme !== 'http' && $scheme !== 'https') $scheme = 'http';

    $host = $p['host'];
    $port = isset($p['port']) ? (int)$p['port'] : null;

    $out = $scheme . '://' . $host;
    if ($port) $out .= ':' . $port;

    return rtrim($out, "/ \t\n\r\0\x0B");
}

function normalize_path(string $path): string {
    $path = trim($path);
    if ($path === '') return '';
    if ($path[0] !== '/') $path = '/' . $path;
    $path = preg_replace('#/+#', '/', $path); // limpia dobles //
    return rtrim($path, " \t\n\r\0\x0B");
}

/* =========================
   ANTI REPOST / CSRF
========================= */
if (!isset($_SESSION['anti_repost_pos_print'])) {
    $_SESSION['anti_repost_pos_print'] = bin2hex(random_bytes(16));
}
$antiToken = $_SESSION['anti_repost_pos_print'];

/* =========================
   FLASH (PRG)
========================= */
$flash_ok = $_SESSION['flash_posprint_ok'] ?? '';
$flash_er = $_SESSION['flash_posprint_er'] ?? '';
unset($_SESSION['flash_posprint_ok'], $_SESSION['flash_posprint_er']);

/* =========================
   POST → GUARDAR + REDIRIGIR
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $errores = [];

    $formToken = $_POST['_anti_token'] ?? '';
    if ($formToken === '' || !hash_equals($antiToken, $formToken)) {
        $errores[] = 'El formulario ya fue enviado o expiró. Recarga la página.';
    } else {

        // Rotamos token (anti refresh duplicado)
        $_SESSION['anti_repost_pos_print'] = bin2hex(random_bytes(16));

        $enabled = isset($_POST['pos_print_enabled']) ? '1' : '0';
        $base    = normalize_base_url((string)($_POST['pos_print_base'] ?? ''));
        $path    = normalize_path((string)($_POST['pos_print_path'] ?? ''));
        $gateway = trim((string)($_POST['pos_print_gateway'] ?? ''));

        // Validaciones
        if ($enabled === '1' && $base === '')  $errores[] = 'Si activas “En ASUS”, debes definir pos_print_base.';
        if ($enabled === '1' && $path === '')  $errores[] = 'Si activas “En ASUS”, debes definir pos_print_path.';

        if ($base !== '' && !preg_match('#^https?://#i', $base)) {
            $errores[] = 'pos_print_base inválido. Ej: http://192.168.2.157';
        }

        if ($path !== '' && !preg_match('#^/[a-zA-Z0-9_\-\/\.]+$#', $path)) {
            $errores[] = 'pos_print_path inválido. Ej: /pos_print/imprimir_pos.php';
        }

        if ($gateway !== '' && !is_valid_ipv4($gateway)) {
            $errores[] = 'Gateway inválido. Ej: 192.168.2.254';
        }

        // Guardar
        if (empty($errores)) {
            $dbErr = '';
            $ok = true;

            $ok = $ok && param_set('pos_print_enabled', $enabled, $dbErr);
            $ok = $ok && param_set('pos_print_base', $base, $dbErr);
            $ok = $ok && param_set('pos_print_path', $path, $dbErr);
            $ok = $ok && param_set('pos_print_gateway', $gateway, $dbErr);

            if ($ok) {
                $_SESSION['flash_posprint_ok'] = '✅ Guardado correctamente.';
            } else {
                $_SESSION['flash_posprint_er'] = '❌ No se pudo guardar. ' . ($dbErr ? ('Detalle: '.$dbErr) : 'Revisa BD/permisos.');
            }

            header('Location: ' . url('/order/superadmin_pos_print.php'));
            exit;
        }
    }

    // Si hubo errores de validación/CSRF → también PRG
    $_SESSION['flash_posprint_er'] = '❌ ' . implode(' | ', $errores);
    header('Location: ' . url('/order/superadmin_pos_print.php'));
    exit;
}

/* =========================
   GET → CARGAR VALORES
========================= */
$cur_enabled = (int)param_get('pos_print_enabled', '0');
$cur_base    = param_get('pos_print_base', '');
$cur_path    = param_get('pos_print_path', '/pos_print/imprimir_pos.php');
$cur_gateway = param_get('pos_print_gateway', '');

$preview = '';
if ($cur_enabled === 1 && $cur_base !== '' && $cur_path !== '') {
    $preview = rtrim($cur_base, '/') . $cur_path;
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<style>
/* ✅ FIX CHECKBOX (tu template lo ocultaba) */
.posprint-fix-check input[type="checkbox"]{
  position: static !important;
  opacity: 1 !important;
  width: 16px !important;
  height: 16px !important;
  margin-right: 8px !important;
  pointer-events: auto !important;
}
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <h4 class="page-title">Configuración impresión “En ASUS”</h4>

      <?php if ($flash_ok): ?>
        <div class="alert alert-success">
          <?php echo h($flash_ok); ?>
        </div>
      <?php endif; ?>

      <?php if ($flash_er): ?>
        <div class="alert alert-danger">
          <?php echo h($flash_er); ?>
        </div>
      <?php endif; ?>

      <div class="card">
        <div class="card-header">
          <h4 class="card-title mb-0">Ajustes por cliente (tabla <code>parametros</code>)</h4>
          <p class="card-category mb-0">
            Solo SUPER_ADMIN puede cambiar esto. Si cambias la IP de la ASUS, actualizas <code>pos_print_base</code> y listo.
          </p>
        </div>

        <div class="card-body">
          <form method="post"
                action="<?php echo h(url('/order/superadmin_pos_print.php')); ?>"
                autocomplete="off"
                class="posprint-fix-check">

            <input type="hidden" name="_anti_token" value="<?php echo h($antiToken); ?>">

            <div class="form-group">
              <label class="font-weight-bold" style="display:flex; align-items:center;">
                <input type="checkbox" name="pos_print_enabled" value="1" <?php echo $cur_enabled===1 ? 'checked' : ''; ?>>
                Activar botones “En ASUS” (móvil)
              </label>
              <small class="form-text text-muted">
                Si lo apagas, en móvil NO aparecerá “En ASUS”.
              </small>
            </div>

            <div class="form-group">
              <label>pos_print_base (IP / host)</label>
              <input type="text"
                     class="form-control"
                     name="pos_print_base"
                     value="<?php echo h($cur_base); ?>"
                     placeholder="http://192.168.2.157">
              <small class="form-text text-muted">
                IP o dominio. Si pones solo “192.168.2.157”, se guardará como <code>http://192.168.2.157</code>.
              </small>
            </div>

            <div class="form-group">
              <label>pos_print_path (ruta del proxy)</label>
              <input type="text"
                     class="form-control"
                     name="pos_print_path"
                     value="<?php echo h($cur_path); ?>"
                     placeholder="/pos_print/imprimir_pos.php">
              <small class="form-text text-muted">
                Normalmente: <code>/pos_print/imprimir_pos.php</code>
              </small>
            </div>

            <div class="form-group">
              <label>pos_print_gateway (puerta de enlace) — soporte</label>
              <input type="text"
                     class="form-control"
                     name="pos_print_gateway"
                     value="<?php echo h($cur_gateway); ?>"
                     placeholder="192.168.2.254">
              <small class="form-text text-muted">
                Queda guardado para soporte/red (no se usa para armar el link).
              </small>
            </div>

            <?php if ($preview): ?>
              <div class="alert alert-info">
                <strong>Preview proxy:</strong> <code><?php echo h($preview); ?></code>
              </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary">
              Guardar cambios
            </button>

            <a href="<?php echo h(url('/dashboard.php')); ?>" class="btn btn-secondary">
              Volver
            </a>

          </form>
        </div>
      </div>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
