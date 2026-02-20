<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/notifications.php';

require_login();
$u = current_user();
$userId = (int)($u['id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Método inválido']);
  exit;
}

$id = (int)($_POST['id'] ?? 0);
$all = (int)($_POST['all'] ?? 0);

try {
  if ($all === 1) {
    notifications_mark_all_read($userId);
    echo json_encode(['ok' => true]);
    exit;
  }
  if ($id <= 0) throw new Exception('ID inválido.');
  notifications_mark_read($userId, $id);
  echo json_encode(['ok' => true]);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}