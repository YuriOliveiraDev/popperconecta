<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

// ✅ Adicionado: essencial para o header funcionar
$u = current_user();

// (Opcional) Para o dropdown "Dashboards" ter a lista completa
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$error = '';

// --- LÓGICA PARA CADASTRO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim($_POST['name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $pass = trim($_POST['pass'] ?? '');
  $role = trim($_POST['role'] ?? 'user');
  $setor = trim($_POST['setor'] ?? '');
  $hierarquia = trim($_POST['hierarquia'] ?? 'Assistente');

  if (empty($name) || empty($email) || empty($pass) || empty($setor)) {
    $error = 'Todos os campos são obrigatórios.';
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Formato de e-mail inválido.';
  } elseif (!in_array($role, ['user', 'admin'], true)) {
    $error = 'Perfil inválido.';
  } elseif (!in_array($hierarquia, ['Diretor','Gerente','Gestor','Supervisor','Analista','Assistente'], true)) {
    $error = 'Hierarquia inválida.';
  } else {
    try {
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
        $error = 'Este e-mail já está cadastrado.';
      } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
          'INSERT INTO users (name, email, password_hash, role, setor, hierarquia, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([$name, $email, $hash, $role, $setor, $hierarquia]);
        $success = "Usuário '{$name}' cadastrado com sucesso!";
      }
    } catch (Throwable $e) {
      $error = 'Erro ao cadastrar usuário: ' . $e->getMessage();
    }
  }
}

// --- LÓGICA PARA LISTAGEM (SELECT) ---
$users = db()->query(
  'SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at FROM users ORDER BY name ASC'
)->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin: Usuários — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- ✅ CSS atualizados com cache-busting -->
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
</head>
<body class="page">

  <!-- ✅ Header antigo substituído pelo template -->
  <?php require_once __DIR__ . '/../app/header.php'; ?>

  <main class="container">
    <h2 class="page-title">Gerenciar Usuários</h2>

    <section class="card">
      <div class="card__header">
        <h3 class="card__title">Cadastrar Novo Usuário</h3>
        <p class="card__subtitle">Preencha os campos para criar um novo usuário.</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert--ok"><?= htmlspecialchars($success) ?></div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="alert alert--error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <form method="post" class="form" action="/admin/users.php" autocomplete="off">
        <label class="field">
          <span class="field__label">Nome Completo</span>
          <input class="field__control" name="name" type="text" required />
        </label>

        <label class="field">
          <span class="field__label">E-mail</span>
          <input class="field__control" name="email" type="email" required />
        </label>

        <label class="field">
          <span class="field__label">Senha</span>
          <input class="field__control" name="pass" type="password" required />
        </label>

        <label class="field">
          <span class="field__label">Setor</span>
          <select class="field__control" name="setor" required>
            <option value="">Selecione...</option>
            <option value="FACILITIES">FACILITIES</option>
            <option value="RH">RH</option>
            <option value="FINANCEIRO">FINANCEIRO</option>
            <option value="LOGISTICA">LOGISTICA</option>
            <option value="COMERCIAL">COMERCIAL</option>
            <option value="COMEX">COMEX</option>
            <option value="DIRETORIA">DIRETORIA</option>
            <option value="CONTROLADORIA">CONTROLADORIA</option>
            <option value="MARKETING">MARKETING</option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Perfil</span>
          <select class="field__control" name="role" required>
            <option value="user">Usuário</option>
            <option value="admin">Administrador</option>
          </select>
        </label>

        <label class="field">
          <span class="field__label">Hierarquia</span>
          <select class="field__control" name="hierarquia" required>
            <option value="Assistente">Assistente</option>
            <option value="Analista">Analista</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Gestor">Gestor</option>
            <option value="Gerente">Gerente</option>
            <option value="Diretor">Diretor</option>
          </select>
        </label>

        <button class="btn btn--primary" type="submit">Cadastrar Usuário</button>
      </form>
    </section>

    <section class="card card--mt">
      <div class="card__header">
        <h3 class="card__title">Usuários Cadastrados</h3>
        <p class="card__subtitle">Lista atual de usuários e acesso rápido à edição.</p>
      </div>

      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Nome</th>
              <th>E-mail</th>
              <th>Setor</th>
              <th>Hierarquia</th>
              <th>Perfil</th>
              <th>Ativo</th>
              <th>Último login</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td><?= htmlspecialchars($u['name']) ?></td>
                <td><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars($u['setor']) ?></td>
                <td><?= htmlspecialchars($u['hierarquia']) ?></td>
                <td>
                  <?php if (($u['role'] ?? '') === 'admin'): ?>
                    <span class="badge badge--admin">Admin</span>
                  <?php else: ?>
                    <span class="badge badge--user">User</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)$u['is_active'] === 1): ?>
                    <span class="badge badge--ok">Sim</span>
                  <?php else: ?>
                    <span class="badge badge--no">Não</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)($u['last_login_at'] ?? '—')) ?></td>
                <td>
                  <a class="link link--pill" href="/admin/user_edit.php?id=<?= (int)$u['id'] ?>">Editar</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <!-- ✅ Adicionado: script para os dropdowns do header funcionarem -->
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>

</body>
</html>