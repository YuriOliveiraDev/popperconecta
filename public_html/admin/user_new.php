<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name  = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role  = ($_POST['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
  $pass  = (string)($_POST['pass'] ?? '');

  if ($name === '' || $email === '' || $pass === '') {
    $error = 'Preencha todos os campos.';
  } else {
    try {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = db()->prepare('INSERT INTO users (name, email, password_hash, role, is_active) VALUES (?, ?, ?, ?, 1)');
      $stmt->execute([$name, $email, $hash, $role]);
      $ok = 'Usuário criado com sucesso.';
    } catch (Throwable $e) {
      $error = 'Não foi possível criar (e-mail pode já existir).';
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Novo usuário — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Novo usuário</span>
    </div>
    <a class="link" href="/admin/users.php">Voltar</a>
  </header>

  <main class="container">
    <div class="card" style="margin:0; width:min(520px, 92vw);">
      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
      <?php if ($ok): ?><div class="alert" style="background:#12301f;border-color:#2b7a45;"><?= htmlspecialchars($ok) ?></div><?php endif; ?>

      <form method="post" class="form">
        <label>Nome <input name="name" required /></label>
        <label>E-mail <input name="email" type="email" required /></label>
        <label>Senha <input name="pass" type="password" required /></label>

        <label>
          Perfil
          <select name="role" required style="padding:10px 12px; border-radius:10px; border:1px solid #263357; background:#0b1326; color:var(--text);">
            <option value="user">Usuário</option>
            <option value="admin">Admin</option>
          </select>
        </label>

        <button type="submit">Criar</button>
      </form>
    </div>
  </main>
</body>
</html>