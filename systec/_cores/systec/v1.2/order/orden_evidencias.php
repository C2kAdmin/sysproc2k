<?php
// order/orden_evidencias.php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

$ordenId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($ordenId <= 0) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

function image_from_any($path, &$typeOut = null) {
    $info = @getimagesize($path);
    if (!$info) return null;
    $typeOut = $info[2];

    switch ($typeOut) {
        case IMAGETYPE_JPEG: return @imagecreatefromjpeg($path);
        case IMAGETYPE_PNG:  return @imagecreatefrompng($path);
        case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
        default: return null;
    }
}

function exif_orient_path($path, $im) {
    if (!function_exists('exif_read_data')) return $im;

    $exif = @exif_read_data($path);
    if (!$exif || empty($exif['Orientation'])) return $im;

    $o = (int)$exif['Orientation'];
    switch ($o) {
        case 3: $im = imagerotate($im, 180, 0); break;
        case 6: $im = imagerotate($im, -90, 0); break;
        case 8: $im = imagerotate($im, 90, 0); break;
    }
    return $im;
}

function resize_keep_ratio($srcIm, $maxW, $maxH) {
    $w = imagesx($srcIm);
    $h = imagesy($srcIm);

    $scale = min($maxW / $w, $maxH / $h, 1);
    $nw = (int)floor($w * $scale);
    $nh = (int)floor($h * $scale);

    $dst = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefill($dst, 0, 0, $white);

    imagecopyresampled($dst, $srcIm, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

$msg = isset($_GET['msg']) ? trim((string)$_GET['msg']) : '';

/**
 * Guardar evidencia (POST)
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tipo = trim($_POST['tipo'] ?? 'General');
    $comentario = trim($_POST['comentario'] ?? '');
    $visible = isset($_POST['visible_cliente']) ? 1 : 0;

    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
        header('Location: ' . url('/order/orden_evidencias.php?id=' . $ordenId . '&msg=' . urlencode('Error al subir archivo.')));
        exit;
    }

    $tmp  = $_FILES['foto']['tmp_name'];
    $name = $_FILES['foto']['name'] ?? 'foto';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) {
        header('Location: ' . url('/order/orden_evidencias.php?id=' . $ordenId . '&msg=' . urlencode('Formato no permitido.')));
        exit;
    }

    // ✅ STORAGE por instancia
    $storageBase = $INSTANCE['STORAGE_PATH'] ?? '';
    if ($storageBase === '') {
        header('Location: ' . url('/order/orden_evidencias.php?id=' . $ordenId . '&msg=' . urlencode('Storage no configurado.')));
        exit;
    }

    // ✅ Carpeta relativa a storage (esto va a BD)
    $destDirRel = 'evidencias/orden_' . $ordenId;
    $destDirAbs = rtrim($storageBase, '/\\') . '/' . $destDirRel;

    if (!is_dir($destDirAbs)) {
        @mkdir($destDirAbs, 0755, true);
    }

    $fileBase = 'ev_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

    // ✅ destino final JPG
    $destRel = $destDirRel . '/' . $fileBase . '.jpg';   // <- BD
    $destAbs = $destDirAbs . '/' . $fileBase . '.jpg';   // <- disco

    // ✅ Optimizar => guardar JPG sin padding negro
    $ok = false;

    if (function_exists('imagecreatetruecolor')) {
        $type = null;
        $im = image_from_any($tmp, $type);

        if ($im) {
            $im = exif_orient_path($tmp, $im);

            $final = resize_keep_ratio($im, 1600, 1600);

            $ok = imagejpeg($final, $destAbs, 82);

            imagedestroy($final);
            imagedestroy($im);
        }
    }

    // ✅ Fallback: mover tal cual si no hay GD
    if (!$ok) {
        $destRel = $destDirRel . '/' . $fileBase . '.' . $ext;
        $destAbs = $destDirAbs . '/' . $fileBase . '.' . $ext;

        if (move_uploaded_file($tmp, $destAbs)) {
            $ok = true;
        }
    }

    if (!$ok) {
        header('Location: ' . url('/order/orden_evidencias.php?id=' . $ordenId . '&msg=' . urlencode('No se pudo guardar la evidencia.')));
        exit;
    }

    // Insert BD (archivo relativo a storage)
    $stmt = $pdo->prepare("
        INSERT INTO ordenes_evidencias (orden_id, tipo, comentario, visible_cliente, archivo, fecha)
        VALUES (:orden_id, :tipo, :comentario, :visible, :archivo, NOW())
    ");
    $stmt->execute([
        ':orden_id'   => $ordenId,
        ':tipo'       => $tipo,
        ':comentario' => $comentario,
        ':visible'    => $visible,
        ':archivo'    => $destRel,
    ]);

    header('Location: ' . url('/order/orden_evidencias.php?id=' . $ordenId . '&msg=' . urlencode('Evidencia guardada.')));
    exit;
}

// Datos de orden (cabecera)
$stmtO = $pdo->prepare("SELECT id, cliente_nombre, equipo_marca, equipo_modelo FROM ordenes WHERE id = ? LIMIT 1");
$stmtO->execute([$ordenId]);
$orden = $stmtO->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Evidencias listadas
$stmt = $pdo->prepare("
    SELECT id, tipo, comentario, visible_cliente, fecha
    FROM ordenes_evidencias
    WHERE orden_id = ?
    ORDER BY id DESC
");
$stmt->execute([$ordenId]);
$evidencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.evi-thumb {
    width: 100%;
    height: 190px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid #eee;
    background: #f8f9fa;
}
.evi-card {
    border: 1px solid #eee;
    border-radius: 12px;
    padding: 12px;
    background: #fff;
}
</style>

<div class="main-panel">
  <div class="content">
    <div class="container-fluid">

      <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
          <h4 class="mb-0">Evidencias — Orden #<?php echo (int)$orden['id']; ?></h4>
          <small class="text-muted">
            <?php echo htmlspecialchars($orden['cliente_nombre']); ?> — <?php echo htmlspecialchars(trim(($orden['equipo_marca'] ?? '').' '.($orden['equipo_modelo'] ?? ''))); ?>
          </small>
        </div>

        <a class="btn btn-outline-primary btn-sm" href="<?php echo url('/order/orden_detalle.php?id='.(int)$ordenId); ?>">
          ← Volver a la orden
        </a>
      </div>

      <?php if ($msg !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
      <?php endif; ?>

      <div class="card mb-3">
        <div class="card-body">
          <h5 class="mb-3">Agregar evidencia</h5>

          <form method="post" enctype="multipart/form-data">
            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Tipo</label>
                <select name="tipo" class="form-control">
                  <option value="General">General</option>
                  <option value="Humedad">Humedad</option>
                  <option value="Sulfatación">Sulfatación</option>
                  <option value="Golpe">Golpe</option>
                  <option value="Rayas">Rayas</option>
                  <option value="Intervención previa">Intervención previa</option>
                </select>
              </div>

              <div class="form-group col-md-6">
                <label>Comentario (opcional)</label>
                <input type="text" name="comentario" class="form-control" placeholder="Ej: Puerto sulfatado / humedad visible...">
              </div>

              <div class="form-group col-md-3">
                <label>Foto (JPG/PNG/WEBP)</label>
                <input type="file" name="foto" class="form-control-file" accept=".jpg,.jpeg,.png,.webp" required>
              </div>
            </div>

            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" name="visible_cliente" id="visible_cliente" checked>
              <label class="form-check-label" for="visible_cliente">
                Visible para el cliente (para futuro link público)
              </label>
            </div>

            <button class="btn btn-dark">Guardar evidencia</button>
            <small class="text-muted ml-2">Se optimiza y guarda sin barras negras 😄</small>
          </form>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="mb-3">Evidencias guardadas (<?php echo count($evidencias); ?>)</h5>

          <?php if (empty($evidencias)): ?>
            <p class="text-muted mb-0">Aún no hay evidencias para esta orden.</p>
          <?php else: ?>
            <div class="row">
              <?php foreach ($evidencias as $ev): ?>
                <div class="col-12 col-md-4 col-lg-3 mb-3">
                  <div class="evi-card">
                    <a href="<?php echo url('/order/evidencia_view.php?id='.(int)$ev['id']); ?>">
                      <img class="evi-thumb"
                           src="<?php echo url('/order/evidencia_ver.php?id='.(int)$ev['id'].'&thumb=1'); ?>"
                           alt="Evidencia">
                    </a>

                    <div class="mt-2 small">
                      <div>
                        <strong><?php echo htmlspecialchars($ev['tipo']); ?></strong>
                        <?php if ((int)$ev['visible_cliente'] === 1): ?>
                          <span class="text-success">· Visible cliente</span>
                        <?php else: ?>
                          <span class="text-warning">· Solo interno</span>
                        <?php endif; ?>
                      </div>

                      <?php if (!empty($ev['comentario'])): ?>
                        <div class="text-muted"><?php echo htmlspecialchars($ev['comentario']); ?></div>
                      <?php endif; ?>

                      <div class="text-muted"><?php echo date('Y-m-d H:i', strtotime($ev['fecha'])); ?></div>

                      <div class="mt-2 d-flex gap-2">
                        <form method="post"
                              action="<?php echo url('/order/evidencia_eliminar.php'); ?>"
                              onsubmit="return confirm('¿Eliminar esta evidencia? Esta acción no se puede deshacer.');">
                          <input type="hidden" name="id" value="<?php echo (int)$ev['id']; ?>">
                          <input type="hidden" name="orden_id" value="<?php echo (int)$ordenId; ?>">
                          <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                        </form>
                      </div>

                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
