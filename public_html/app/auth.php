<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    session_name(SESSION_NAME);

    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'secure' => $isHttps,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);

    session_start();
  }
}

function current_user(): ?array {
  start_session();
  return $_SESSION['user'] ?? null;
}

function require_login(): void {
  start_session();
  if (empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
  }
}

function require_admin(): void {
  require_login();
  if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }
}

function login(string $email, string $pass): bool {
  start_session();

  $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u) return false;
  if ((int)$u['is_active'] !== 1) return false;
  if (!password_verify($pass, $u['password_hash'])) return false;

  session_regenerate_id(true);

  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'name' => $u['name'],
    'email' => $u['email'],
    'role' => $u['role'],
  ];

  db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$u['id']]);
  return true;
}

function logout(): void {
  start_session();
  $_SESSION = [];
  session_destroy();
}