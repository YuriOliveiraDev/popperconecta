<?php
declare(strict_types=1);
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';
require_login();
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ✅ Essencial para o header.php funcionar
$u = current_user();

// ✅ Dropdown "Dashboards" no header
$dashboards = db()
  ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
  ->fetchAll(PDO::FETCH_ASSOC);

// Esta página é do financeiro
$current_dash = 'financeiro';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Financeiro — <?= htmlspecialchars((string)APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- ✅ CSS globais + específicos (igual aos outros) -->
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
</head>
<body class="page">

  <!-- ✅ Header (igual aos outros) -->
  <?php require_once __DIR__ . '/app/header.php'; ?>

  <main class="container">
    <h2 class="page-title">Financeiro (teste)</h2>

    <section class="dashboard-grid" style="grid-template-columns: repeat(2, minmax(0, 1fr));">
      <div class="kpi-card">
        <span class="kpi-label">Faturado no dia</span>
        <strong class="kpi-value" id="kpi-faturado-dia">R$ 0,00</strong>
        <span class="kpi-trend" id="kpi-fin-updated"></span>
      </div>

      <div class="kpi-card">
        <span class="kpi-label">Contas a pagar no dia</span>
        <strong class="kpi-value" id="kpi-contas-pagar-dia">R$ 0,00</strong>
        <span class="kpi-trend"></span>
      </div>

      <div class="data-table-card grid-col-span-2">
        <h3 class="table-title">Detalhamento</h3>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>Indicador</th>
                <th class="right">Valor</th>
              </tr>
            </thead>
            <tbody id="finTableBody"></tbody>
          </table>
        </div>
      </div>
    </section>
  </main>

  <!-- ✅ Footer (igual aos outros) -->
  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <!-- ✅ JS global + específico (igual aos outros) -->
  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

  <!-- ✅ Passa dados do PHP para o JS (sem PHP dentro do .js) -->
  <script>
    window.DASH_CURRENT = <?= json_encode($current_dash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <script src="/assets/js/financeiro.js?v=<?= filemtime(__DIR__ . '/assets/js/financeiro.js') ?>"></script>
</body>
</html>