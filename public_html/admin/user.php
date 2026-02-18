<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_admin();

$users = db()->query('SELECT id, name, email, role, is_active, last_login_at FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Usuários — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/style.css" />
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Administração</span>
    </div>
    <a class="link" href="/dashboard.php">Voltar</a>
  </header>

  <main class="container">
    <h2>Usuários</h2>
    <p><a class="link" href="/admin/user_new.php">+ Criar usuário</a></p>

    <div class="card" style="margin:0; width:auto;">
      <div style="overflow:auto;">
        <table style="width:100%; border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left; padding:10px; border-bottom:1px solid #223055;">Nome</th>
              <th style="text-align:left; padding:10px; border-bottom:1px solid #223055;">E-mail</th>
              <th style="text-align:left; padding:10px; border-bottom:1px solid #223055;">Perfil</th>
              <th style="text-align:left; padding:10px; border-bottom:1px solid #223055;">Ativo</th>
              <th style="text-align:left; padding:10px; border-bottom:1px solid #223055;">Último login</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $u): ?>
              <tr>
                <td style="padding:10px; border-bottom:1px solid #223055;"><?= htmlspecialchars($u['name']) ?></td>
                <td style="padding:10px; border-bottom:1px solid #223055;"><?= htmlspecialchars($u['email']) ?></td>
                <td style="padding:10px; border-bottom:1px solid #223055;"><?= htmlspecialchars($u['role']) ?></td>
                <td style="padding:10px; border-bottom:1px solid #223055;"><?= ((int)$u['is_active']===1) ? 'Sim' : 'Não' ?></td>
                <td style="padding:10px; border-bottom:1px solid #223055;"><?= htmlspecialchars((string)($u['last_login_at'] ?? '—')) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</body>
</html>