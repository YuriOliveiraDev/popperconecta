<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_login();

$stmt = db()->query("SELECT DISTINCT setor FROM users WHERE setor IS NOT NULL AND setor <> '' ORDER BY setor ASC");
$sectors = $stmt->fetchAll(PDO::FETCH_COLUMN);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => true, 'sectors' => $sectors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);