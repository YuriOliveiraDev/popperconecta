<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

require_login();

$mode = (string)($_GET['mode'] ?? 'all'); // all | month
$sector = trim((string)($_GET['sector'] ?? ''));
$q = trim((string)($_GET['q'] ?? ''));

if (!in_array($mode, ['all', 'month'], true)) $mode = 'all';

$where = [];
$params = [];

// usuários ativos
$where[] = "u.is_active = 1";

if ($sector !== '') {
  $where[] = "u.setor = ?";
  $params[] = $sector;
}

if ($q !== '') {
  $where[] = "u.name LIKE ?";
  $params[] = '%' . $q . '%';
}

if ($mode === 'month') {
  $where[] = "l.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01 00:00:00')";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "
  SELECT
    u.id,
    u.name,
    u.setor,
    u.profile_photo_path AS avatar,
    COALESCE(SUM(l.amount),0) AS coins
  FROM users u
  LEFT JOIN popper_coin_ledger l ON l.user_id = u.id
  $whereSql
  GROUP BY u.id, u.name, u.setor, u.profile_photo_path
  ORDER BY coins DESC, u.name ASC
  LIMIT 200
";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ranked = [];
$pos = 0;
$lastCoins = null;
$shownPos = 0;

foreach ($rows as $r) {
  $pos++;
  $coins = (int)$r['coins'];

  if ($lastCoins === null || $coins !== $lastCoins) {
    $shownPos = $pos;
    $lastCoins = $coins;
  }

  $ranked[] = [
    'position' => $shownPos,
    'userId' => (int)$r['id'],
    'name' => (string)$r['name'],
    'sector' => (string)($r['setor'] ?? ''),
    'avatar' => (string)($r['avatar'] ?? ''),
    'coins' => $coins,
  ];
}

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
  'ok' => true,
  'mode' => $mode,
  'sector' => $sector,
  'q' => $q,
  'items' => $ranked
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);