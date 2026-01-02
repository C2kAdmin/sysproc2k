<?php
// order/evidencia_eliminar.php
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . url('/login.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido');
}

$evId        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$ordenIdPost = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;

if ($evId <= 0) {
    header('Location: ' . url('/dashboard.php'));
    exit;
}

// Buscar evidencia
$stmt = $pdo->prepare("
    SELECT id, orden_id, archivo
    FROM ordenes_evidencias
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$evId]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    $back = ($ordenIdPost > 0)
        ? url('/order/orden_evidencias.php?id=' . $ordenIdPost)
        : url('/dashboard.php');

    header('Location: ' . $back);
    exit;
}

$ordenId = (int)$ev['orden_id'];

// Normalizar ruta relativa almacenada en BD
$rel = (string)($ev['archivo'] ?? '');
$rel = str_replace('\\', '/', $rel);

// Seguridad extra: limpiar intentos de salir del storage
$rel = preg_replace('#\.\./#', '', $rel);
$rel = ltrim($rel, '/');

// Bases
$storageBase = $INSTANCE['STORAGE_PATH'] ?? '';
$storageBaseNorm = rtrim(str_replace('\\', '/', (string)$storageBase), '/');
$appBaseNorm     = rtrim(str_replace('\\', '/', (string)APP_BASE), '/');

// Rutas posibles (storage actual + compat antigua en CORE)
$abs1 = ($storageBaseNorm !== '') ? ($storageBaseNorm . '/' . $rel) : '';
$abs2 = ($appBaseNorm !== '') ? ($appBaseNorm . '/' . $rel) : '';

// Si abs1 y abs2 apuntan al mismo archivo, no borrar dos veces
$toDelete = [];
if ($abs1 !== '' && is_file($abs1)) $toDelete[$abs1] = true;
if ($abs2 !== '' && is_file($abs2)) $toDelete[$abs2] = true;

try {
    $pdo->beginTransaction();

    // 1) Borrar archivo(s) físico(s)
    foreach (array_keys($toDelete) as $f) {
        @unlink($f);
    }

    // 2) Borrar registro BD
    $del = $pdo->prepare("DELETE FROM ordenes_evidencias WHERE id = ? LIMIT 1");
    $del->execute([$evId]);

    $pdo->commit();

    header('Location: ' . url('/order/orden_evidencias.php?id=' . $ordenId . '&msg=' . urlencode('Evidencia eliminada.')));
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();

    $fallbackId = ($ordenIdPost > 0) ? $ordenIdPost : $ordenId;

    header('Location: ' . url('/order/orden_evidencias.php?id=' . $fallbackId . '&msg=' . urlencode('Error eliminando evidencia.')));
    exit;
}
