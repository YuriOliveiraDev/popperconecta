<?php
declare(strict_types=1);

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
    <script src="/assets/js/view-detect.js?v=1"></script>
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

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_session(): void {
  if (session_status() === PHP_SESSION_NONE) {
    $sessionName = defined('SESSION_NAME') && SESSION_NAME !== ''
      ? SESSION_NAME
      : 'POPPERSESSID';

    session_name($sessionName);

    $isHttps = (
      (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
      || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

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

    $_SESSION['user'] = [
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

    return $_SESSION['user'];
  } catch (Throwable $e) {
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

  try {
    $stmt = db()->prepare('
      SELECT id, name, email, password_hash, role, is_active, permissions, phone, birth_date, gender, profile_photo_path
      FROM users
      WHERE email = ?
      LIMIT 1
    ');
    $stmt->execute([$email]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$u) return false;
    if ((int)($u['is_active'] ?? 0) !== 1) return false;
    if (!isset($u['password_hash']) || !password_verify($pass, $u['password_hash'])) return false;

    session_regenerate_id(true);

    $_SESSION['user'] = [
      'id' => (int)$u['id'],
      'name' => (string)$u['name'],
      'email' => (string)$u['email'],
      'role' => (string)$u['role'],
      'permissions' => $u['permissions'] ?? null,
      'phone' => (string)($u['phone'] ?? ''),
      'birth_date' => (string)($u['birth_date'] ?? ''),
      'gender' => (string)($u['gender'] ?? ''),
      'profile_photo_path' => (string)($u['profile_photo_path'] ?? ''),
    ];

    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$u['id']]);

    return true;
  } catch (Throwable $e) {
    return false;
  }
}

function logout(): void {
  start_session();
  $_SESSION = [];

  if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool)$params['secure'], (bool)$params['httponly']);
  }

  session_destroy();
}