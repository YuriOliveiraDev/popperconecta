<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_login();
require_dash_perm('dash.comercial.clientes');

date_default_timezone_set('America/Sao_Paulo');

// =========================================================
// CONTEXTO DA PÁGINA
// =========================================================
$u = current_user();
$activePage = 'clientes';
$page_title = 'Dashboard • Clientes';
$html_class = 'clientes-page';

// =========================================================
// DASHBOARDS (usado pelo header)
// =========================================================
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

// =========================================================
// ASSETS DA PÁGINA
// =========================================================
$extra_css = [
  '/assets/css/base.css?v=' . @filemtime(APP_ROOT . '/assets/css/base.css'),
  '/assets/css/dropdowns.css?v=' . @filemtime(APP_ROOT . '/assets/css/dropdowns.css'),
  '/assets/css/carousel.css?v=' . @filemtime(APP_ROOT . '/assets/css/carousel.css'),
  '/assets/css/index.css?v=' . @filemtime(APP_ROOT . '/assets/css/index.css'),
  '/assets/css/header.css?v=' . @filemtime(APP_ROOT . '/assets/css/header.css'),
  '/assets/css/clientes.css?v=' . @filemtime(APP_ROOT . '/assets/css/clientes.css'),
  '/assets/css/loader.css?v=' . @filemtime(APP_ROOT . '/assets/css/loader.css'),
];

$extra_js_head = [
  'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js',
  '/assets/js/loader.js?v=' . @filemtime(APP_ROOT . '/assets/js/loader.js'),
];

// =========================================================
// CONFIGS
// =========================================================
require_once APP_ROOT . '/app/config/config-totvs.php';
require_once APP_ROOT . '/app/layout/header.php';

// =========================================================
// MESES (ORDEM FIXA: MAIS ANTIGO -> MAIS RECENTE)
// =========================================================
$months = [];
$year = (int) date('Y');
$currentMonth = (int) date('m');

for ($m = 1; $m <= $currentMonth; $m++) {
  $months[] = [
    'ym' => sprintf('%04d-%02d', $year, $m),
    'label_short' => sprintf('%02d/%02d', $m, $year % 100),
  ];
}
?>

<script>
  (function () {
    function openClientesLoader() {
      if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
        window.PopperLoading.show('Carregando…', 'Montando dashboard de clientes');
      }
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', openClientesLoader, { once: true });
    } else {
      openClientesLoader();
    }
  })();
</script>

<div class="wrap" id="wrap">
  <div class="canvasTip" id="canvasTip"></div>

  <div class="pageHead">
    <div class="headTop">
      <div class="title">
        <h1>Dashboard • Clientes (Em Consolidação)</h1>
        <div class="meta">
          <span>Atualizado: <strong id="updatedAt">--/--/---- --:--</strong></span>
          <span class="dot">•</span>
          <span>Período: <strong id="periodo">--</strong></span>
          <span class="dot">•</span>
        </div>
      </div>

      <div class="monthBar" id="monthBar">
        <?php foreach ($months as $m): ?>
          <button class="pill" data-ym="<?= htmlspecialchars($m['ym'], ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($m['label_short'], ENT_QUOTES, 'UTF-8') ?>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="headBottom">
      <div class="kpiRow">
        <div class="kpi">
          <div class="left">
            <div class="l">Clientes ativos</div>
            <div class="s" id="kpiTop3">Top 3: 0%</div>
          </div>
          <div class="right">
            <div class="v" id="kpiClientes">0</div>
          </div>
        </div>

        <div class="kpi">
          <div class="left">
            <div class="l">Ticket médio (NF)</div>
            <div class="s" id="kpiPedidos">NFs: 0</div>
          </div>
          <div class="right">
            <div class="v" id="kpiTicket">R$ 0,00</div>
          </div>
        </div>

        <div class="kpi">
          <div class="left">
            <div class="l">Margem média / cliente</div>
            <div class="s" id="kpiMargPct">Margem %: 0%</div>
          </div>
          <div class="right">
            <div class="v" id="kpiMargCli">R$ 0,00</div>
          </div>
        </div>

        <div class="kpi">
          <div class="left">
            <div class="l">Desconto médio</div>
            <div class="s" id="kpiAlert">Sem alerta</div>
          </div>
          <div class="right">
            <div class="v" id="kpiDesc">0%</div>
          </div>
        </div>
      </div>

      <div class="rowInline">
        <select class="select" id="clienteSelect" title="Cliente (Top 50)">
          <option value="">Cliente (Top 50)</option>
        </select>

        <button class="btn" id="btnRefresh" title="Atualizar (force=1)">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="rgba(15,23,42,.85)" stroke-width="2" stroke-linecap="round" />
            <path d="M21 3v6h-6" stroke="rgba(15,23,42,.85)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Atualizar
        </button>
      </div>
    </div>
  </div>

  <div class="err" id="err"></div>

  <div class="grid">
    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Top 50 Clientes</div>
          <div class="cardSub">Ranking por faturamento</div>
        </div>
        <div class="cardSub" id="rankMeta">—</div>
      </div>
      <div class="listRank" id="topClientesList"></div>
    </div>

    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Curva ABC (Pareto)</div>
          <div class="cardSub">% acumulado faturamento</div>
        </div>
        <div class="cardSub">A=80% • B=15% • C=5%</div>
      </div>
      <canvas class="chart" id="cABC"></canvas>
    </div>

    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Evolução de Vendas</div>
          <div class="cardSub">cliente selecionado</div>
        </div>
        <div class="cardSub" id="evoMeta">—</div>
      </div>
      <div id="cEvoWrap">
        <canvas class="chart" id="cEvo"></canvas>
      </div>
    </div>

    <div class="card span-8 card-matrix">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Matriz Clientes</div>
          <div class="cardSub">Faturamento × Margem</div>
        </div>
        <div class="cardSub">Volume × rentabilidade</div>
      </div>
      <canvas class="chart chart-matrix" id="cMatrix"></canvas>
    </div>

    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Margem por Cliente</div>
          <div class="cardSub">Top 10 margem %</div>
        </div>
      </div>
      <canvas class="chart" id="cMargem"></canvas>
    </div>
  </div>
</div>

<script src="/assets/js/clientes.js?v=<?= @filemtime(APP_ROOT . '/assets/js/clientes.js') ?>"></script>
<script src="/assets/js/header.js?v=<?= @filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= @filemtime(APP_ROOT . '/assets/js/dropdowns.js') ?>"></script>

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>