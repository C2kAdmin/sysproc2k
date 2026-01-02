<?php
// order/evidencia_ver.php
require_once __DIR__ . '/../config/config.php';

// Seguridad: interno (por ahora)
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Sin sesión.');
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('ID inválido');
}

$isThumb = isset($_GET['thumb']) && $_GET['thumb'] == '1';

// Buscar evidencia
$stmt = $pdo->prepare("
    SELECT id, archivo, visible_cliente, orden_id
    FROM ordenes_evidencias
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$ev = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ev) {
    http_response_code(404);
    exit('No encontrada');
}

$rel = (string)$ev['archivo'];
$rel = str_replace('\\', '/', $rel);
$rel = ltrim(str_replace('../', '', $rel), '/');

$storageBase = $INSTANCE['STORAGE_PATH'] ?? '';
if ($storageBase === '') {
    http_response_code(500);
    exit('Storage no configurado');
}

// ✅ 1) Intento normal: storage de la instancia
$storageBaseNorm = rtrim(str_replace('\\', '/', $storageBase), '/');
$abs1 = $storageBaseNorm . '/' . $rel;

// ✅ 2) Fallback compat: evidencias antiguas dentro del CORE (APP_BASE)
$appBaseNorm = rtrim(str_replace('\\', '/', APP_BASE), '/');
$abs2 = $appBaseNorm . '/' . $rel;

$abs = '';
if (is_file($abs1)) {
    $abs = $abs1;
} elseif (is_file($abs2)) {
    $abs = $abs2;
} else {
    http_response_code(404);
    exit('Archivo no encontrado');
}

/**
 * Helpers imagen
 */
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

// Thumb => generamos JPEG liviano
if ($isThumb && function_exists('imagecreatetruecolor')) {
    $type = null;
    $im = image_from_any($abs, $type);

    if ($im) {
        $im = exif_orient_path($abs, $im);
        $thumb = resize_keep_ratio($im, 520, 520);

        header('Content-Type: image/jpeg');
        header('Cache-Control: private, max-age=3600');
        header('X-Content-Type-Options: nosniff');

        imagejpeg($thumb, null, 82);

        imagedestroy($thumb);
        imagedestroy($im);
        exit;
    }
}

// Archivo real
$mime = 'image/jpeg';
if (function_exists('mime_content_type')) {
    $m = @mime_content_type($abs);
    if ($m) $mime = $m;
}

header('Content-Type: ' . $mime);
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($abs);
exit;
