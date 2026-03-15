<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config.php';

/* ===============================
   BLOQUEIO MOBILE (por viewport)
   - JS define cookie pc_view=mobile|ok
   - PHP bloqueia só quando for mobile
   =============================== */

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isTVBox = (bool)preg_match('/\bSTV-|Android TV|SMART-TV|SmartTV|HbbTV|AFT|BRAVIA|MiTV|TV Box|Tizen|Web0S\b/i', $userAgent);

// cookie setado pelo JS
$view  = $_COOKIE['pc_view'] ?? '';
$allow = $_COOKIE['pc_allow_mobile'] ?? '';

if (!$isTVBox && $allow !== '1' && $view === 'mobile') {
  header('Content-Type: text/html; charset=UTF-8');
  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <script src="/../../assets/js/view-detect.js?v=1"></script>
    <title>Popper Conecta</title>
    <style>
      body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#fff;color:#0f172a;height:100vh;display:flex;align-items:center;justify-content:center;text-align:center}
      .mobile-block{max-width:460px;padding:32px}
      .logo{font-weight:900;font-size:18px;color:#5c2c84;margin-bottom:24px}
      h1{font-size:22px;margin:0 0 12px 0;font-weight:900}
      p{font-size:15px;opacity:.75;line-height:1.5;margin:0}
      .btn{display:inline-flex;align-items:center;justify-content:center;margin-top:18px;height:42px;padding:0 16px;border-radius:10px;border:1px solid rgba(92,44,132,.35);background:rgba(92,44,132,.10);color:#5c2c84;font-weight:900;text-decoration:none}
    </style>
  </head>
  <body>
    <div class="mobile-block">
      <div class="logo">Popper Conecta</div>
      <h1>🚧 Indisponível no mobile</h1>
      <p>
        Este portal ainda não está disponível para celulares.<br><br>
        Acesse usando um computador ou TV para visualizar dashboards e funcionalidades.
      </p>
      <a class="btn" href="/mobile-allow.php">Mesmo assim, abrir</a>
    </div>
  </body>
  </html>
  <?php
  exit;
}

/* ===============================
   HELPERS DE COOKIE / REMEMBER ME
   =============================== */

function auth_is_https(): bool
{
  return (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
  );
}

function auth_remember_cookie_name(): string
{
  return 'remember_me';
}

function auth_set_cookie(string $name, string $value, int $expires): void
{
  setcookie($name, $value, [
    'expires'  => $expires,
    'path'     => '/',
    'secure'   => auth_is_https(),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
}

function auth_forget_remember_me_cookie(): void
{
  auth_set_cookie(auth_remember_cookie_name(), '', time() - 3600);
}

function auth_delete_remember_me_token(int $tokenId): void
{
  db()->prepare('DELETE FROM user_remember_tokens WHERE id = ?')->execute([$tokenId]);
}

function auth_delete_remember_me_token_by_selector(string $selector): void
{
  db()->prepare('DELETE FROM user_remember_tokens WHERE selector = ?')->execute([$selector]);
}

function auth_delete_remember_me_token_by_cookie(?string $cookieValue): void
{
  if (!$cookieValue) {
    return;
  }

  $parts = explode(':', $cookieValue, 2);
  if (count($parts) !== 2) {
    return;
  }

  [$selector] = $parts;
  if ($selector !== '') {
    auth_delete_remember_me_token_by_selector($selector);
  }
}

function auth_create_session_user(array $u): array
{
  return [
    'id' => (int)$u['id'],
    'name' => (string)$u['name'],
    'email' => (string)$u['email'],
    'role' => (string)$u['role'],
    'setor' => (string)($u['setor'] ?? ''),
    'hierarquia' => (string)($u['hierarquia'] ?? ''),
    'is_active' => (int)($u['is_active'] ?? 1),
    'permissions' => $u['permissions'] ?? null,
    'phone' => (string)($u['phone'] ?? ''),
    'birth_date' => (string)($u['birth_date'] ?? ''),
    'gender' => (string)($u['gender'] ?? ''),
    'profile_photo_path' => (string)($u['profile_photo_path'] ?? ''),
  ];
}

function auth_create_remember_me_token(int $userId): void
{
  $selector  = bin2hex(random_bytes(12));
  $validator = bin2hex(random_bytes(32));
  $tokenHash = password_hash($validator, PASSWORD_DEFAULT);
  $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

  db()->prepare('
    INSERT INTO user_remember_tokens (
      user_id, selector, token_hash, expires_at, user_agent, ip_address
    ) VALUES (?, ?, ?, ?, ?, ?)
  ')->execute([
    $userId,
    $selector,
    $tokenHash,
    $expiresAt,
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    $_SERVER['REMOTE_ADDR'] ?? null,
  ]);

  auth_set_cookie(
    auth_remember_cookie_name(),
    $selector . ':' . $validator,
    time() + (60 * 60 * 24 * 30)
  );
}

function auth_rotate_remember_me_token(int $tokenId, int $userId): void
{
  $selector  = bin2hex(random_bytes(12));
  $validator = bin2hex(random_bytes(32));
  $tokenHash = password_hash($validator, PASSWORD_DEFAULT);
  $expiresAt = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

  db()->prepare('
    UPDATE user_remember_tokens
       SET selector = ?,
           token_hash = ?,
           expires_at = ?,
           last_used_at = NOW(),
           user_agent = ?,
           ip_address = ?
     WHERE id = ?
       AND user_id = ?
  ')->execute([
    $selector,
    $tokenHash,
    $expiresAt,
    substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    $_SERVER['REMOTE_ADDR'] ?? null,
    $tokenId,
    $userId,
  ]);

  auth_set_cookie(
    auth_remember_cookie_name(),
    $selector . ':' . $validator,
    time() + (60 * 60 * 24 * 30)
  );
}

function auth_try_remember_login(): void
{
  if (!empty($_SESSION['user'])) {
    return;
  }

  $cookieValue = $_COOKIE[auth_remember_cookie_name()] ?? '';
  if ($cookieValue === '') {
    return;
  }

  $parts = explode(':', $cookieValue, 2);
  if (count($parts) !== 2) {
    auth_forget_remember_me_cookie();
    return;
  }

  [$selector, $validator] = $parts;

  if ($selector === '' || $validator === '') {
    auth_forget_remember_me_cookie();
    return;
  }

  try {
    $stmt = db()->prepare('
      SELECT
        rt.id AS remember_id,
        rt.user_id,
        rt.token_hash,
        rt.expires_at,
        u.id,
        u.name,
        u.email,
        u.role,
        u.setor,
        u.hierarquia,
        u.is_active,
        u.permissions,
        u.phone,
        u.birth_date,
        u.gender,
        u.profile_photo_path
      FROM user_remember_tokens rt
      INNER JOIN users u ON u.id = rt.user_id
      WHERE rt.selector = ?
      LIMIT 1
    ');
    $stmt->execute([$selector]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      auth_forget_remember_me_cookie();
      return;
    }

    if ((int)($row['is_active'] ?? 0) !== 1) {
      auth_delete_remember_me_token((int)$row['remember_id']);
      auth_forget_remember_me_cookie();
      return;
    }

    if (strtotime((string)$row['expires_at']) < time()) {
      auth_delete_remember_me_token((int)$row['remember_id']);
      auth_forget_remember_me_cookie();
      return;
    }

    if (!password_verify($validator, (string)$row['token_hash'])) {
      auth_delete_remember_me_token((int)$row['remember_id']);
      auth_forget_remember_me_cookie();
      return;
    }

    session_regenerate_id(true);

    $_SESSION['user'] = auth_create_session_user($row);

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$row['id']]);
    auth_rotate_remember_me_token((int)$row['remember_id'], (int)$row['user_id']);
  } catch (Throwable $e) {
    auth_forget_remember_me_cookie();
  }
}

/* ===============================
   SESSÃO / USUÁRIO
   =============================== */

function start_session(): void
{
  if (session_status() === PHP_SESSION_NONE) {
    $sessionName = defined('SESSION_NAME') && SESSION_NAME !== ''
      ? SESSION_NAME
      : 'POPPERSESSID';

    session_name($sessionName);

    session_set_cookie_params([
      'lifetime' => 0,
      'path' => '/',
      'secure' => auth_is_https(),
      'httponly' => true,
      'samesite' => 'Lax',
    ]);

    session_start();
  }

  if (empty($_SESSION['user'])) {
    auth_try_remember_login();
  }
}

function current_user(): ?array
{
  start_session();

  if (empty($_SESSION['user']['id'])) {
    return null;
  }

  $id = (int)$_SESSION['user']['id'];

  try {
    $stmt = db()->prepare('
      SELECT id, name, email, role, setor, hierarquia, is_active, permissions, phone, birth_date, gender, profile_photo_path
      FROM users
      WHERE id = ?
      LIMIT 1
    ');
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) {
      $_SESSION['user'] = null;
      return null;
    }

    if ((int)($u['is_active'] ?? 0) !== 1) {
      $_SESSION['user'] = null;
      return null;
    }

    $_SESSION['user'] = auth_create_session_user($u);
    return $_SESSION['user'];
  } catch (Throwable $e) {
    return $_SESSION['user'] ?? null;
  }
}

function require_login(): void
{
  start_session();

  if (empty($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
  }
}

function require_admin(): void
{
  require_login();

  if (($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }
}

/* ===============================
   LOGIN / LOGOUT
   =============================== */

function login(string $email, string $pass, bool $remember = false): bool
{
  start_session();

  try {
    $stmt = db()->prepare('
      SELECT id, name, email, password_hash, role, setor, hierarquia, is_active, permissions, phone, birth_date, gender, profile_photo_path
      FROM users
      WHERE email = ?
      LIMIT 1
    ');
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) return false;
    if ((int)($u['is_active'] ?? 0) !== 1) return false;
    if (!isset($u['password_hash']) || !password_verify($pass, (string)$u['password_hash'])) return false;

    session_regenerate_id(true);

    $_SESSION['user'] = auth_create_session_user($u);

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$u['id']]);

    if ($remember) {
      auth_create_remember_me_token((int)$u['id']);
    } else {
      auth_delete_remember_me_token_by_cookie($_COOKIE[auth_remember_cookie_name()] ?? null);
      auth_forget_remember_me_cookie();
    }

    return true;
  } catch (Throwable $e) {
    return false;
  }
}

function logout(): void
{
  start_session();

  auth_delete_remember_me_token_by_cookie($_COOKIE[auth_remember_cookie_name()] ?? null);
  auth_forget_remember_me_cookie();

  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
      session_name(),
      '',
      time() - 42000,
      $params['path'],
      $params['domain'] ?? '',
      (bool)$params['secure'],
      (bool)$params['httponly']
    );
  }

  session_destroy();
}