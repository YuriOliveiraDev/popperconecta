<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/header.php';

// mês ativo (default)
$nowY = (int)date('Y');
$nowM = (int)date('m');

$activeYm = ($nowY === 2026)
  ? sprintf('2026-%02d', $nowM)
  : '2026-02';

$meses = [
  1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
  7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];
?>

<link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
<link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
<link rel="stylesheet" href="/assets/css/dashboard-executivo.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard-executivo.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />

  <link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" />
 

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- força a classe no <html> para os overrides do dashboard -->
<script>document.documentElement.classList.add('dashboard-exec');</script>

<main class="container dashboard-exec">
  <!-- IMPORTANTE: usamos uma classe exclusiva do grid do executivo -->
  <section class="dashboard-grid dashboard-grid--exec">

    <!-- Cabeçalho + Presets -->
    <div class="card grid-col-span-3 exec-filter">
      <div class="exec-filter__row">
        <div class="card__header" style="margin:0;">
          <h2 class="card__title">Dashboard Executivo</h2>
          <p class="card__subtitle"><span id="updatedAt">Atualizando...</span></p>
        </div>

        <div class="exec-filter__chips" id="chipsMeses" aria-label="Filtros por mês">
          <?php foreach ($meses as $m => $label): ?>
            <?php $ym = sprintf('2026-%02d', $m); ?>
            <button
              type="button"
              class="chip <?= $ym === $activeYm ? 'is-active' : '' ?>"
              data-ym="<?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?>"
            >
              <?= $label ?>/26
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="exec-filter__period">
        <span class="muted" id="periodLabel">Período: —</span>
      </div>
    </div>

    <!-- KPIs -->
    <div class="kpi-card">
      <span class="kpi-label">Hoje</span>
      <strong class="kpi-value" id="kpiHoje">R$ 0,00</strong>
      <div class="kpi-detail"><span id="kpiNfHoje">0 NF</span></div>
    </div>

    <div class="kpi-card">
      <span class="kpi-label" id="lblPeriodo">Mês (período)</span>
      <strong class="kpi-value" id="kpiMes">R$ 0,00</strong>
      <div class="kpi-detail"><span id="kpiClientesMes">0 clientes</span></div>
    </div>

    <div class="kpi-card">
      <span class="kpi-label">Ano</span>
      <strong class="kpi-value" id="kpiAno">R$ 0,00</strong>
      <div class="kpi-detail"><span id="kpiNfMes">0 NF no mês</span></div>
    </div>

    <!-- Gráfico -->
    <div class="chart-card grid-col-span-3 exec-chart">
      <h3 class="chart-title" id="ttlChart">Faturamento Diário (mês)</h3>
      <div class="chart-box">
        <canvas id="chartDiario"></canvas>
      </div>
    </div>

    <!-- Tops lado a lado -->
    <div class="tops-row grid-col-span-3">
      <div class="data-table-card top-card">
        <div class="top-head">
          <div>
            <h3 class="table-title">Top Produtos</h3>
            <div class="top-sub">(scroll para ver todos)</div>
          </div>
          <span class="top-badge" id="badgeTopProdutos">—</span>
        </div>
        <div class="top-list" id="listTopProdutos"></div>
      </div>

      <div class="data-table-card top-card">
        <div class="top-head">
          <div>
            <h3 class="table-title">Top Vendedores</h3>
            <div class="top-sub">(scroll para ver todos)</div>
          </div>
          <span class="top-badge" id="badgeTopVendedores">—</span>
        </div>
        <div class="top-list" id="listTopVendedores"></div>
      </div>
    </div>
<?php require_once __DIR__ . '/app/footer.php'; ?>
  </section>

  <script>
    window.EXEC_DEFAULT_YM = <?= json_encode($activeYm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <script src="/assets/js/dashboard-executivo.js?v=<?= filemtime(__DIR__ . '/assets/js/dashboard-executivo.js') ?>"></script>
<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>