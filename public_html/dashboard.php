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

$current_dash = $_GET['dash'] ?? 'executivo';
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dashboard — <?= htmlspecialchars((string) APP_NAME, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- CSS global + específicos -->
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/users.css?v=<?= filemtime(__DIR__ . '/assets/css/users.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dashboard.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>

<body class="page">
  <?php require_once __DIR__ . '/app/header.php'; ?>

  <main class="container">
    <div class="dash-topbar">
      <div class="dash-topbar__left">
        <h2 class="page-title">Métricas de Desempenho</h2>
        <p class="dash-subtitle">Atualização automática + botão para forçar a leitura do TOTVS.</p>

        <section class="dashboard-grid">
          <!-- KPI: Meta do mês -->
          <div class="kpi-card">
            <span class="kpi-label">Meta do mês</span>
            <strong class="kpi-value" id="kpi-meta-mes">R$ 0,00</strong>
            <span class="kpi-trend" id="kpi-meta-trend"></span>
            <div class="kpi-detail">
              Realizado: <span id="kpi-realizado-mes">R$ 0,00</span>
              · Falta: <span id="kpi-falta-mes">R$ 0,00</span>
            </div>
          </div>
          <!-- KPI: Dias úteis -->
          <!-- KPI: Faturamento do mês -->
          <div class="kpi-card">
            <span class="kpi-label">Faturamento do mês (atual)</span>

            <!-- VALOR GRANDE -->
            <strong class="kpi-value" id="kpi-mes-atual">R$ 0,00</strong>

            <span class="kpi-trend" id="kpi-dias-trend"></span>

            <div class="kpi-detail">
              <br>
              Faturado: <span id="kpi-mes-fat">R$ 0,00</span>
              · Agendado: <span id="kpi-mes-ag">R$ 0,00</span>
              <br>
              Dias úteis: <span id="kpi-dias">0 / 0</span>
              · Produtividade: <span id="kpi-produtividade">0%</span>
            </div>
          </div>
          <!-- KPI: Deveria ter -->
          <div class="kpi-card">
            <span class="kpi-label">Deveria ter até hoje</span>
            <strong class="kpi-value" id="kpi-deveria">R$ 0,00</strong>
            <span class="kpi-trend" id="kpi-deveria-trend"></span>
            <div class="kpi-detail">
              Atingimento (mês): <span id="kpi-atingimento">0%</span>
            </div>
          </div>



          <!-- KPI: Projeção -->
          <div class="kpi-card">
            <span class="kpi-label">Projeção de fechamento (mês)</span>
            <strong class="kpi-value" id="kpi-projecao-mes">R$ 0,00</strong>
            <span class="kpi-trend" id="kpi-projecao-mes-trend"></span>
            <div class="kpi-detail">Baseado no ritmo atual</div>
          </div>

          <!-- KPI: Hoje -->
          <div class="kpi-card">
            <span class="kpi-label">Hoje (faturado)</span>
            <strong class="kpi-value" id="kpi-hoje-total">R$ 0,00</strong>
            <span class="kpi-trend" id="kpi-hoje-trend"></span>
            <div class="kpi-detail">
              Faturado: <span id="kpi-hoje-fat">R$ 0,00</span>
              · Agendado p/ hoje: <span id="kpi-hoje-ag">R$ 0,00</span>
            </div>
          </div>

          <!-- KPI: Meta dinâmica (card especial) -->
          <div class="kpi-dynamic" role="group" aria-label="Meta dinâmica por dia">
            <div class="kpi-dynamic__value" id="metaDinamica">R$ 0,00</div>
            <div class="kpi-dynamic__label">Meta necessária por dia</div>
            <div class="kpi-dynamic__sub" id="metaRestante">—</div>
            <div class="kpi-dynamic__info">
              <span id="metaTeorica"></span>
              <span id="gapHoje"></span>
            </div>
          </div>

          <!-- Gráficos -->
          <div class="chart-card">
            <h3 class="chart-title" id="titleProgressMonth">Progresso (Mês)</h3>
            <div class="chart-box"><canvas id="salesExpensesChartMonth"></canvas></div>
          </div>

          <div class="chart-card">
            <h3 class="chart-title" id="titleProgressYear">Progresso (Ano)</h3>
            <div class="chart-box"><canvas id="salesExpensesChartYear"></canvas></div>
          </div>

          <div class="chart-card">
            <h3 class="chart-title" id="titlePace">Ritmo (Dia útil)</h3>
            <div class="chart-box"><canvas id="salesBySectorChart"></canvas></div>
          </div>

          <!-- Tabela -->
          <div class="data-table-card grid-col-span-3">
            <h3 class="table-title">Detalhamento (indicador → valor)</h3>
            <div class="table-wrap">
              <table class="table" id="topProductsTable">
                <thead>
                  <tr>
                    <th>Indicador</th>
                    <th class="right">Valor</th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </div>
        </section>
  </main>

  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

  <script>
    window.DASH_CURRENT = <?= json_encode($current_dash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>
  <script src="/assets/js/dashboard.js?v=<?= filemtime(__DIR__ . '/assets/js/dashboard.js') ?>"></script>
</body>

</html>