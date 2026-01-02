<?php
// order/evidencia_view.php
require_once __DIR__ . '/../config/auth.php';
require_login();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}
$stmt = $pdo->prepare("
    SELECT e.id, e.orden_id, e.tipo, e.comentario, e.visible_cliente, e.fecha, e.archivo
    FROM ordenes_evidencias e
    WHERE e.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.evi-wrap {
    background: #ffffff;
    border: 1px solid #eaeaea;
    border-radius: 12px;
    padding: 14px;
}
.evi-imgbox {
    background: #f8f9fa;          /* 👈 NO negro */
border-radius: 12px;
    padding: 10px;
    border: 1px solid #eee;
    display:flex;
    align-items:center;
    justify-content:center;
}
.evi-imgbox img {
    max-width: 100%;
    max-height: 72vh;
    object-fit: contain;          /* Respeta proporción */
border-radius: 10px;
    background: #fff;
}
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Evidencia #<?php echo (int)$ev['id']; ?></h4>

        <a class="btn btn-outline-primary btn-sm"
           href="<?php echo url('/order/orden_detalle.php?id='.(int)$ev['orden_id']); ?>">
          ← Volver a la orden
</a>
      </div>

      <div class="evi-wrap">
        <div class="mb-2">
          <span class="badge badge-secondary"><?php echo htmlspecialchars($ev['tipo']); ?></span>

          <?php if ((int)$ev['visible_cliente'] === 1): ?>
            <span class="badge badge-success">Visible cliente</span>
          <?php else: ?>
            <span class="badge badge-warning">Solo interno</span>
          <?php endif; ?>

          <span class="text-muted small ml-2">
            <?php echo date('Y-m-d H:i:s', strtotime($ev['fecha'])); ?>
          </span>
        </div>

        <?php if (!empty($ev['comentario'])): ?>
          <div class="mb-3"><?php echo nl2br(htmlspecialchars($ev['comentario'])); ?></div>
        <?php endif; ?>

        <div class="evi-imgbox">
          <img src="<?php echo url('/order/evidencia_ver.php?id='.(int)$ev['id']); ?>"
alt="Evidencia">
        </div>

        <div class="mt-3">
          <a class="btn btn-sm btn-dark"
             href="<?php echo url('/order/orden_evidencias.php?id='.(int)$ev['orden_id']); ?>">
            Abrir evidencias
</a>

          <a class="btn btn-sm btn-outline-secondary"
             target="_blank"
             rel="noopener"
             href="<?php echo url('/order/evidencia_ver.php?id='.(int)$ev['id']); ?>">
            Abrir archivo original
</a>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
