<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_admin();

$success = '';
$error = '';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido.';
  exit;
}

// Carrega os dados do usuário para preencher o formulário
$stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

// Lógica para salvar as alterações via POST
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
      // Verifica se o e-mail já está em uso por OUTRO usuário
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
      $stmt->execute([$email, $id]);
      if ($stmt->fetch()) {
        $error = 'Este e-mail já está em uso por outro usuário.';
      } else {
        // Se uma nova senha foi fornecida, atualiza o hash
        if ($newPass !== '') {
          $hash = password_hash($newPass, PASSWORD_DEFAULT);
          $stmt = db()->prepare(
            'UPDATE users SET name=?, email=?, role=?, setor=?, hierarquia=?, is_active=?, password_hash=? WHERE id=?'
          );
          $stmt->execute([$name, $email, $role, $setor, $hierarquia, $is_active, $hash, $id]);
        } else {
          // Se não, atualiza tudo, menos a senha
          $stmt = db()->prepare(
            'UPDATE users SET name=?, email=?, role=?, setor=?, hierarquia=?, is_active=? WHERE id=?'
          );
          $stmt->execute([$name, $email, $role, $setor, $hierarquia, $is_active, $id]);
        }
        $success = 'Usuário atualizado com sucesso.';
        
        // Recarrega os dados para exibir no formulário
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
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Editar Usuário</span>
    </div>
    <a class="link" href="/admin/users.php">Voltar</a>
  </header>

  <main class="container">
    <h2>Editar: <?= htmlspecialchars($user['name']) ?></h2>

    <div class="card">
      <?php if ($success): ?><div class="alert-ok"><?= htmlspecialchars($success) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <form method="post" class="form" action="/admin/user_edit.php?id=<?= (int)$user['id'] ?>">
        <label>Nome Completo<input name="name" type="text" required value="<?= htmlspecialchars($user['name']) ?>" /></label>
        <label>E-mail<input name="email" type="email" required value="<?= htmlspecialchars($user['email']) ?>" /></label>
        <label>Setor
  <select name="setor" required>
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
</label>

        <label>Hierarquia
          <select name="hierarquia" required>
            <?php
              $hierarquias = ['Assistente', 'Analista', 'Supervisor', 'Gestor', 'Gerente', 'Diretor'];
              foreach ($hierarquias as $h) {
                $selected = ($user['hierarquia'] === $h) ? 'selected' : '';
                echo "<option value='{$h}' {$selected}>{$h}</option>";
              }
            ?>
          </select>
        </label>

        <label>Perfil
          <select name="role" required>
            <option value="user" <?= ($user['role'] === 'user') ? 'selected' : '' ?>>Usuário</option>
            <option value="admin" <?= ($user['role'] === 'admin') ? 'selected' : '' ?>>Administrador</option>
          </select>
        </label>

        <label>Status
          <select name="is_active" required>
            <option value="1" <?= ((int)$user['is_active'] === 1) ? 'selected' : '' ?>>Ativo</option>
            <option value="0" <?= ((int)$user['is_active'] === 0) ? 'selected' : '' ?>>Inativo</option>
          </select>
        </label>
        
        <hr style="margin: 1rem 0; border-color: #223055; opacity: 0.5;">
        
        <label>Nova Senha (deixe em branco para não alterar)
          <input name="new_pass" type="password" autocomplete="new-password" />
        </label>

        <button type="submit">Salvar Alterações</button>
      </form>
    </div>
  </main>
</body>
</html>