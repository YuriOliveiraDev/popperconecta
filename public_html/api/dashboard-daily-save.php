<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';

require_admin();

$dash = trim((string)($_POST['dash'] ?? 'executivo'));
$refDate = trim((string)($_POST['ref_date'] ?? ''));
$faturado = (float)($_POST['faturado_dia'] ?? 0);
$agendadoHoje = (float)($_POST['agendado_hoje'] ?? 0);

if ($dash === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $refDate)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'Dados inválidos.']);
  exit;
}

$u = current_user();
$uid = (int)($u['id'] ?? 0);

$stmt = db()->prepare('
  INSERT INTO dashboard_daily (dash_slug, ref_date, faturado_dia, agendado_hoje, updated_by)
  VALUES (?, ?, ?, ?, ?)
  ON DUPLICATE KEY UPDATE
    faturado_dia=VALUES(faturado_dia),
    agendado_hoje=VALUES(agendado_hoje),
    updated_by=VALUES(updated_by),
    updated_at=CURRENT_TIMESTAMP
');
$stmt->execute([$dash, $refDate, $faturado, $agendadoHoje, $uid]);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);