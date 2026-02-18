<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_login();

$u = current_user();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard — <?= htmlspecialchars(APP_NAME) ?></title>
  <link rel="stylesheet" href="/assets/css/dashboard.css" />
</head>
<body class="page">
  <header class="topbar">
    <div class="topbar__left">
      <strong><?= htmlspecialchars(APP_NAME) ?></strong>
      <span class="muted">Bem-vindo, <?= htmlspecialchars($u['name']) ?></span>
      <?php if (($u['role'] ?? '') === 'admin'): ?>
        <a class="link" href="/admin/users.php" style="margin-left:12px;">Usuários</a>
      <?php endif; ?>
    </div>
    <a class="link" href="/logout.php">Sair</a>
  </header>

  <main class="container">
    <h2>Início</h2>

    <section class="carousel">
      <div class="carousel__track" id="track">
        <article class="slide"><h3>Comunicado</h3><p>Bem-vindo ao Popper Conecta.</p></article>
        <article class="slide"><h3>Indicador</h3><p>Card 2 (placeholder)</p></article>
        <article class="slide"><h3>Status</h3><p>TOTVS: futuro endpoint /api/kpis</p></article>
      </div>

      <div class="carousel__controls">
        <button type="button" id="prev">Anterior</button>
        <button type="button" id="next">Próximo</button>
      </div>
    </section>
  </main>

  <script src="/assets/js/carousel.js"></script>
</body>
</html>