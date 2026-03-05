<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_login();

// =========================================================
// CONTEXTO (header usa $dashboards e $activePage)
// =========================================================
$u = current_user();
$activePage = 'clientes';

// (se seu header usa $dashboards, carregue ANTES do header)
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

require_once __DIR__ . '/app/header.php';
require_once __DIR__ . '/app/config-totvs.php';

// =========================================================
// MESES (ORDEM FIXA: MAIS ANTIGO -> MAIS RECENTE)
// =========================================================
// meses do ano atual (Jan → Dez)
$months = [];
$year = (int) date('Y');

for ($m = 1; $m <= 12; $m++) {
  $months[] = [
    'ym' => sprintf('%04d-%02d', $year, $m),
    'label_short' => sprintf('%02d/%02d', $m, $year % 100),
  ];
}
?>
<link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
<link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
<link rel="stylesheet" href="/assets/css/carousel.css?v=<?= filemtime(__DIR__ . '/assets/css/carousel.css') ?>" />
<link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" />
<link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
<link rel="stylesheet" href="/assets/css/clientes.css?v=<?= filemtime(__DIR__ . '/assets/css/clientes.css') ?>" />
<link rel="stylesheet" href="/assets/css/loader.css?v=<?= filemtime(__DIR__ . '/assets/css/loader.css') ?>" />

<div class="wrap" id="wrap">
  <!-- Tooltip único (remove duplicado) -->
  <div class="canvasTip" id="canvasTip"></div>

  <script src="/assets/js/loader.js"></script>
  <script>
    (function () {
      try {
        if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
          window.PopperLoading.show('Carregando…', 'Montando dashboard de clientes');
        } else {
          document.addEventListener('DOMContentLoaded', function () {
            if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
              window.PopperLoading.show('Carregando…', 'Montando dashboard de clientes');
            }
          }, { once: true });
        }
      } catch (e) { }
    })();
  </script>

  <div class="pageHead">
    <div class="headTop">
      <div class="title">
        <h1>Dashboard • Clientes >>>>>>> NÃO CONSOLIDADO</h1>
        <div class="meta">
          <span>Atualizado: <strong id="updatedAt">--/--/---- --:--</strong></span>
          <span class="dot">•</span>
          <span>Período: <strong id="periodo">--</strong></span>
          <span class="dot">•</span>
          <span>Cache: <strong id="cacheInfo">10 min</strong></span>
          <span class="dot">•</span>
          <span>Fonte: <strong>TOTVS 000070</strong></span>
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
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M21 12a9 9 0 1 1-2.64-6.36" stroke="rgba(15,23,42,.85)" stroke-width="2" stroke-linecap="round" />
            <path d="M21 3v6h-6" stroke="rgba(15,23,42,.85)" stroke-width="2" stroke-linecap="round"
              stroke-linejoin="round" />
          </svg>
          Atualizar
        </button>
      </div>
    </div>
  </div>

  <div class="err" id="err"></div>

  <!-- Insight Cliente -->
  <div class="card span-12 insight" style="margin-bottom:14px;">
    <div class="cardHead">
      <div>
        <div class="cardTitle">🧠 Insight Cliente</div>
        <div class="cardSub">assistente comercial (auto)</div>
      </div>
      <div class="cardSub" id="insHint">Selecione um cliente</div>
    </div>
    <div class="insTag" id="insTag">—</div>
    <div class="insText" id="insText">Selecione um cliente no dropdown para ver insights automáticos (crescimento,
      margem, desconto e inatividade).</div>
  </div>

  <div class="grid">
    <!-- Top 50 -->
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

    <!-- ABC -->
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

    <!-- Evolução -->
    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Evolução de Vendas</div>
          <div class="cardSub">cliente selecionado</div>
        </div>
        <div class="cardSub" id="evoMeta">—</div>
      </div>
      <canvas class="chart" id="cEvo"></canvas>
    </div>

    <!-- Margem -->
    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Margem por Cliente</div>
          <div class="cardSub">Top 10 margem %</div>
        </div>
      </div>
      <canvas class="chart" id="cMargem"></canvas>
    </div>

    <!-- Frequência -->
    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Frequência de Compra</div>
          <div class="cardSub">intervalo médio</div>
        </div>
      </div>
      <canvas class="chart" id="cFreq"></canvas>
    </div>

    <!-- Score -->
    <div class="card span-4">
      <div class="cardHead">
        <div>
          <div class="cardTitle">Score de Clientes</div>
          <div class="cardSub">Ranking Inteligente</div>
        </div>
      </div>
      <div class="listRank" id="scoreClientesList"></div>
    </div>
  </div>

  <div class="card span-12">
    <div class="cardHead">
      <div>
        <div class="cardTitle">Matriz Clientes</div>
        <div class="cardSub">Faturamento × Margem</div>
      </div>
    </div>
    <canvas class="chart" id="cMatrix"></canvas>
  </div>

  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <!-- Evita duplicar loader.js (já carregado acima) -->
  <script src="/assets/js/clientes.js?v=<?= filemtime(__DIR__ . '/assets/js/clientes.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

</div>