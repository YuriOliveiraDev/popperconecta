<?php
declare(strict_types=1);

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$activePage = 'poppercoins';
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Ranking — Popper Coins</title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />

  <style>
    /* Container wide só nesta página */
    .container.container--wide{max-width:1400px}
    @media (min-width: 1500px){.container.container--wide{max-width:1560px}}

    /* Topo */
    .coins-top{display:flex;align-items:flex-end;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:14px}
    .filter-wrap{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
    .filter-label{font-weight:600;color:#111827}
    .filter-input{height:38px;min-width:280px;border:1px solid #e5e7eb;border-radius:10px;padding:0 12px;outline:none;background:#fff}
    .tabs{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
    .tab{height:36px;padding:0 14px;border:1px solid #e5e7eb;border-radius:10px;background:#fff;font-weight:600;cursor:pointer}
    .tab.is-active{background:#f3f4f6;border-color:#d1d5db}
    .filters{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
    .muted{color:rgba(15,23,42,.55);font-size:12px;font-weight:600}
    .select{border:1px solid rgba(15,23,42,.15);border-radius:10px;padding:8px 10px;background:#fff;height:38px}

    .total-wrap{display:flex;align-items:center;gap:10px}
    .total-label{font-size:12px;color:#6b7280;font-weight:600}
    .total-value{font-size:34px;font-weight:800;line-height:1;color:#111827}

    /* Layout */
    .coins-page{
      display:grid;
      grid-template-columns:minmax(0, 1fr) 420px;
      gap:16px;
      align-items:start;
      padding-bottom:50px;
    }
    @media (max-width: 1100px){.coins-page{grid-template-columns:1fr}}

    /* ESQUERDA: área sem scroll (o grid não rola) */
    .cards-carousel{position:relative}

    /* Cards: 3 por linha */
    .cards{
      display:grid;
      grid-template-columns:repeat(3, minmax(0, 1fr));
      gap:16px;
      overflow:hidden; /* ✅ sem scroll dentro do grid */
    }
    @media (max-width: 980px){.cards{grid-template-columns:repeat(2, minmax(0, 1fr))}}
    @media (max-width: 620px){
      .cards{grid-template-columns:1fr; overflow:visible} /* mobile precisa rolar */
    }

    /* ✅ Card menor (mantém avatar 108) */
    .card{
      background:#fff;
      border:1px solid #eef2f7;
      border-radius:16px;
      padding:10px;              /* era 16 */
      box-shadow:0 6px 18px rgba(17,24,39,.06);
      display:flex;
      flex-direction:column;
      gap:8px;                   /* era 12 */
      min-height:176px;          /* era 220 / 188 */
      overflow:hidden;
    }

    .avatar{
      width:108px;height:108px;border-radius:999px;
      margin:0 auto;
      background:#f8fafc; /* fundo neutro em vez do gradient */
      display:flex;align-items:center;justify-content:center;
      color:#111827;font-weight:800;
      overflow:hidden;
      border:1px solid rgba(15,23,42,.10);
      flex:0 0 auto;
    }
    .avatar img{width:100%;height:100%;object-fit:cover;border-radius:999px;display:block}

    .card-name{
      text-align:center;
      font-weight:600;
      color:#111827;
      font-size:13px;            /* era 15 */
      line-height:1.15;
      min-height:30px;           /* era 38 */
      display:flex;
      align-items:center;
      justify-content:center;
      padding:0 4px;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    .coin-btn{
      display:flex;align-items:center;justify-content:center;
      background:#2c2c7c;color:#fff;border-radius:12px;
      height:36px;               /* era 42 */
      font-weight:600;
      font-size:13px;
      padding:0 10px;
      text-align:center;
      line-height:1;
      white-space:nowrap;
      overflow:hidden;
      text-overflow:ellipsis;
    }

    /* ✅ Animação de troca de página */
    .cards.is-animating{pointer-events:none}
    .cards.page-enter{animation: pageEnter .22s ease both}
    .cards.page-leave{animation: pageLeave .16s ease both}
    @keyframes pageLeave{
      from{opacity:1; transform:translateY(0)}
      to{opacity:0; transform:translateY(6px)}
    }
    @keyframes pageEnter{
      from{opacity:0; transform:translateY(-6px)}
      to{opacity:1; transform:translateY(0)}
    }

    /* Carousel manual (setas) */
    .cards-nav{
      display:flex;
      justify-content:center;
      align-items:center;
      gap:10px;
      margin-top:12px;
      user-select:none;
    }
    .nav-btn{
      height:38px;
      min-width:44px;
      padding:0 12px;
      border:1px solid #e5e7eb;
      border-radius:10px;
      background:#fff;
      font-weight:800;
      cursor:pointer;
    }
    .nav-btn:disabled{opacity:.45;cursor:not-allowed}
    .page-ind{font-weight:700;color:#111827;opacity:.75}

    /* Ranking (direita) */
    .ranking{background:#fff;border:1px solid #eef2f7;border-radius:16px;overflow:hidden;box-shadow:0 6px 18px rgba(17,24,39,.06)}
    .ranking-head{background:#9cc434;color:#fff;padding:16px 18px}
    .ranking-title{font-size:20px;font-weight:800;margin:0}
    .ranking-meta{margin-top:4px;font-weight:700;opacity:.95}
    .ranking-body{padding:14px 18px}

    .rank-tabs{display:flex;gap:18px;justify-content:center;margin:10px 0 12px}
    .rank-tab{font-weight:700;color:#111827;opacity:.65;cursor:pointer}
    .rank-tab.is-active{opacity:1;border-bottom:3px solid #111827;padding-bottom:6px}

    .rank-list{overflow-x:hidden}

    .rank-item{
      display:grid;
      grid-template-columns:34px 1fr 110px;
      gap:10px;
      align-items:center;
      padding:10px 0;
      border-bottom:1px solid #f1f5f9;
      min-width:0;
    }
    .rank-item:last-child{border-bottom:none}

    .badge{
      width:28px;height:28px;border-radius:999px;
      display:flex;align-items:center;justify-content:center;
      color:#fff;font-weight:800;font-size:12px;
      background:#8b5cf6;
      flex:0 0 auto;
    }

    .trophy-medal{font-size:20px;line-height:1;width:28px;text-align:center}

    .rank-name{
      font-weight:600;color:#111827;font-size:13px;line-height:1.2;margin-bottom:6px;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
    }
    .bar{height:10px;border-radius:999px;background:#e5e7eb;overflow:hidden}
    .bar > span{display:block;height:100%;background:#22c55e;width:0%}

    .rank-coins{
      display:flex;align-items:center;justify-content:flex-end;
      font-weight:700;color:#111827;
      white-space:nowrap;
      flex:0 0 auto;
    }

    .empty{padding:14px;color:#6b7280;font-weight:700}
  </style>
</head>
<body class="page">
<?php require_once __DIR__ . '/app/header.php'; ?>

<main class="container container--wide">
  <h2 class="page-title">Ranking — Popper Coins</h2>

  <div class="coins-top">
    <div class="filter-wrap">
      <div class="filter-label">Filtrar:</div>
      <input class="filter-input" id="filterInput" placeholder="Buscar por nome..." />

      <div class="tabs">
        <button class="tab is-active" id="tab-all" data-mode="all">Acumulado</button>
        <button class="tab" id="tab-month" data-mode="month">Mês atual</button>
      </div>

      <div class="filters">
        <label class="muted" for="sector">Setor</label>
        <select class="select" id="sector">
          <option value="">Todos</option>
        </select>
      </div>
    </div>

    <div class="total-wrap">
      <div>
        <div class="total-label">Total de Popper Coins</div>
        <div class="total-value" id="totalCoins">0</div>
      </div>
    </div>
  </div>

  <div class="coins-page">
    <!-- ESQUERDA -->
    <section class="cards-carousel">
      <div class="cards" id="cardsContainer">
        <div class="card">
          <div class="avatar">—</div>
          <div class="card-name">Carregando…</div>
          <div class="coin-btn">— Popper Coins</div>
        </div>
      </div>

      <div class="cards-nav" id="cardsNav" style="display:none;">
        <button class="nav-btn" id="prevPageBtn" type="button" aria-label="Página anterior">&larr;</button>
        <div class="page-ind" id="pageIndicator">1 / 1</div>
        <button class="nav-btn" id="nextPageBtn" type="button" aria-label="Próxima página">&rarr;</button>
      </div>
    </section>

    <!-- DIREITA -->
    <aside class="ranking">
      <div class="ranking-head">
        <h3 class="ranking-title">Ranking do Mês</h3>
        <div class="ranking-meta">Top 10</div>
      </div>

      <div class="ranking-body">
        <div class="rank-tabs">
          <div class="rank-tab is-active" id="rankTabColab">Colaboradores</div>
          <div class="rank-tab" id="rankTabGest">Gestores</div>
        </div>

        <div class="rank-list" id="rankingList">
          <div class="empty">Carregando…</div>
        </div>
      </div>
    </aside>
  </div>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>

<script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>

<script>
const USE_DEFAULT_AVATAR_ICON = true; // opcional: true = usa ícone; false = usa o comportamento antigo
function defaultAvatarIcon(){
  return `
    <svg viewBox="0 0 24 24" width="56" height="56" aria-hidden="true" focusable="false">
      <path fill="#94a3b8" d="M12 12a4.25 4.25 0 1 0 0-8.5A4.25 4.25 0 0 0 12 12Zm0 2c-4.42 0-8 2.24-8 5v.75c0 .41.34.75.75.75h14.5c.41 0 .75-.34.75-.75V19c0-2.76-3.58-5-8-5Z"/>
    </svg>
  `;
}
(function(){
  let mode = 'all';
  let sector = '';
  let q = '';
  let rankType = 'colab';

  const cardsPerPage = 9;
  let allItems = [];
  let page = 1;
  let totalPages = 1;

  const tabAll = document.getElementById('tab-all');
  const tabMonth = document.getElementById('tab-month');
  const sectorEl = document.getElementById('sector');
  const filterInput = document.getElementById('filterInput');
  const totalCoinsEl = document.getElementById('totalCoins');

  const cardsContainer = document.getElementById('cardsContainer');
  const cardsNav = document.getElementById('cardsNav');
  const prevPageBtn = document.getElementById('prevPageBtn');
  const nextPageBtn = document.getElementById('nextPageBtn');
  const pageIndicator = document.getElementById('pageIndicator');

  const rankingList = document.getElementById('rankingList');
  const rankTabColab = document.getElementById('rankTabColab');
  const rankTabGest = document.getElementById('rankTabGest');

  function esc(s){
    return String(s).replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function initials(name){
    const n = String(name || '').trim();
    if (!n) return 'U';
    const parts = n.split(/\s+/);
    const a = parts[0]?.[0] || 'U';
    const b = (parts.length > 1 ? parts[parts.length-1][0] : '') || '';
    return (a + b).toUpperCase();
  }

  function simpleName(full){
    const n = String(full || '').trim().replace(/\s+/g, ' ');
    if (!n) return '';
    const parts = n.split(' ');
    if (parts.length === 1) return parts[0];
    return parts[0] + ' ' + parts[parts.length - 1];
  }

  function setActiveTabs(){
    tabAll.classList.toggle('is-active', mode === 'all');
    tabMonth.classList.toggle('is-active', mode === 'month');
  }

  function setActiveRankTabs(){
    rankTabColab.classList.toggle('is-active', rankType === 'colab');
    rankTabGest.classList.toggle('is-active', rankType === 'gest');
  }

  async function loadSectors(){
    const res = await fetch('/api/sectors.php', { cache: 'no-store' });
    const data = await res.json();
    if (!data || !data.ok || !Array.isArray(data.sectors)) return;

    data.sectors.forEach((s) => {
      const opt = document.createElement('option');
      opt.value = s;
      opt.textContent = s;
      sectorEl.appendChild(opt);
    });
  }

  async function loadTotal(){
    const qs = new URLSearchParams({ mode, sector });
    const res = await fetch('/api/popper-coins-total.php?' + qs.toString(), { cache: 'no-store' });
    const data = await res.json();
    if (data && data.ok) totalCoinsEl.textContent = String(data.total ?? 0);
  }

  async function loadAllRanking(){
    const qs = new URLSearchParams({ mode, sector, q, rankType });
    const res = await fetch('/api/popper-coins-ranking.php?' + qs.toString(), { cache: 'no-store' });
    const data = await res.json();
    if (!data || !data.ok || !Array.isArray(data.items)) return { items: [] };
    return data;
  }

  function medalForIndex(idx){
    if (idx === 0) return '🥇';
    if (idx === 1) return '🥈';
    if (idx === 2) return '🥉';
    return null;
  }

  function animateCardsSwap(doRender){
    cardsContainer.classList.add('is-animating','page-leave');

    window.setTimeout(() => {
      cardsContainer.classList.remove('page-leave');
      doRender();
      cardsContainer.classList.add('page-enter');

      window.setTimeout(() => {
        cardsContainer.classList.remove('page-enter','is-animating');
      }, 230);
    }, 170);
  }

  function renderCardsPage(animate = true){
    if (!allItems || allItems.length === 0) {
      cardsContainer.innerHTML = '<div class="empty">Sem dados.</div>';
      cardsNav.style.display = 'none';
      return;
    }

    totalPages = Math.max(1, Math.ceil(allItems.length / cardsPerPage));
    if (page > totalPages) page = totalPages;
    if (page < 1) page = 1;

    const start = (page - 1) * cardsPerPage;
    const chunk = allItems.slice(start, start + cardsPerPage);

    const doRender = () => {
      cardsContainer.innerHTML = '';
      chunk.forEach((it) => {
        const avatar = (it.avatar && String(it.avatar).trim() !== '') ? String(it.avatar).trim() : '';
        const nm = simpleName(it.name);

        const card = document.createElement('div');
        card.className = 'card';

        const icon = defaultAvatarIcon().replace(/'/g, "\'");

        const avatarHtml = avatar
          ? `<img src="${esc(avatar)}" alt="Foto de ${esc(nm)}"
                onerror="this.remove(); this.parentNode.innerHTML='${USE_DEFAULT_AVATAR_ICON ? icon : esc(initials(nm))}';" />`
          : (USE_DEFAULT_AVATAR_ICON ? defaultAvatarIcon() : esc(initials(nm)));

        card.innerHTML = `
          <div class="avatar">${avatarHtml}</div>
          <div class="card-name" title="${esc(it.name)}">${esc(nm)}</div>
          <div class="coin-btn" title="${esc(it.coins)} Popper Coins">${esc(it.coins)} Popper Coins</div>
        `;
        cardsContainer.appendChild(card);
      });

      if (totalPages > 1) cardsNav.style.display = 'flex';
      else cardsNav.style.display = 'none';

      prevPageBtn.disabled = (page <= 1);
      nextPageBtn.disabled = (page >= totalPages);
      pageIndicator.textContent = page + ' / ' + totalPages;
    };

    if (!animate) doRender();
    else animateCardsSwap(doRender);
  }

  function renderRanking(items){
    if (!items || items.length === 0) {
      rankingList.innerHTML = '<div class="empty">Sem dados.</div>';
      return;
    }

    const topCoins = Math.max(...items.map(x => Number(x.coins) || 0), 0) || 1;

    rankingList.innerHTML = '';
    items.slice(0, 10).forEach((it, idx) => {
      const pct = Math.max(0, Math.min(100, ((Number(it.coins)||0) / topCoins) * 100));
      const medal = medalForIndex(idx);
      const nm = simpleName(it.name);

      const row = document.createElement('div');
      row.className = 'rank-item';

      const leftHtml = medal
        ? `<div class="trophy-medal" title="${esc(it.position)}º">${medal}</div>`
        : `<div class="badge" title="${esc(it.position)}º">${esc(it.position)}</div>`;

      row.innerHTML = `
        ${leftHtml}
        <div style="min-width:0">
          <div class="rank-name" title="${esc(it.name)}">${esc(nm)}</div>
          <div class="bar"><span style="width:${pct.toFixed(0)}%"></span></div>
        </div>
        <div class="rank-coins">${esc(it.coins)}</div>
      `;
      rankingList.appendChild(row);
    });
  }

  async function refresh(){
    cardsContainer.innerHTML = '<div class="card"><div class="avatar">—</div><div class="card-name">Carregando…</div><div class="coin-btn">— Popper Coins</div></div>';
    cardsNav.style.display = 'none';
    rankingList.innerHTML = '<div class="empty">Carregando…</div>';
    page = 1;

    await loadTotal();

    const data = await loadAllRanking();
    allItems = data.items || [];

    renderCardsPage(false); /* primeira renderização sem animação */
    renderRanking(allItems);
  }

  tabAll.addEventListener('click', () => { mode = 'all'; setActiveTabs(); refresh(); });
  tabMonth.addEventListener('click', () => { mode = 'month'; setActiveTabs(); refresh(); });
  sectorEl.addEventListener('change', () => { sector = sectorEl.value; refresh(); });

  let t = null;
  filterInput.addEventListener('input', () => {
    q = filterInput.value.trim();
    clearTimeout(t);
    t = setTimeout(refresh, 250);
  });

  rankTabColab.addEventListener('click', () => { rankType = 'colab'; setActiveRankTabs(); refresh(); });
  rankTabGest.addEventListener('click', () => { rankType = 'gest'; setActiveRankTabs(); refresh(); });

  prevPageBtn.addEventListener('click', () => {
    if (page <= 1) return;
    page--;
    renderCardsPage(true);
  });

  nextPageBtn.addEventListener('click', () => {
    if (page >= totalPages) return;
    page++;
    renderCardsPage(true);
  });

  setActiveTabs();
  setActiveRankTabs();
  loadSectors().then(refresh);
})();

</script>
</body>
</html>