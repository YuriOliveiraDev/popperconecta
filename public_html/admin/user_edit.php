<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';
require_admin();

$u = current_user(); // para o header

$success = '';
$error = '';

$me = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  http_response_code(400);
  echo 'ID inválido.';
  exit;
}

// Carrega dados do usuário
$stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at, permissions FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$user = $stmt->fetch();

if (!$user) {
  http_response_code(404);
  echo 'Usuário não encontrado.';
  exit;
}

$allowedPerms = array_keys(PERMISSION_CATALOG);
$curPerms = user_perms($user);
$curPerms = array_values(array_unique(array_intersect($curPerms, $allowedPerms)));

// EXCLUI USUÁRIO (POST + action=delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
  if ((int)$me['id'] === (int)$id) {
    $error = 'Você não pode excluir o seu próprio usuário.';
  } else {
    try {
      $stmt = db()->prepare('DELETE FROM users WHERE id = ? LIMIT 1');
      $stmt->execute([$id]);

      if ($stmt->rowCount() < 1) {
        $error = 'Não foi possível excluir (usuário não encontrado).';
      } else {
        header('Location: /admin/users.php?deleted=1');
        exit;
      }
    } catch (Throwable $e) {
      $error = 'Erro ao excluir: ' . $e->getMessage();
    }
  }
}

// Salva alterações (POST normal)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $role = trim($_POST['role'] ?? 'user');
  $setor = trim($_POST['setor'] ?? '');
  $hierarquia = trim($_POST['hierarquia'] ?? 'Assistente');
  $is_active = (int)($_POST['is_active'] ?? 1);
  $newPass = trim($_POST['new_pass'] ?? '');

  // Permissões (checkbox)
  $perms = $_POST['perms'] ?? [];
  if (!is_array($perms)) $perms = [];
  $perms = array_values(array_unique(array_intersect($perms, $allowedPerms)));
  $permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);

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
          $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=?, setor=?, hierarquia=?, is_active=?, permissions=?, password_hash=? WHERE id=?');
          $stmt->execute([$name, $email, $role, $setor, $hierarquia, $is_active, $permsJson, $hash, $id]);
        } else {
          $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=?, setor=?, hierarquia=?, is_active=?, permissions=? WHERE id=?');
          $stmt->execute([$name, $email, $role, $setor, $hierarquia, $is_active, $permsJson, $id]);
        }

        $success = 'Usuário atualizado com sucesso.';

        // Recarrega
        $stmt = db()->prepare('SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at, permissions FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        // Recalcula permissões atuais para refletir no HTML
        $curPerms = user_perms($user);
        $curPerms = array_values(array_unique(array_intersect($curPerms, $allowedPerms)));
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
  <title>Editar Usuário — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- ✅ mesmos CSS base do site para o header funcionar igual -->
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />

  <!-- seu CSS específico do edit -->
  <link rel="stylesheet" href="/assets/css/edit.css?v=<?= filemtime(__DIR__ . '/../assets/css/edit.css') ?>" />

  <style>
    /* ✅ garante que dropdowns (perfil/admin/dashboards) não sejam cortados nesta página */
    .topbar { overflow: visible !important; }
    .page { overflow: visible !important; }

    .perm-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 8px 14px;
      padding-top: 6px;
    }
    .perm-item {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      margin: 0;
      user-select: none;
    }
    .perm-help {
      margin-top: 8px;
      font-size: 12px;
      opacity: .8;
    }
    @media (max-width: 720px) {
      .perm-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

<a class="link" href="/admin/users.php" style="display:block;margin:12px 20px;">← Voltar</a>

<main class="container">
  <h2 class="page-title">Editar Usuário</h2>

  <div class="card card--narrow">
    <div class="card__header">
      <h3 class="card__title"><?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?></h3>
      <p class="card__subtitle"><?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <?php if ($success): ?>
      <div class="alert alert--ok"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert--error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post" class="form form--edit" action="/admin/user_edit.php?id=<?= (int)$user['id'] ?>">
      <div class="field">
        <label class="field__label" for="name">Nome completo</label>
        <input class="field__control" id="name" name="name" type="text" required value="<?= htmlspecialchars((string)$user['name'], ENT_QUOTES, 'UTF-8') ?>" />
      </div>

      <div class="field">
        <label class="field__label" for="email">E-mail</label>
        <input class="field__control" id="email" name="email" type="email" required value="<?= htmlspecialchars((string)$user['email'], ENT_QUOTES, 'UTF-8') ?>" />
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
              echo '<option value="' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '" ' . $sel . '>' . htmlspecialchars($s, ENT_QUOTES, 'UTF-8') . '</option>';
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
              echo '<option value="' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '" ' . $selected . '>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</option>';
            }
          ?>
        </select>
      </div>

      <div class="field">
        <label class="field__label" for="role">Perfil</label>
        <select class="field__control" id="role" name="role" required>
          <option value="user" <?= (($user['role'] ?? '') === 'user') ? 'selected' : '' ?>>Usuário</option>
          <option value="admin" <?= (($user['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
        </select>
      </div>

      <div class="field field--full">
        <label class="field__label">Permissões (Administração)</label>
        <div class="perm-grid">
          <?php foreach (PERMISSION_CATALOG as $perm => $meta): ?>
            <label class="perm-item">
              <input type="checkbox" name="perms[]" value="<?= htmlspecialchars($perm, ENT_QUOTES, 'UTF-8') ?>" <?= in_array($perm, $curPerms, true) ? 'checked' : '' ?>>
              <span><?= htmlspecialchars((string)($meta['label'] ?? $perm), ENT_QUOTES, 'UTF-8') ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="perm-help">Marque as áreas do Admin que este usuário poderá acessar.</div>
      </div>

      <div class="field">
        <label class="field__label" for="is_active">Status</label>
        <select class="field__control" id="is_active" name="is_active" required>
          <option value="1" <?= ((int)($user['is_active'] ?? 0) === 1) ? 'selected' : '' ?>>Ativo</option>
          <option value="0" <?= ((int)($user['is_active'] ?? 0) === 0) ? 'selected' : '' ?>>Inativo</option>
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

    <form method="post" action="/admin/user_edit.php?id=<?= (int)$user['id'] ?>" onsubmit="return confirm('Tem certeza que deseja excluir este usuário? Essa ação não pode ser desfeita.');" style="margin-top: 14px;">
      <input type="hidden" name="action" value="delete">
      <button type="submit" class="btn btn--danger" <?= ((int)$me['id'] === (int)$user['id']) ? 'disabled' : '' ?>
        title="<?= ((int)$me['id'] === (int)$user['id']) ? 'Você não pode excluir o seu próprio usuário.' : 'Excluir usuário' ?>">
        Excluir usuário
      </button>
    </form>

  </div>
</main>

<!-- ✅ necessário para dropdowns do header (Admin/Dashboards) -->
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>

</body>
</html>