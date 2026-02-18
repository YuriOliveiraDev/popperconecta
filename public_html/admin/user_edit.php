<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$success = '';
$error = '';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido.';
  exit;
}

// Carrega dados do usuário
$stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

// Salva alterações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role = trim($_POST['role'] ?? 'user');
  $setor = trim($_POST['setor'] ?? '');
  $hierarquia = trim($_POST['hierarquia'] ?? 'Assistente');
  $is_active = (int)($_POST['is_active'] ?? 1);
  $newPass = trim($_POST['new_pass'] ?? '');

  if ($name === '' || $email === '' || $setor === '') {
    $error = 'Nome, e-mail e setor são obrigatórios.';
  } else {
    try {
      // E-mail duplicado?
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $stmt->execute([$email, $id]);
      if ($stmt->fetch()) {
        $error = 'Este e-mail já está em uso por outro usuário.';
      } else {
        if ($newPass !== '') {
          $hash = password_hash($newPass, PASSWORD_DEFAULT);
          $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=?, setor=?, hierarquia=?, is_active=?, password_hash=? WHERE id=?');
          $stmt->execute([$name, $email, $role, $setor, $hierarquia, $is_active, $hash, $id]);
        } else {
          $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=?, setor=?, hierarquia=?, is_active=? WHERE id=?');
          $stmt->execute([$name, $email, $role, $setor, $hierarquia, $is_active, $id]);
        }

        $success = 'Usuário atualizado com sucesso.';

        // Recarrega
        $stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();
      }
    } catch (Throwable $e) {
      $error = 'Erro ao atualizar: ' . $e->getMessage();
    }
  }
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Editar Usuário — <?= htmlspecialchars(APP_NAME) ?></title>

  <!-- CSS base (igual ao da página users) -->
  <link rel="stylesheet" href="../assets/css/users.css" />
  <!-- CSS específico desta tela -->
  <link rel="stylesheet" href="../assets/css/edit.css" />
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong class="brand"><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Administração</span>
    </div>
    <a class="link" href="/admin/users.php">← Voltar</a>
  </header>

  <main class="container">
    <h2 class="page-title">Editar Usuário</h2>

    <div class="card card--narrow">
      <div class="card__header">
        <h3 class="card__title"><?= htmlspecialchars($user['name']) ?></h3>
        <p class="card__subtitle"><?= htmlspecialchars($user['email']) ?></p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert--ok"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="form form--edit" action="/admin/user_edit.php?id=<?= (int)$user['id'] ?>">
        <div class="field">
          <label class="field__label" for="name">Nome completo</label>
          <input class="field__control" id="name" name="name" type="text" required value="<?= htmlspecialchars($user['name']) ?>" />
        </div>

        <div class="field">
          <label class="field__label" for="email">E-mail</label>
          <input class="field__control" id="email" name="email" type="email" required value="<?= htmlspecialchars($user['email']) ?>" />
        </div>

        <div class="field">
          <label class="field__label" for="setor">Setor</label>
          <select class="field__control" id="setor" name="setor" required>
            <?php
              $setores = ['FACILITIES','RH','FINANCEIRO','LOGISTICA','COMERCIAL','COMEX','DIRETORIA','CONTROLADORIA','MARKETING'];
              $curSetor = (string)($user['setor'] ?? '');
              echo '<option value="">Selecione...</option>';
              foreach ($setores as $s) {
                $sel = ($s === $curSetor) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($s) . '" ' . $sel . '>' . htmlspecialchars($s) . '</option>';
              }
            ?>
          </select>
        </div>

        <div class="field">
          <label class="field__label" for="hierarquia">Hierarquia</label>
          <select class="field__control" id="hierarquia" name="hierarquia" required>
            <?php
              $hierarquias = ['Assistente', 'Analista', 'Supervisor', 'Gestor', 'Gerente', 'Diretor'];
              $cur = (string)($user['hierarquia'] ?? 'Assistente');
              foreach ($hierarquias as $h) {
                $selected = ($cur === $h) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($h) . '" ' . $selected . '>' . htmlspecialchars($h) . '</option>';
              }
            ?>
          </select>
        </div>

        <div class="field">
          <label class="field__label" for="role">Perfil</label>
          <select class="field__control" id="role" name="role" required>
            <option value="user" <?= ($user['role'] === 'user') ? 'selected' : '' ?>>Usuário</option>
            <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
          </select>
        </div>

        <div class="field">
          <label class="field__label" for="is_active">Status</label>
          <select class="field__control" id="is_active" name="is_active" required>
            <option value="1" <?= ((int)$user['is_active'] === 1) ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= ((int)$user['is_active'] === 0) ? 'selected' : '' ?>>Inativo</option>
          </select>
        </div>

        <div class="field field--full">
          <label class="field__label" for="new_pass">Nova senha (deixe em branco para não alterar)</label>
          <input class="field__control" id="new_pass" name="new_pass" type="password" autocomplete="new-password" />
          <div class="help">Se ficar em branco, a senha atual será mantida.</div>
        </div>

        <div class="form-actions">
          <a class="link link--pill" href="/admin/users.php">Cancelar</a>
          <button type="submit" class="btn btn--primary">Salvar alterações</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>