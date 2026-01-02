<?php
// order/evidencia_publica.php (PÚBLICO POR TOKEN)
// Sirve la imagen real desde BD (solo si es visible_cliente=1 y token válido)
// ✅ Compatible con STORAGE_PATH (nuevo) + fallback evidencias antiguas (core)

require_once __DIR__ . '/../config/config_publico.php';

$id    = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

if ($id <= 0 || $token === '') {
    http_response_code(403);
    exit('Sin permisos.');
}

try {
    // Traemos archivo y validamos:
    // - evidencia pertenece a orden del token
    // - visible_cliente = 1
    $stmt = $pdo->prepare("
        SELECT e.archivo
        FROM ordenes_evidencias e
        INNER JOIN ordenes o ON o.id = e.orden_id
        WHERE e.id = :id
          AND e.visible_cliente = 1
          AND o.token_publico = :token
        LIMIT 1
    ");
    $stmt->execute([':id' => $id, ':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['archivo'])) {
        http_response_code(404);
        exit('No encontrado.');
    }

    // Ruta relativa guardada en BD (ej: evidencias/orden_4/ev_2025...jpg)
    $rel = (string)$row['archivo'];

    // Sanitizar traversal
    $rel = ltrim(str_replace(['..\\','../'], '', $rel), '/\\');

    // ✅ 1) Intento: STORAGE_PATH de la instancia (nuevo sistema)
    $abs = '';
    $storageBase = $INSTANCE['STORAGE_PATH'] ?? '';

    if ($storageBase !== '') {
        $absTry = rtrim($storageBase, '/\\') . '/' . $rel;
        if (is_file($absTry)) $abs = $absTry;
    }

    // ✅ 2) Fallback: evidencias antiguas dentro del proyecto/core
    if ($abs === '') {
        // base del core: .../_cores/systec/v1.1
        $baseDir = realpath(__DIR__ . '/../'); // /config está en /config, subimos a /v1.1
        if (!$baseDir) {
            http_response_code(500);
            exit('Error base dir.');
        }

        $absTry = $baseDir . '/' . $rel; // SIN realpath para no fallar si no existe
        if (is_file($absTry)) {
            $abs = $absTry;
        } else {
            // Alternativa extra por si guardaste algo con rutas tipo "assets/..."
            // (mantener compatibilidad)
            if (defined('APP_BASE')) {
                $absOld = rtrim((string)APP_BASE, '/\\') . '/' . $rel;
                if (is_file($absOld)) $abs = $absOld;
            }
        }
    }

    if ($abs === '' || !is_file($abs)) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }

    // MIME
    $ext  = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
    $mime = 'image/jpeg';
    if ($ext === 'png')  $mime = 'image/png';
    if ($ext === 'webp') $mime = 'image/webp';
    if ($ext === 'gif')  $mime = 'image/gif';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($abs));
    header('Cache-Control: private, max-age=86400');
    header('X-Content-Type-Options: nosniff');

    readfile($abs);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    exit('Error.');
}
