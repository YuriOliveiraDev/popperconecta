<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config.php';

function db(): PDO
{
  static $pdo = null;

  if ($pdo instanceof PDO) {
    return $pdo;
  }

  if (APP_ENV === 'dev') {
    $host   = DB_HOST_DEV;
    $port   = DB_PORT_DEV;
    $dbname = DB_NAME_DEV;
    $user   = DB_USER_DEV;
    $pass   = DB_PASS_DEV;
  } else {
    $host   = DB_HOST_PROD;
    $port   = DB_PORT_PROD;
    $dbname = DB_NAME_PROD;
    $user   = DB_USER_PROD;
    $pass   = DB_PASS_PROD;
  }

  $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

  $pdo = new PDO(
    $dsn,
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );

  return $pdo;
}