<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_login();
require_dash_perm('dash.comercial.faturamento');

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$activePage = 'dashboard';
$current_dash = $_GET['dash'] ?? 'executivo';
$page_title = 'Dashboard - Faturamento';
$html_class = 'faturamento-page page';

try {
    $dashboards = db()
        ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dashboards = null;
}

$extra_css = [
    '/assets/css/loader.css?v=' . filemtime(__DIR__ . '/../assets/css/loader.css'),
    '/assets/css/base.css?v=' . filemtime(__DIR__ . '/../assets/css/base.css'),
    '/assets/css/users.css?v=' . filemtime(__DIR__ . '/../assets/css/users.css'),
    '/assets/css/dashboard.css?v=' . filemtime(__DIR__ . '/../assets/css/dashboard.css'),
    '/assets/css/header.css?v=' . filemtime(__DIR__ . '/../assets/css/header.css'),
];

$extra_js_head = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js',
];

require_once APP_ROOT . '/app/layout/header.php';
?>

<main class="container">
  <div class="dash-topbar">
    <div class="dash-topbar__left">
      <h2 class="page-title">Metricas de Desempenho</h2>
      <p class="dash-subtitle">Atualizacao automatica + botao para forcar a leitura do TOTVS.</p>

      <section class="dashboard-grid">
        <div class="kpi-card">
          <span class="kpi-label">Meta do mes</span>
          <strong class="kpi-value" id="kpi-meta-mes">R$ 0,00</strong>
          <span class="kpi-trend" id="kpi-meta-trend"></span>
          <div class="kpi-detail">
            Realizado: <span id="kpi-realizado-mes">R$ 0,00</span> -
            Falta: <span id="kpi-falta-mes">R$ 0,00</span>
          </div>
        </div>

        <div class="kpi-card">
          <span class="kpi-label">Vendas do mes (atual)</span>
          <strong class="kpi-value" id="kpi-mes-atual">R$ 0,00</strong>
          <span class="kpi-trend" id="kpi-dias-trend"></span>
          <div class="kpi-detail">
            <br>
            Faturado: <span id="kpi-mes-fat">R$ 0,00</span> -
            Imediato: <span id="kpi-mes-ag">R$ 0,00</span><br>
            Agendado: <span id="kpi-mes-ag2">R$ 0,00</span><br>
            Dias uteis: <span id="kpi-dias">0 / 0</span> -
            Produtividade: <span id="kpi-produtividade">0%</span>
          </div>
        </div>

        <div class="kpi-card">
          <span class="kpi-label">Deveria ter ate hoje</span>
          <strong class="kpi-value" id="kpi-deveria">R$ 0,00</strong>
          <span class="kpi-trend" id="kpi-deveria-trend"></span>
          <div class="kpi-detail">
            Atingimento (mes): <span id="kpi-atingimento">0%</span>
          </div>
        </div>

        <div class="kpi-card">
          <span class="kpi-label">Projecao de fechamento (mes)</span>
          <strong class="kpi-value" id="kpi-projecao-mes">R$ 0,00</strong>
          <span class="kpi-trend" id="kpi-projecao-mes-trend"></span>
          <div class="kpi-detail">Baseado no ritmo atual</div>
        </div>

        <div class="kpi-card">
          <span class="kpi-label">Hoje</span>
          <strong class="kpi-value" id="kpi-hoje-total">R$ 0,00</strong>
          <span class="kpi-trend" id="kpi-hoje-trend"></span>
          <div class="kpi-detail">
            Faturado: <span id="kpi-hoje-fat">R$ 0,00</span> -
            Imediato p/ hoje: <span id="kpi-hoje-ag">R$ 0,00</span><br>
            Agendado: <span id="kpi-hoje-ag2">R$ 0,00</span>
          </div>
        </div>

        <div class="kpi-card" role="group" aria-label="Meta necessaria por dia">
          <span class="kpi-label">Meta necessaria por dia</span>
          <strong class="kpi-value" id="metaDinamica">R$ 0,00</strong>
          <span class="kpi-trend" id="metaDinamicaTrend"></span>
          <div class="kpi-detail">
            Restante: <span id="metaRestante">--</span><br>
            Meta teorica: <span id="metaTeorica">--</span> - Gap hoje: <span id="gapHoje">--</span>
          </div>
        </div>

        <div class="chart-card">
          <h3 class="chart-title" id="titleProgressMonth">Progresso (Mes)</h3>
          <div class="chart-box"><canvas id="salesExpensesChartMonth"></canvas></div>
        </div>

        <div class="chart-card">
          <h3 class="chart-title" id="titleProgressYear">Progresso (Ano)</h3>
          <div class="chart-box"><canvas id="salesExpensesChartYear"></canvas></div>
        </div>

        <div class="chart-card">
          <h3 class="chart-title" id="titlePace">Ritmo (Dia util)</h3>
          <div class="chart-box"><canvas id="salesBySectorChart"></canvas></div>
        </div>

        <div class="data-table-card grid-col-span-3">
          <h3 class="table-title">Detalhamento (indicador -> valor)</h3>
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
    </div>
  </div>
</main>

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/../assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/../assets/js/dropdowns.js') ?>"></script>
<script>
  window.DASH_CURRENT = <?= json_encode($current_dash, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/assets/js/loader.js"></script>
<script src="/assets/js/dashboard.js?v=<?= filemtime(__DIR__ . '/../assets/js/dashboard.js') ?>"></script>
