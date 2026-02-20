<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php';
require_admin();

$me = current_user();

try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$success = '';
$errors = [];

// Mensagem após excluir (redirect do user_edit)
if (($_GET['deleted'] ?? '') === '1') {
  $success = 'Usuário excluído com sucesso.';
}

$allowedPerms = array_keys(PERMISSION_CATALOG);

// --- CADASTRO (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name       = trim($_POST['name'] ?? '');
  $email      = trim($_POST['adm_email'] ?? ($_POST['email'] ?? ''));
  $pass       = (string)($_POST['adm_pass'] ?? ($_POST['pass'] ?? ''));
  $role       = trim($_POST['role'] ?? 'user');
  $setor      = trim($_POST['setor'] ?? '');
  $hierarquia = trim($_POST['hierarquia'] ?? 'Assistente');

  // Permissões (checkbox)
  $perms = $_POST['perms'] ?? [];
  if (!is_array($perms)) $perms = [];
  $perms = array_values(array_unique(array_intersect($perms, $allowedPerms)));
  $permsJson = json_encode($perms, JSON_UNESCAPED_UNICODE);

  if ($name === '') $errors[] = 'Nome é obrigatório.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Formato de e-mail inválido.';
  if ($pass === '' || strlen($pass) < 6) $errors[] = 'A senha deve ter pelo menos 6 caracteres.';
  if ($setor === '') $errors[] = 'Setor é obrigatório.';
  if (!in_array($role, ['user','admin'], true)) $errors[] = 'Perfil inválido.';
  if (!in_array($hierarquia, ['Diretor','Gerente','Gestor','Supervisor','Analista','Assistente'], true)) $errors[] = 'Hierarquia inválida.';

  if (!$errors) {
    try {
      $stmt = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
      $stmt->execute([$email]);
      if ($stmt->fetch()) {
        $errors[] = 'Este e-mail já está cadastrado.';
      } else {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = db()->prepare(
          'INSERT INTO users (name, email, password_hash, role, setor, hierarquia, is_active, permissions)
           VALUES (?, ?, ?, ?, ?, ?, 1, ?)'
        );
        $stmt->execute([$name, $email, $hash, $role, $setor, $hierarquia, $permsJson]);
        $success = "Usuário '" . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . "' cadastrado com sucesso!";
      }
    } catch (Throwable $e) {
      $errors[] = 'Erro ao cadastrar usuário: ' . $e->getMessage();
    }
  }
}

// --- LISTAGEM ---
try {
  $users = db()->query(
    'SELECT id, name, email, role, setor, hierarquia, is_active, last_login_at, permissions FROM users ORDER BY name ASC'
  )->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $users = [];
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin: Usuários — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />

  <style>
    .offscreen-bait {
      position: absolute !important;
      left: -9999px !important;
      width: 1px !important;
      height: 1px !important;
      overflow: hidden !important;
      opacity: 0 !important;
    }
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

  <main class="container">
    <h2 class="page-title">Gerenciar Usuários</h2>

    <section class="card">
      <div class="card__header">
        <h3 class="card__title">Cadastrar Novo Usuário</h3>
        <p class="card__subtitle">Preencha os campos para criar um novo usuário.</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert--ok"><?= $success ?></div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert--error">
          <ul>
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" class="form" action="/admin/users.php" autocomplete="off">
        <input class="offscreen-bait" type="text" name="email" autocomplete="username email" aria-hidden="true" tabindex="-1">
        <input class="offscreen-bait" type="password" name="password" autocomplete="current-password" aria-hidden="true" tabindex="-1">

        <label class="field" for="name">
          <span class="field__label">Nome Completo</span>
          <input class="field__control" id="name" name="name" type="text" required autocomplete="off" spellcheck="false" autocapitalize="off" />
        </label>

        <label class="field" for="adm_email">
          <span class="field__label">E-mail</span>
          <input class="field__control" id="adm_email" name="adm_email" type="email" required autocomplete="off" inputmode="email" spellcheck="false" autocapitalize="off" readonly />
        </label>

        <label class="field" for="adm_pass">
          <span class="field__label">Senha</span>
          <input class="field__control" id="adm_pass" name="adm_pass" type="password" required autocomplete="off" readonly />
        </label>

        <label class="field" for="setor">
          <span class="field__label">Setor</span>
          <select class="field__control" id="setor" name="setor" required autocomplete="off">
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

        <label class="field" for="role">
          <span class="field__label">Perfil</span>
          <select class="field__control" id="role" name="role" required autocomplete="off">
            <option value="user">Usuário</option>
            <option value="admin">Administrador</option>
          </select>
        </label>

        <label class="field" for="hierarquia">
          <span class="field__label">Hierarquia</span>
          <select class="field__control" id="hierarquia" name="hierarquia" required autocomplete="off">
            <option value="Assistente">Assistente</option>
            <option value="Analista">Analista</option>
            <option value="Supervisor">Supervisor</option>
            <option value="Gestor">Gestor</option>
            <option value="Gerente">Gerente</option>
            <option value="Diretor">Diretor</option>
          </select>
        </label>

        <!-- PERMISSÕES -->
        <div class="field" style="grid-column: 1 / -1;">
          <span class="field__label">Permissões (Administração)</span>
          <div class="perm-grid">
            <?php foreach (PERMISSION_CATALOG as $perm => $meta): ?>
              <label class="perm-item">
                <input type="checkbox" name="perms[]" value="<?= htmlspecialchars($perm, ENT_QUOTES, 'UTF-8') ?>">
                <span><?= htmlspecialchars((string)($meta['label'] ?? $perm), ENT_QUOTES, 'UTF-8') ?></span>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="perm-help">Marque as áreas do Admin que este usuário poderá acessar.</div>
        </div>

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
            <?php foreach ($users as $row): ?>
              <tr>
                <td><?= htmlspecialchars((string)$row['name'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$row['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$row['setor'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)$row['hierarquia'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <?php if (($row['role'] ?? '') === 'admin'): ?>
                    <span class="badge badge--admin">Admin</span>
                  <?php else: ?>
                    <span class="badge badge--user">User</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int)($row['is_active'] ?? 0) === 1): ?>
                    <span class="badge badge--ok">Sim</span>
                  <?php else: ?>
                    <span class="badge badge--no">Não</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string)($row['last_login_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                  <a class="link link--pill" href="/admin/user_edit.php?id=<?= (int)$row['id'] ?>">Editar</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>

  <script>
    (function () {
      function arm(id) {
        var el = document.getElementById(id);
        if (!el) return;
        try { el.value = ''; } catch (e) {}
        var unfreeze = function () {
          if (el.hasAttribute('readonly')) el.removeAttribute('readonly');
          el.removeEventListener('focus', unfreeze);
          el.removeEventListener('pointerdown', unfreeze);
          el.removeEventListener('keydown', unfreeze);
        };
        el.addEventListener('focus', unfreeze, { once: true });
        el.addEventListener('pointerdown', unfreeze, { once: true });
        el.addEventListener('keydown', unfreeze, { once: true });
      }
      arm('adm_email');
      arm('adm_pass');
    })();
  </script>
</body>
</html>