<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_login();

$mode = (string)($_GET['mode'] ?? 'all');
$sector = trim((string)($_GET['sector'] ?? ''));

$where = [];
$params = [];

if ($sector !== '') {
  $where[] = "u.setor = ?";
  $params[] = $sector;
}

if ($mode === 'month') {
  $where[] = "l.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT COALESCE(SUM(l.amount), 0) AS total
  FROM users u
  LEFT JOIN popper_coin_ledger l ON l.user_id = u.id
  $whereSql
";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'total' => (int)($row['total'] ?? 0)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);