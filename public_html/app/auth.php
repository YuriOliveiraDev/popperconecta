<?php
declare(strict_types=1);

/* ===============================
   BLOQUEIO MOBILE (temporário)
   =============================== */
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$isAndroid = stripos($userAgent, 'Android') !== false;
$isAndroidMobile = $isAndroid && stripos($userAgent, 'Mobile') !== false;

$isTV = (bool)preg_match('/Android TV|SMART-TV|SmartTV|HbbTV|AFT|BRAVIA|Tizen|Web0S/i', $userAgent);

$isMobile = (!$isTV) && (
  (bool)preg_match('/iPhone|iPod|Windows Phone|webOS|BlackBerry|Opera Mini|Mobile/i', $userAgent)
  || $isAndroidMobile
);
if ($isMobile) {
  header('Content-Type: text/html; charset=UTF-8');
  ?>
  <!doctype html>
  <html lang="pt-BR">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Popper Conecta</title>
    <style>
      body{
        margin:0;
        font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
        background:#ffffff;
        color:#0f172a;
        height:100vh;
        display:flex;
        align-items:center;
        justify-content:center;
        text-align:center;
      }
      .mobile-block{max-width:420px;padding:32px;}
      .logo{font-weight:900;font-size:18px;color:#5c2c84;margin-bottom:24px;}
      h1{font-size:22px;margin:0 0 12px 0;font-weight:900;}
      p{font-size:15px;opacity:.75;line-height:1.5;margin:0;}
    </style>
  </head>
  <body>
    <div class="mobile-block">
      <div class="logo">Popper Conecta</div>
      <h1>🚧 Indisponível no mobile</h1>
      <p>
        Este portal ainda não está disponível para celulares.<br><br>
        Acesse usando um computador para visualizar dashboards e funcionalidades.
      </p>
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
    // ✅ CORREÇÃO: Incluí profile_photo_path, phone, birth_date, gender no SELECT
    $stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, permissions, phone, birth_date, gender, profile_photo_path FROM users WHERE id = ? LIMIT 1');
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

    // ✅ CORREÇÃO: Incluí os campos novos na sessão
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

  // ✅ CORREÇÃO: Incluí phone, birth_date, gender, profile_photo_path no SELECT
  $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active, permissions, phone, birth_date, gender, profile_photo_path FROM users WHERE email = ? LIMIT 1');
  $stmt->execute([$email]);
  $u = $stmt->fetch();

  if (!$u) return false;
  if ((int)$u['is_active'] !== 1) return false;
  if (!password_verify($pass, $u['password_hash'])) return false;

  session_regenerate_id(true);

  // ✅ CORREÇÃO: Incluí os campos novos na sessão
  $_SESSION['user'] = [
    'id' => (int)$u['id'],
    'name' => $u['name'],
    'email' => $u['email'],
    'role' => $u['role'],
    'permissions' => $u['permissions'] ?? null,
    'phone' => (string)($u['phone'] ?? ''),
    'birth_date' => (string)($u['birth_date'] ?? ''),
    'gender' => (string)($u['gender'] ?? ''),
    'profile_photo_path' => (string)($u['profile_photo_path'] ?? ''),
  ];

  db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$u['id']]);
  return true;
}

function logout(): void {
  start_session();
  $_SESSION = [];
  session_destroy();
}

// ✅ REMOVIDO: user_can() e require_permission() duplicados (já existem em permissions.php)
?>