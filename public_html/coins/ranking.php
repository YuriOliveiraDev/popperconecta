<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

require_login();

date_default_timezone_set('America/Sao_Paulo');

$u = current_user();
$activePage = 'poppercoins';
$page_title = 'Ranking — Popper Coins';
$html_class = 'page poppercoins-ranking';

try {
    $dashboards = db()->query("
        SELECT slug, name, icon
        FROM dashboards
        WHERE is_active = TRUE
        ORDER BY sort_order ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dashboards = null;
}

$extra_css = [
    '/assets/css/base.css?v=' . @filemtime(APP_ROOT . '/assets/css/base.css'),
    '/assets/css/header.css?v=' . @filemtime(APP_ROOT . '/assets/css/header.css'),
    '/assets/css/ranking_coins.css?v=' . @filemtime(APP_ROOT . '/assets/css/ranking_coins.css'),
];

require_once APP_ROOT . '/app/layout/header.php';
?>

<main class="container container--wide">
  <div class="coins-shell">
    <section class="hero">
      <div class="hero-top">
        <div>
          <h1 class="hero-title">Ranking — Popper Coins</h1>
          <p class="hero-sub">Acompanhe o desempenho dos colaboradores e gestores em tempo real.</p>
        </div>
        <div class="hero-badge" id="heroModeBadge">Modo: Acumulado</div>
      </div>

      <div class="kpis">
        <div class="kpi">
          <div class="kpi-label">Total de coins</div>
          <div class="kpi-value" id="totalCoins">0</div>
          <div class="kpi-sub">Saldo geral conforme filtros</div>
        </div>
        <div class="kpi">
          <div class="kpi-label">Participantes</div>
          <div class="kpi-value" id="kpiPeople">0</div>
          <div class="kpi-sub">Pessoas exibidas no ranking</div>
        </div>
        <div class="kpi">
          <div class="kpi-label">Maior saldo</div>
          <div class="kpi-value" id="kpiTopCoins">0</div>
          <div class="kpi-sub" id="kpiTopName">—</div>
        </div>
        <div class="kpi">
          <div class="kpi-label">Média por pessoa</div>
          <div class="kpi-value" id="kpiAverage">0</div>
          <div class="kpi-sub">Baseada no resultado atual</div>
        </div>
      </div>
    </section>

    <section class="toolbar">
      <div class="toolbar-row">
        <div class="toolbar-left">
          <div class="search-wrap">
            <span class="search-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" width="18" height="18">
                <path fill="currentColor" d="M10.5 3a7.5 7.5 0 1 1 0 15a7.5 7.5 0 0 1 0-15Zm0 2a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11Zm8.85 12.44l2.86 2.85a1 1 0 0 1-1.42 1.42l-2.85-2.86a1 1 0 0 1 1.41-1.41Z"/>
              </svg>
            </span>
            <input class="search-input" id="filterInput" placeholder="Buscar por nome..." />
            <button class="clear-search" id="clearSearchBtn" type="button" aria-label="Limpar busca">×</button>
          </div>

          <div class="segmented" aria-label="Modo de período">
            <button class="seg-btn is-active" id="tab-all" data-mode="all" type="button">Acumulado</button>
            <button class="seg-btn" id="tab-month" data-mode="month" type="button">Mês atual</button>
          </div>
        </div>

        <div class="toolbar-right">
          <div class="control">
            <label for="sector">Setor</label>
            <select class="select" id="sector">
              <option value="">Todos</option>
            </select>
          </div>

          <div class="control">
            <label for="sortBy">Ordenar por</label>
            <select class="select" id="sortBy">
              <option value="coins_desc">Maior saldo</option>
              <option value="coins_asc">Menor saldo</option>
              <option value="name_asc">Nome A-Z</option>
              <option value="name_desc">Nome Z-A</option>
            </select>
          </div>

          <div class="control">
            <label for="pageSize">Por página</label>
            <select class="select" id="pageSize">
              <option value="12">12</option>
              <option value="15">15</option>
              <option value="18">18</option>
            </select>
          </div>
        </div>
      </div>
    </section>

    <div class="coins-page">
      <section class="cards-panel">
        <div class="panel-head">
          <div class="panel-title-wrap">
            <h2 class="panel-title">Colaboradores</h2>
            <div class="panel-subtitle" id="cardsSummary">Exibindo 0 pessoas</div>
          </div>
          <div class="panel-chip" id="cardsModeChip">Acumulado</div>
        </div>

        <div class="cards-carousel">
          <div class="cards" id="cardsContainer">
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
            <div class="skeleton-card"></div>
          </div>

          <div class="cards-nav" id="cardsNav" style="display:none;">
            <button class="nav-btn" id="prevPageBtn" type="button" aria-label="Página anterior">&larr;</button>
            <div class="page-ind" id="pageIndicator">1 / 1</div>
            <button class="nav-btn" id="nextPageBtn" type="button" aria-label="Próxima página">&rarr;</button>
          </div>
        </div>
      </section>

      <aside class="ranking">
        <div class="ranking-head">
          <h3 class="ranking-title">Ranking do Mês</h3>
          <div class="ranking-meta">Top 10</div>
        </div>

        <div class="ranking-body">
          <div class="rank-list" id="rankingList">
            <div class="empty">Carregando…</div>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>

<script src="/assets/js/header.js?v=<?= @filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/ranking_coins.js?v=<?= @filemtime(APP_ROOT . '/assets/js/ranking_coins.js') ?>"></script>



<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>