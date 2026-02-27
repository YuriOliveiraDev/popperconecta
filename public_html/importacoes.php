<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// ✅ Essencial para o header.php funcionar
$u = current_user();
$activePage = 'comex';

// ✅ Dropdown "Dashboards" no header
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>COMEX • Importações</title>

  <!-- CSS global -->
  <link rel="stylesheet" href="/assets/css/loader.css?v=<?= @filemtime(__DIR__ . '/assets/css/loader.css') ?: time() ?>">
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= @filemtime(__DIR__ . '/assets/css/base.css') ?: time() ?>">
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= @filemtime(__DIR__ . '/assets/css/dropdowns.css') ?: time() ?>">
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= @filemtime(__DIR__ . '/assets/css/header.css') ?: time() ?>">

  <!-- CSS específico da tela -->
  <link rel="stylesheet" href="/assets/css/importacoes.css?v=<?= @filemtime(__DIR__ . '/assets/css/importacoes.css') ?: time() ?>">
</head>

<body class="page comex-importacoes comex-theme-light">
  <?php require_once __DIR__ . '/app/header.php'; ?>

  <main class="container comex">
    <header class="page__head">
      <div class="page__title">
        <h1>Importações (COMEX)</h1>
        <p>Consulta rápida de processos: fase, ETD e ETA.</p>
      </div>

      <div class="page__actions">
        <button class="btn btn--ghost" id="btnReload" type="button" title="Recarregar">⟳</button>
        <button class="btn btn--primary" id="btnForce" type="button" title="Recarregar ignorando cache">⚡</button>
      </div>
    </header>

    <section class="toolbar">
      <div class="toolbar__left">
        <div class="field">
          <label for="q">Buscar</label>
          <input id="q" type="search" placeholder="Ex.: PO02.2024, NEON, LATAS…" autocomplete="off"/>
        </div>

        <div class="field">
          <label for="fase">Fase</label>
          <select id="fase"><option value="">Todas</option></select>
        </div>

        <div class="field">
          <label for="ordem">Ordenar</label>
          <select id="ordem">
            <option value="eta_asc">ETA (menor → maior)</option>
            <option value="eta_desc">ETA (maior → menor)</option>
            <option value="etd_asc">ETD (menor → maior)</option>
            <option value="etd_desc">ETD (maior → menor)</option>
            <option value="nome_asc">Nome (A → Z)</option>
            <option value="nome_desc">Nome (Z → A)</option>
          </select>
        </div>
      </div>

      <div class="toolbar__right">
        <div class="chips">
          <button class="chip is-active" data-range="all" type="button">Todas</button>
          <button class="chip" data-range="next30" type="button">Próx. 30 dias (ETA)</button>
          <button class="chip" data-range="late" type="button">Atrasadas (ETA)</button>
        </div>
      </div>
    </section>

    <section class="kpis" id="kpis">
      <div class="kpi">
        <div class="kpi__label">Total</div>
        <div class="kpi__value" id="kTotal">—</div>
      </div>
      <div class="kpi">
        <div class="kpi__label">Próx. 30 dias</div>
        <div class="kpi__value" id="kNext30">—</div>
      </div>
      <div class="kpi">
        <div class="kpi__label">Atrasadas</div>
        <div class="kpi__value" id="kLate">—</div>
      </div>
      <div class="kpi">
        <div class="kpi__label">Última atualização</div>
        <div class="kpi__value kpi__value--small" id="kUpdated">—</div>
      </div>
    </section>

    <section class="content">
      <div class="board" id="board" aria-label="Quadro de status"></div>

      <div class="empty" id="empty" hidden>
        <div class="empty__title">Nada por aqui</div>
        <div class="empty__sub">Tente ajustar filtros ou busca.</div>
      </div>
    </section>
  </main>

  <!-- Modal -->
  <div class="modal" id="modal" aria-hidden="true">
    <div class="modal__backdrop" data-close="1"></div>

    <div class="modal__card" role="dialog" aria-modal="true" aria-labelledby="mTitle">
      <div class="modal__head">
        <div>
          <div class="modal__kicker">Detalhes</div>
          <div class="modal__title" id="mTitle">—</div>
        </div>
        <button class="iconbtn" type="button" data-close="1" aria-label="Fechar">✕</button>
      </div>

      <div class="modal__body">
        <div class="detailgrid">
          <div class="detail">
            <div class="detail__label">Fase</div>
            <div class="detail__value" id="mFase">—</div>
          </div>
          <div class="detail">
            <div class="detail__label">ETD</div>
            <div class="detail__value" id="mETD">—</div>
          </div>
          <div class="detail">
            <div class="detail__label">ETA</div>
            <div class="detail__value" id="mETA">—</div>
          </div>
          <div class="detail">
            <div class="detail__label">Card ID</div>
            <div class="detail__value mono" id="mId">—</div>
          </div>
        </div>
      </div>

      <div class="modal__foot">
        <button class="btn btn--ghost" type="button" data-close="1">Fechar</button>
      </div>
    </div>
  </div>

  <script>
    window.COMEX_API_URL = "/api/comex-importacoes.php";
  </script>

  <script src="/assets/js/header.js?v=<?= @filemtime(__DIR__ . '/assets/js/header.js') ?: time() ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= @filemtime(__DIR__ . '/assets/js/dropdowns.js') ?: time() ?>"></script>
  <script src="/assets/js/comex-importacoes.js?v=<?= @filemtime(__DIR__ . '/assets/js/comex-importacoes.js') ?: time() ?>"></script>
</body>
</html>