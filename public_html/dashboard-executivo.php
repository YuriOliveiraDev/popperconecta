<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_login();

// =========================
// CONFIG / HEAD
// =========================
date_default_timezone_set('America/Sao_Paulo');

$page_title = 'Dashboard Executivo';
$html_class = 'dashboard-exec';

// CSS necessários desta página (carrega no <head> via header.php)
$extra_css = [
  '/assets/css/loader.css?v=' . filemtime(__DIR__ . '/assets/css/loader.css'),
  '/assets/css/base.css?v=' . filemtime(__DIR__ . '/assets/css/base.css'),
  '/assets/css/header.css?v=' . filemtime(__DIR__ . '/assets/css/header.css'),
  '/assets/css/dropdowns.css?v=' . filemtime(__DIR__ . '/assets/css/dropdowns.css'),
  '/assets/css/index.css?v=' . filemtime(__DIR__ . '/assets/css/index.css'),
  '/assets/css/dashboard-executivo.css?v=' . filemtime(__DIR__ . '/assets/css/dashboard-executivo.css'),
];

// JS que precisa estar no head (Chart.js)
$extra_js_head = [
  'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
];

require_once __DIR__ . '/app/header.php';

// =========================
// MÊS ATIVO
// =========================
$nowY = (int)date('Y');
$nowM = (int)date('m');

$activeYm = ($nowY === 2026)
  ? sprintf('2026-%02d', $nowM)
  : '2026-02';

if (isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['ym'])) {
  $activeYm = (string)$_GET['ym'];
}

$meses = [
  1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
  7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];
?>

<main class="container dashboard-exec">
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
            <button type="button"
                    class="chip <?= $ym === $activeYm ? 'is-active' : '' ?>"
                    data-ym="<?= htmlspecialchars($ym, ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>/26
            </button>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="exec-filter__period">
        <span class="muted" id="periodLabel">Período: —</span>
      </div>
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
          <div class="top-badge" id="badgeTopProdutos">—</div>
        </div>
        <div class="top-list" id="listTopProdutos"></div>
      </div>

      <div class="data-table-card top-card">
        <div class="top-head">
          <div>
            <h3 class="table-title">Top Vendedores</h3>
            <div class="top-sub">(scroll para ver todos)</div>
          </div>
          <div class="top-badge" id="badgeTopVendedores">—</div>
        </div>
        <div class="top-list" id="listTopVendedores"></div>
      </div>
    </div>

  </section>

  <script>
    window.EXEC_DEFAULT_YM = <?= json_encode($activeYm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  </script>

  <script src="/assets/js/loader.js?v=<?= filemtime(__DIR__ . '/assets/js/loader.js') ?>"></script>
  <script src="/assets/js/dashboard-executivo.js?v=<?= filemtime(__DIR__ . '/assets/js/dashboard-executivo.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>