<?php
// order/ticket_view.php
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

// Cargar orden
$stmt = $pdo->prepare("SELECT id, numero_orden, token_publico FROM ordenes WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
  header('Location: ' . url('/dashboard.php'));
  exit;
}

$numeroOrden = str_pad((string)($orden['numero_orden'] ?? ''), 4, '0', STR_PAD_LEFT);

// ✅ Siempre 80mm (tu “88mm”)
$W_FIXED = 80;

// Costos 0/1
$costos = (int)($_GET['costos'] ?? 0);
$costos = ($costos === 1) ? 1 : 0;

// Auto-print 0/1 (si viene en 1, dispara imprimir una vez)
$autoprint = (int)($_GET['autoprint'] ?? 0);
$autoprint = ($autoprint === 1) ? 1 : 0;

// URL ticket
if (!empty($orden['token_publico'])) {
  $ticketBase = url('/order/orden_ticket.php?token=' . urlencode($orden['token_publico']));
} else {
  $ticketBase = url('/order/orden_ticket.php?id=' . (int)$orden['id']);
}

$ticketUrl = $ticketBase . '&w=' . $W_FIXED;
if ($costos === 1) $ticketUrl .= '&costos=1';

// Volver
$return = trim((string)($_GET['return'] ?? ''));
if ($return === '') {
  $returnUrl = url('/order/orden_detalle.php?id=' . (int)$orden['id']);
} else {
  if (stripos($return, 'http') === 0) $return = '';
  $returnUrl = url('/' . ltrim($return, '/'));
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.ticket-frame{
  width: 100%;
  min-height: 80vh;
  border: 1px solid #eaeaea;
  border-radius: 10px;
  background: #fff;
}
.toolbar{ gap:8px; }
@media (max-width: 767.98px){
  .ticket-frame{ min-height: 75vh; }
}
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 class="page-title mb-0">Ticket — Orden N° <?php echo htmlspecialchars($numeroOrden); ?></h4>
          <small class="text-muted">80mm fijo · sin pestañas · sin botones de ancho 😄</small>
        </div>

        <div class="d-flex flex-wrap toolbar">
          <a href="<?php echo htmlspecialchars($returnUrl); ?>" class="btn btn-outline-primary btn-sm">
            <i class="la la-arrow-left mr-1"></i> Volver
          </a>

          <button type="button" class="btn btn-secondary btn-sm" onclick="printTicket()">
            <i class="la la-print mr-1"></i> Imprimir
          </button>

          <a href="<?php echo url('/order/orden_detalle.php?id='.(int)$orden['id']); ?>" class="btn btn-outline-dark btn-sm">
            <i class="la la-file-text mr-1"></i> Detalle
          </a>
        </div>
      </div>

      <div class="card">
        <div class="card-body">

          <!-- Solo costos, si quieres mantenerlo -->
          <div class="d-flex flex-wrap align-items-center mb-3" style="gap:10px;">
            <div class="btn-group btn-group-sm" role="group" aria-label="Costos">
              <a class="btn btn-<?php echo ($costos===0?'primary':'outline-primary'); ?>"
                 href="<?php echo url('/order/ticket_view.php?id='.(int)$orden['id'].'&costos=0&return='.urlencode(ltrim(parse_url($returnUrl, PHP_URL_PATH) ?? '', '/'))); ?>">
                Sin costos
              </a>

              <a class="btn btn-<?php echo ($costos===1?'primary':'outline-primary'); ?>"
                 href="<?php echo url('/order/ticket_view.php?id='.(int)$orden['id'].'&costos=1&return='.urlencode(ltrim(parse_url($returnUrl, PHP_URL_PATH) ?? '', '/'))); ?>">
                Con costos
              </a>
            </div>

            <small class="text-muted">Cambia costos sin abrir pestañas.</small>
          </div>

          <iframe
            id="ticketFrame"
            class="ticket-frame"
            src="<?php echo htmlspecialchars($ticketUrl); ?>"
            loading="eager"
            referrerpolicy="no-referrer"
          ></iframe>

        </div>
      </div>

    </div>
  </div>
</div>

<script>
const AUTO_PRINT = <?php echo (int)$autoprint; ?>;

function printTicket(){
  const f = document.getElementById('ticketFrame');
  if (!f) return;

  // Nota realista: el navegador SIEMPRE muestra el diálogo.
  try {
    f.contentWindow.focus();
    f.contentWindow.print();
  } catch (e) {
    // fallback: navega al ticket y deja que su print=1 lo maneje si existe
    window.location.href = <?php echo json_encode($ticketUrl . '&print=1', JSON_UNESCAPED_SLASHES); ?>;
  }
}

// Auto-print una sola vez cuando carga el iframe
(function(){
  if (!AUTO_PRINT) return;

  const f = document.getElementById('ticketFrame');
  if (!f) return;

  let fired = false;
  f.addEventListener('load', function(){
    if (fired) return;
    fired = true;
    // pequeño delay para que renderice el ticket
    setTimeout(function(){
      printTicket();
      // al cerrar imprimir, volvemos solos
      window.onafterprint = function(){
        window.location.href = <?php echo json_encode($returnUrl, JSON_UNESCAPED_SLASHES); ?>;
      };
    }, 250);
  });
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
