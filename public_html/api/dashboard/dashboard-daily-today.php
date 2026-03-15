<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_login();

$dash = (string)($_GET['dash'] ?? 'executivo');
$today = (new DateTime('today'))->format('Y-m-d');

$stmt = db()->prepare('SELECT dash_slug, ref_date, faturado_dia, agendado_hoje, updated_at FROM dashboard_daily WHERE dash_slug=? AND ref_date=? LIMIT 1');
$stmt->execute([$dash, $today]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
  $row = [
    'dash_slug' => $dash,
    'ref_date' => $today,
    'faturado_dia' => 0,
    'agendado_hoje' => 0,
    'updated_at' => null
  ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'today' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);