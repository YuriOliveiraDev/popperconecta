<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/permissions.php'; // ✅ ADICIONADO: para require_perm()


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
  <title>RH — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- CSS global -->
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/../assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/../assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/../assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/rh.css?v=<?= filemtime(__DIR__ . '/../assets/css/rh.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/../assets/css/header.css') ?>" />
</head>
<body class="page">

<?php require_once __DIR__ . '/../app/header.php'; ?>

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
        <a class="btn-modern btn-modern--accent" href="/admin/rh_coins.php">
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
        <a class="btn-modern btn-modern--accent" href="/admin/rh_rewards.php">
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
        <a class="btn-modern btn-modern--accent" href="/admin/rh_redemptions.php">
          <span class="btn-modern__icon">↗</span>
          Acessar
        </a>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/../app/footer.php'; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>" defer></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>" defer></script>
</body>
</html>