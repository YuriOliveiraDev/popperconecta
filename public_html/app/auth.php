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

  if (empty($_SESSION['user']['id'])) {
    return null;
  }

  $id = (int)$_SESSION['user']['id'];

  try {
    // Recarrega do banco (fonte da verdade) para refletir mudanças instantaneamente
    $stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, permissions FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
      // usuário sumiu do banco
      $_SESSION['user'] = null;
      return null;
    }

    if ((int)($u['is_active'] ?? 0) !== 1) {
      // usuário desativado
      $_SESSION['user'] = null;
      return null;
    }

    // Atualiza sessão com dados atuais
    $_SESSION['user'] = [
      'id' => (int)$u['id'],
      'name' => (string)$u['name'],
      'email' => (string)$u['email'],
      'role' => (string)$u['role'],
      'setor' => (string)($u['setor'] ?? ''),
      'hierarquia' => (string)($u['hierarquia'] ?? ''),
      'is_active' => (int)($u['is_active'] ?? 1),
      'permissions' => $u['permissions'] ?? null,
    ];

    return $_SESSION['user'];
  } catch (Throwable $e) {
    // Se o banco falhar, devolve o que tiver na sessão (fallback)
    return $_SESSION['user'] ?? null;
  }
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

  // ✅ Atualizado: inclui permissions no SELECT
  $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active, permissions FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u) return false;
  if ((int)$u['is_active'] !== 1) return false;
  if (!password_verify($pass, $u['password_hash'])) return false;

  session_regenerate_id(true);

  // ✅ Atualizado: inclui permissions na sessão
  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'name' => $u['name'],
    'email' => $u['email'],
    'role' => $u['role'],
    'permissions' => $u['permissions'] ?? null, // pode ser string JSON ou null
  ];

  db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$u['id']]);
  return true;
}

function logout(): void {
  start_session();
  $_SESSION = [];
  session_destroy();
}