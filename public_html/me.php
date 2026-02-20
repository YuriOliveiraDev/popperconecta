<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$id = (int)($u['id'] ?? 0);
if ($id <= 0) {
  http_response_code(401);
  echo 'Sessão inválida.';
  exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $newPass = trim($_POST['new_pass'] ?? '');

  if ($name === '' || $email === '') {
    $error = 'Nome e e-mail são obrigatórios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Formato de e-mail inválido.';
  } else {
    try {
      // E-mail duplicado (outro usuário)
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $stmt->execute([$email, $id]);
      if ($stmt->fetch()) {
        $error = 'Este e-mail já está em uso por outro usuário.';
      } else {
        if ($newPass !== '') {
          if (strlen($newPass) < 6) {
            $error = 'A nova senha deve ter pelo menos 6 caracteres.';
          } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $stmt = db()->prepare('UPDATE users SET name=?, email=?, password_hash=? WHERE id=?');
            $stmt->execute([$name, $email, $hash, $id]);
            $success = 'Dados atualizados com sucesso.';
          }
        } else {
          $stmt = db()->prepare('UPDATE users SET name=?, email=? WHERE id=?');
          $stmt->execute([$name, $email, $id]);
          $success = 'Dados atualizados com sucesso.';
        }
      }
    } catch (Throwable $e) {
      $error = 'Erro ao atualizar: ' . $e->getMessage();
    }
  }

  // Recarrega o usuário (seu auth atual já faz refresh por request, mas aqui garante)
  $u = current_user();
}

// Para destacar menu (opcional)
$activePage = 'me';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Meus dados — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container">
  <h2 class="page-title">Meus dados</h2>

  <section class="card card--narrow">
    <div class="card__header">
      <h3 class="card__title">Editar dados</h3>
      <p class="card__subtitle">Atualize seu nome, e-mail e senha.</p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="form form--edit" action="/me.php" autocomplete="off">
      <div class="field">
        <label class="field__label" for="name">Nome</label>
        <input class="field__control" id="name" name="name" type="text" required value="<?= htmlspecialchars((string)($u['name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="email">E-mail</label>
        <input class="field__control" id="email" name="email" type="email" required value="<?= htmlspecialchars((string)($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field field--full">
        <label class="field__label" for="new_pass">Nova senha (opcional)</label>
        <input class="field__control" id="new_pass" name="new_pass" type="password" autocomplete="new-password" />
        <div class="help">Deixe em branco para não alterar.</div>
      </div>

      <div class="field field--full">
        <div class="help">
          Perfil: <strong><?= htmlspecialchars((string)($u['role'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
          <?php if (!empty($u['setor'])): ?> | Setor: <strong><?= htmlspecialchars((string)$u['setor'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
          <?php if (!empty($u['hierarquia'])): ?> | Hierarquia: <strong><?= htmlspecialchars((string)$u['hierarquia'], ENT_QUOTES, 'UTF-8') ?></strong><?php endif; ?>
        </div>
      </div>

      <div class="form-actions">
        <a class="link link--pill" href="/index.php">Voltar</a>
        <button type="submit" class="btn btn--primary">Salvar</button>
      </div>
    </form>
  </section>
</main>

<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

</body>
</html>