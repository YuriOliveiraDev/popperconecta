<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_login();

$dash = (string)($_GET['dash'] ?? 'executivo');
$month = (string)($_GET['month'] ?? date('Y-m')); // "YYYY-MM"

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
  http_response_code(400);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'Parâmetro month inválido. Use YYYY-MM.']);
  exit;
}

$start = $month . '-01';
$end = (new DateTime($start))->modify('first day of next month')->format('Y-m-d');

$stmt = db()->prepare('
  SELECT ref_date, faturado_dia, agendado_hoje
  FROM dashboard_daily
  WHERE dash_slug=? AND ref_date >= ? AND ref_date < ?
  ORDER BY ref_date ASC
');
$stmt->execute([$dash, $start, $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'month' => $month, 'series' => $rows], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);