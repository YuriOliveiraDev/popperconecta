<?php
declare(strict_types=1);
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_admin(); // Troque para require_login() se quiser testar sem ser admin

$u = current_user();
$activePage = 'admin'; // destaca no header

// Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

$current_dash = 'executivo';
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>RH — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(APP_ROOT . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(APP_ROOT . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(APP_ROOT . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/rh.css?v=<?= filemtime(APP_ROOT . '/assets/css/rh.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(APP_ROOT . '/assets/css/header.css') ?>" />
</head>

<body class="page">

  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main class="container rh">
    <h2 class="page-title">RH</h2>

    <section class="rh-grid">
      <div class="rh-card">
        <div class="rh-head">
          <h3 class="rh-title">Popper Coins · Lançamentos</h3>
          <span class="rh-badge">Admin</span>
        </div>
        <p class="rh-sub">Adicionar, remover, ajustar e registrar lançamentos no saldo dos usuários.</p>
        <div class="rh-actions">
          <a class="btn-modern btn-modern--accent" href="/admin/rh/rh_coins.php">
            <span class="btn-modern__icon">↗</span>
            Acessar
          </a>
        </div>
      </div>

      <div class="rh-card">
        <div class="rh-head">
          <h3 class="rh-title">Popper Coins · Recompensas</h3>
          <span class="rh-badge">Catálogo</span>
        </div>
        <p class="rh-sub">Cadastrar/editar recompensas e custos do catálogo (o que o usuário pode resgatar).</p>
        <div class="rh-actions">
          <a class="btn-modern btn-modern--accent" href="/admin/rh/rh_rewards.php">
            <span class="btn-modern__icon">↗</span>
            Acessar
          </a>
        </div>
      </div>

      <div class="rh-card">
        <div class="rh-head">
          <h3 class="rh-title">Popper Coins · Aprovações</h3>
          <span class="rh-badge">Pendências</span>
        </div>
        <p class="rh-sub">Aprovar ou negar resgates pendentes. Ao aprovar, o sistema debita as coins.</p>
        <div class="rh-actions">
          <a class="btn-modern btn-modern--accent" href="/admin/rh/rh_redemptions.php">
            <span class="btn-modern__icon">↗</span>
            Acessar
          </a>
        </div>
      </div>

      <div class="rh-card">
        <div class="rh-head">
          <h3 class="rh-title">Popper Coins · Campanhas</h3>
          <span class="rh-badge">Regras</span>
        </div>
        <p class="rh-sub">Cadastrar campanhas e valores fixos de coins para uso rápido nos lançamentos.</p>
        <div class="rh-actions">
          <a class="btn-modern btn-modern--accent" href="/admin/rh/rh_campaigns.php">
            <span class="btn-modern__icon">↗</span>
            Acessar
          </a>
        </div>
      </div>

    </section>
  </main>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>" defer></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(APP_ROOT . '/assets/js/dropdowns.js') ?>" defer></script> defer>
</body>

</html>