<?php
// order/firma_registrar.php

require_once __DIR__ . '/../config/auth.php';

// ✅ ADMIN + RECEPCION (+ SUPER_ADMIN pasa por auth.php)
require_role(['SUPER_ADMIN','ADMIN', 'RECEPCION']);
/* ----------------------------------
   Helper Title Case (robusto)
---------------------------------- */
if (!function_exists('title_case_smart')) {
    function title_case_smart($str) {
        $str = trim((string)$str);
        if ($str === '') return '';

        if (!mb_check_encoding($str, 'UTF-8')) {
            $str = mb_convert_encoding($str, 'UTF-8', 'ISO-8859-1');
        }

        $str = preg_replace('/\s+/', ' ', $str);
        $str = mb_strtolower($str, 'UTF-8');
        return mb_convert_case($str, MB_CASE_TITLE, 'UTF-8');
    }
}

/* ----------------------------------
   Helper SQL: normaliza estado_actual quitando tildes
---------------------------------- */
$SQL_ESTADO_NORM = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(UPPER(TRIM(estado_actual)),
                    'Á','A'),'É','E'),'Í','I'),'Ó','O'),'Ú','U')";

/* ----------------------------------
   Usuario
---------------------------------- */
$usuarioNombre = $_SESSION['usuario_nombre'] ?? 'Sistema';

/* ----------------------------------
   Return
---------------------------------- */
$return = trim($_GET['return'] ?? 'order/firmas_pendientes.php');

if (
    $return === '' ||
    strpos($return, '..') !== false ||
    strpos($return, '://') !== false ||
    strpos($return, 'order/') !== 0
) {
    $return = 'order/firmas_pendientes.php';
}
/* ----------------------------------
   Anti doble submit
---------------------------------- */
if (!isset($_SESSION['anti_repost_firma'])) {
    $_SESSION['anti_repost_firma'] = bin2hex(random_bytes(16));
}
$antiToken = $_SESSION['anti_repost_firma'];

/* ===================================================
   1) POST → Guardar firma
=================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $formToken = $_POST['_anti_token'] ?? '';
    if ($formToken === '' || !hash_equals($antiToken, $formToken)) {
        header('Location: ' . url('/' . ltrim($return, '/')));
        exit;
    }

    // regeneramos token
    $_SESSION['anti_repost_firma'] = bin2hex(random_bytes(16));
    $antiToken = $_SESSION['anti_repost_firma'];

    $idPost = (int)($_POST['id'] ?? 0);
    if ($idPost <= 0) {
        header('Location: ' . url('/' . ltrim($return, '/')));
        exit;
    }

    // ✅ Validar que la orden aún:
    // - requiere firma
    // - NO tiene firma
    // - NO está ENTREGADO
    $stmt = $pdo->prepare("
        SELECT id, requiere_firma, firma_ruta
        FROM ordenes
        WHERE id = :id
          AND requiere_firma = 1
          AND (firma_ruta IS NULL OR firma_ruta = '')
          AND {$SQL_ESTADO_NORM} <> 'ENTREGADO'
        LIMIT 1
    ");
    $stmt->execute([':id' => $idPost]);
    $okOrden = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$okOrden) {
        header('Location: ' . url('/order/orden_detalle.php?id=' . $idPost));
        exit;
    }

    $imgBase64 = $_POST['firma_data'] ?? '';
    if ($imgBase64 === '' || strpos($imgBase64, 'data:image/png;base64,') !== 0) {
        header('Location: ' . url('/order/firma_registrar.php?id=' . $idPost . '&return=' . urlencode($return)));
        exit;
    }

    // Limpiamos base64
    $imgBase64 = str_replace('data:image/png;base64,', '', $imgBase64);
    $imgBase64 = str_replace(' ', '+', $imgBase64);

    $datosImagen = base64_decode($imgBase64);
    if ($datosImagen === false || strlen($datosImagen) < 1024 || strlen($datosImagen) > (2 * 1024 * 1024)) {
        header('Location: ' . url('/order/firma_registrar.php?id=' . $idPost . '&return=' . urlencode($return)));
        exit;
    }

    // ✅ carpeta real dentro de /assets/firmas (raíz del sistema)
    $carpetaFirmas = dirname(__DIR__) . '/assets/firmas';
    if (!is_dir($carpetaFirmas)) {
        @mkdir($carpetaFirmas, 0775, true);
    }

    $nombreArchivo = 'firma_' . $idPost . '_' . time() . '.png';
    $rutaArchivo   = $carpetaFirmas . '/' . $nombreArchivo;

    if (file_put_contents($rutaArchivo, $datosImagen) === false) {
        header('Location: ' . url('/order/firma_registrar.php?id=' . $idPost . '&return=' . urlencode($return)));
        exit;
    }

    // ✅ ruta pública relativa desde la raíz web del sistema
    $rutaRelativa = 'assets/firmas/' . $nombreArchivo;

    // Guardamos firma (solo si aún calza la condición)
    $stmt = $pdo->prepare("
        UPDATE ordenes
        SET firma_ruta = :firma
        WHERE id = :id
          AND requiere_firma = 1
          AND (firma_ruta IS NULL OR firma_ruta = '')
          AND {$SQL_ESTADO_NORM} <> 'ENTREGADO'
    ");
    $stmt->execute([
        ':firma' => $rutaRelativa,
        ':id'    => $idPost,
    ]);

    // Historial (solo si realmente se actualizó)
    if ((int)$stmt->rowCount() > 0) {
        $stmtHist = $pdo->prepare("
            INSERT INTO ordenes_estados (orden_id, estado, comentario, usuario)
            VALUES (:orden_id, :estado, :comentario, :usuario)
        ");
        $stmtHist->execute([
            ':orden_id'   => $idPost,
            ':estado'     => 'FIRMA REGISTRADA',
            ':comentario' => 'Firma del cliente registrada en sistema.',
            ':usuario'    => $usuarioNombre,
        ]);
    }

    header('Location: ' . url('/order/orden_detalle.php?id=' . $idPost));
    exit;
}

/* ===================================================
   2) GET → Mostrar pantalla
=================================================== */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: ' . url('/' . ltrim($return, '/')));
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, numero_orden, cliente_nombre, cliente_telefono,
           equipo_marca, equipo_modelo, fecha_ingreso,
           requiere_firma, firma_ruta
    FROM ordenes
    WHERE id = :id
      AND requiere_firma = 1
      AND (firma_ruta IS NULL OR firma_ruta = '')
      AND {$SQL_ESTADO_NORM} <> 'ENTREGADO'
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    header('Location: ' . url('/order/orden_detalle.php?id=' . $id));
    exit;
}

// ✅ Formateos (bonitos)
$numeroOrdenFmt = str_pad((string)($orden['numero_orden'] ?? $orden['id']), 4, '0', STR_PAD_LEFT);
$clienteFmt     = title_case_smart($orden['cliente_nombre'] ?? '');
$marcaFmt       = title_case_smart($orden['equipo_marca'] ?? '');
$modeloFmt      = title_case_smart($orden['equipo_modelo'] ?? '');
$equipoFmt      = trim($marcaFmt . ' ' . $modeloFmt);
if ($equipoFmt === '') $equipoFmt = 'Sin datos';

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>

<style>
.signature-container {
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
    padding: 10px;
}
#signature-pad {
    border: 1px solid #aaa;
    width: 100%;
    max-width: 560px;
    height: 260px;
    touch-action: none;
}
</style>

<div class="main-panel">
    <div class="content">
        <div class="container-fluid">

            <h4 class="page-title">Firma del cliente</h4>

            <div class="row">
                <div class="col-md-7">

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                Orden #<?php echo htmlspecialchars($numeroOrdenFmt); ?>
                            </h5>
                            <p class="card-category mb-0">
                                Cliente: <?php echo htmlspecialchars($clienteFmt !== '' ? $clienteFmt : (string)($orden['cliente_nombre'] ?? '')); ?><br>
                                Teléfono: <?php echo htmlspecialchars((string)($orden['cliente_telefono'] ?? '')); ?><br>
                                Equipo: <?php echo htmlspecialchars($equipoFmt); ?><br>
                                Ingreso: <?php echo htmlspecialchars((string)($orden['fecha_ingreso'] ?? '')); ?>
                            </p>
                        </div>

                        <div class="card-body">
                            <p class="mb-2">
                                Pida al cliente que firme en el recuadro. Esta firma quedará asociada a la orden.
                            </p>

                            <div class="signature-container mb-3">
                                <canvas id="signature-pad"></canvas>
                            </div>

                            <form method="post"
                                  action="<?php echo htmlspecialchars(url('/order/firma_registrar.php?id=' . (int)$orden['id'] . '&return=' . urlencode($return))); ?>"
                                  onsubmit="return enviarFirma();">
                                <input type="hidden" name="_anti_token" value="<?php echo htmlspecialchars($antiToken); ?>">
                                <input type="hidden" name="id" value="<?php echo (int)$orden['id']; ?>">
                                <input type="hidden" name="firma_data" id="firma_data">

                                <button type="button" class="btn btn-secondary" onclick="limpiarFirma();">
                                    Limpiar
                                </button>
                                <button type="submit" class="btn btn-success">
                                    Guardar firma
                                </button>
                                <a href="<?php echo htmlspecialchars(url('/' . ltrim($return, '/'))); ?>" class="btn btn-outline-secondary">
                                    Volver
                                </a>
                            </form>

                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>
</div>

<script>
(function() {
    const canvas = document.getElementById('signature-pad');
    const ctx = canvas.getContext('2d');

    function resizeCanvas() {
        const rect = canvas.getBoundingClientRect();
        canvas.width = rect.width;
        canvas.height = 260;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    let dibujando = false;
    let ultimoX = 0;
    let ultimoY = 0;

    function empezarDibujo(x, y) { dibujando = true; ultimoX = x; ultimoY = y; }
    function terminarDibujo() { dibujando = false; }

    function dibujar(x, y) {
        if (!dibujando) return;
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(ultimoX, ultimoY);
        ctx.lineTo(x, y);
        ctx.stroke();
        ultimoX = x;
        ultimoY = y;
    }

    // Mouse
    canvas.addEventListener('mousedown', function(e) {
        const rect = canvas.getBoundingClientRect();
        empezarDibujo(e.clientX - rect.left, e.clientY - rect.top);
    });
    canvas.addEventListener('mousemove', function(e) {
        const rect = canvas.getBoundingClientRect();
        dibujar(e.clientX - rect.left, e.clientY - rect.top);
    });
    canvas.addEventListener('mouseup', terminarDibujo);
    canvas.addEventListener('mouseleave', terminarDibujo);

    // Touch
    canvas.addEventListener('touchstart', function(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const t = e.touches[0];
        empezarDibujo(t.clientX - rect.left, t.clientY - rect.top);
    }, { passive: false });

    canvas.addEventListener('touchmove', function(e) {
        e.preventDefault();
        const rect = canvas.getBoundingClientRect();
        const t = e.touches[0];
        dibujar(t.clientX - rect.left, t.clientY - rect.top);
    }, { passive: false });

    canvas.addEventListener('touchend', function(e) {
        e.preventDefault();
        terminarDibujo();
    });

    window._firmaCanvas = canvas;
})();

function limpiarFirma() {
    const canvas = window._firmaCanvas;
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
}

function enviarFirma() {
    const canvas = window._firmaCanvas;
    const out = document.getElementById('firma_data');

    const blank = document.createElement('canvas');
    blank.width = canvas.width;
    blank.height = canvas.height;

    if (canvas.toDataURL() === blank.toDataURL()) {
        alert('Debe registrar una firma antes de guardar.');
        return false;
    }

    out.value = canvas.toDataURL('image/png');
    return true;
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
