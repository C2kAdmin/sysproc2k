<?php
// _cores/systec/v1.1/notices/mark_read.php
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['usuario_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'No autenticado']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
  exit;
}

$noticeId = (int)($_POST['notice_id'] ?? 0);
if ($noticeId <= 0) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'notice_id inválido']);
  exit;
}

$userId = (int)($_SESSION['usuario_id'] ?? 0);

// device_id (cookie generado en footer)
$deviceId = trim((string)($_COOKIE['systec_device_id'] ?? ''));
if ($deviceId === '' || strlen($deviceId) < 12) {
  $deviceId = trim((string)($_POST['device_id'] ?? ''));
}
if ($deviceId === '' || strlen($deviceId) < 12) {
  $deviceId = 'no_device';
}
try {
  // Verificar que el aviso exista y esté activo
  $stmt = $pdo->prepare("SELECT id FROM notices WHERE id = :id AND activo = 1 LIMIT 1");
  $stmt->execute([':id' => $noticeId]);
  $exists = (int)$stmt->fetchColumn();

  if ($exists <= 0) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Aviso no encontrado o inactivo']);
    exit;
  }  // Insert idempotente (funciona aunque NO exista UNIQUE)
  $stmt = $pdo->prepare("
    INSERT INTO user_notice_reads (user_id, notice_id, device_id, read_at)
    VALUES (:u, :n, :d, NOW())
    ON DUPLICATE KEY UPDATE read_at = VALUES(read_at)
  ");
  $stmt->execute([
    ':u' => $userId,
    ':n' => $noticeId,
    ':d' => $deviceId
  ]);

  echo json_encode(['ok' => true]);
  exit;

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Error al marcar como leído']);
  exit;
}

