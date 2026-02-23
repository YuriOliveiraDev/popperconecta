<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

require_admin();

header('Content-Type: text/plain; charset=utf-8');

$db = db();

echo "DATABASE():\n";
echo $db->query("SELECT DATABASE()")->fetchColumn() . "\n\n";

echo "TABELAS:\n";
$tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $t) {
  echo "============================================================\n";
  echo "TABLE: {$t}\n";

  // contagem
  try {
    $cnt = $db->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
    echo "ROWS: {$cnt}\n";
  } catch (Throwable $e) {
    echo "ROWS: (erro ao contar)\n";
  }

  echo "\nCOLUMNS:\n";
  $cols = $db->query("SHOW COLUMNS FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($cols as $c) {
    $field = (string)($c['Field'] ?? '');
    $type = (string)($c['Type'] ?? '');
    $null = (string)($c['Null'] ?? '');
    $key = (string)($c['Key'] ?? '');
    $def = $c['Default'];
    $extra = (string)($c['Extra'] ?? '');
    $defStr = ($def === null) ? 'NULL' : (string)$def;

    echo "- {$field} | {$type} | Null: {$null} | Key: {$key} | Default: {$defStr} | Extra: {$extra}\n";
  }

  echo "\nINDEXES:\n";
  $idx = $db->query("SHOW INDEX FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($idx as $i) {
    $keyName = (string)($i['Key_name'] ?? '');
    $colName = (string)($i['Column_name'] ?? '');
    $unique = isset($i['Non_unique']) ? (int)!((int)$i['Non_unique']) : 0;
    $seq = (string)($i['Seq_in_index'] ?? '');
    echo "- {$keyName} | Column: {$colName} | Unique: {$unique} | Seq: {$seq}\n";
  }

  echo "\nCREATE TABLE:\n";
  $create = $db->query("SHOW CREATE TABLE `{$t}`")->fetch(PDO::FETCH_ASSOC);
  if ($create) {
    $createSql = (string)($create['Create Table'] ?? '');
    echo $createSql . "\n";
  }

  echo "\n";
}

echo "============================================================\n";
echo "FIM\n";