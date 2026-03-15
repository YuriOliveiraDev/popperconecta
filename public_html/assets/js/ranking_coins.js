
  const USE_DEFAULT_AVATAR_ICON = true;

  function defaultAvatarIcon(size = 56) {
    return `
      <svg viewBox="0 0 24 24" width="${size}" height="${size}" aria-hidden="true" focusable="false">
        <path fill="#94a3b8" d="M12 12a4.25 4.25 0 1 0 0-8.5A4.25 4.25 0 0 0 12 12Zm0 2c-4.42 0-8 2.24-8 5v.75c0 .41.34.75.75.75h14.5c.41 0 .75-.34.75-.75V19c0-2.76-3.58-5-8-5Z"/>
      </svg>
    `;
  }

  (function () {
    const STORAGE_KEY = 'poppercoins-ranking-ui-v2';

    let mode = 'all';
    let sector = '';
    let q = '';
    let sortBy = 'coins_desc';
    let cardsPerPage = 12;

    let allItems = [];
    let page = 1;
    let totalPages = 1;

    const tabAll = document.getElementById('tab-all');
    const tabMonth = document.getElementById('tab-month');
    const sectorEl = document.getElementById('sector');
    const filterInput = document.getElementById('filterInput');
    const clearSearchBtn = document.getElementById('clearSearchBtn');
    const sortByEl = document.getElementById('sortBy');
    const pageSizeEl = document.getElementById('pageSize');

    const totalCoinsEl = document.getElementById('totalCoins');
    const kpiPeopleEl = document.getElementById('kpiPeople');
    const kpiTopCoinsEl = document.getElementById('kpiTopCoins');
    const kpiTopNameEl = document.getElementById('kpiTopName');
    const kpiAverageEl = document.getElementById('kpiAverage');

    const heroModeBadge = document.getElementById('heroModeBadge');
    const cardsModeChip = document.getElementById('cardsModeChip');
    const cardsSummary = document.getElementById('cardsSummary');

    const cardsContainer = document.getElementById('cardsContainer');
    const cardsNav = document.getElementById('cardsNav');
    const prevPageBtn = document.getElementById('prevPageBtn');
    const nextPageBtn = document.getElementById('nextPageBtn');
    const pageIndicator = document.getElementById('pageIndicator');

    const rankingList = document.getElementById('rankingList');

    function esc(s) {
      return String(s ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]
      ));
    }

    function num(v) {
      const n = Number(v);
      return Number.isFinite(n) ? n : 0;
    }

    function formatInt(v) {
      return new Intl.NumberFormat('pt-BR').format(num(v));
    }

    function initials(name) {
      const n = String(name || '').trim();
      if (!n) return 'U';
      const parts = n.split(/\s+/);
      const a = parts[0]?.[0] || 'U';
      const b = (parts.length > 1 ? parts[parts.length - 1][0] : '') || '';
      return (a + b).toUpperCase();
    }

    function simpleName(full) {
      const n = String(full || '').trim().replace(/\s+/g, ' ');
      if (!n) return '';
      const parts = n.split(' ');
      if (parts.length === 1) return parts[0];
      return parts[0] + ' ' + parts[parts.length - 1];
    }

    function currentModeLabel() {
      return mode === 'month' ? 'Mês atual' : 'Acumulado';
    }

    function setActiveTabs() {
      tabAll.classList.toggle('is-active', mode === 'all');
      tabMonth.classList.toggle('is-active', mode === 'month');
      heroModeBadge.textContent = 'Modo: ' + currentModeLabel();
      cardsModeChip.textContent = currentModeLabel();
    }

    function updateSearchClearButton() {
      clearSearchBtn.classList.toggle('show', filterInput.value.trim().length > 0);
    }

    function saveState() {
      const state = { mode, sector, q, sortBy, cardsPerPage };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    }

    function loadState() {
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const s = JSON.parse(raw);

        if (s.mode === 'all' || s.mode === 'month') mode = s.mode;
        if (typeof s.sector === 'string') sector = s.sector;
        if (typeof s.q === 'string') q = s.q;
        if (['coins_desc', 'coins_asc', 'name_asc', 'name_desc'].includes(s.sortBy)) sortBy = s.sortBy;
        if ([12, 15, 18].includes(Number(s.cardsPerPage))) cardsPerPage = Number(s.cardsPerPage);
      } catch (e) {}
    }

    async function loadSectors() {
      const res = await fetch('/api/sectors.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data || !data.ok || !Array.isArray(data.sectors)) return;

      const seen = new Set();
      data.sectors.forEach((s) => {
        const name = String(s || '').trim();
        if (!name || seen.has(name)) return;
        seen.add(name);

        const opt = document.createElement('option');
        opt.value = name;
        opt.textContent = name;
        sectorEl.appendChild(opt);
      });

      if (sector) sectorEl.value = sector;
    }

    async function loadTotal() {
      const qs = new URLSearchParams({ mode, sector });
      const res = await fetch('/api/coins/popper-coins-total.php?' + qs.toString(), { cache: 'no-store' });
      const data = await res.json();

      if (data && data.ok) {
        totalCoinsEl.textContent = formatInt(data.total ?? 0);
      } else {
        totalCoinsEl.textContent = '0';
      }
    }

    async function loadAllRanking() {
      const qs = new URLSearchParams({ mode, sector, q });
      const res = await fetch('/api/coins/popper-coins-ranking.php?' + qs.toString(), { cache: 'no-store' });
      const data = await res.json();
      if (!data || !data.ok || !Array.isArray(data.items)) return { items: [] };
      return data;
    }

    function normalizeItem(it, idx) {
      return {
        position: num(it.position) || (idx + 1),
        name: String(it.name || '').trim(),
        coins: num(it.coins),
        avatar: String(it.avatar || '').trim(),
        sector: String(it.sector || it.department || '').trim()
      };
    }

    function sortItems(items) {
      const arr = [...items];
      arr.sort((a, b) => {
        if (sortBy === 'coins_asc') return a.coins - b.coins || a.name.localeCompare(b.name, 'pt-BR');
        if (sortBy === 'name_asc') return a.name.localeCompare(b.name, 'pt-BR') || (b.coins - a.coins);
        if (sortBy === 'name_desc') return b.name.localeCompare(a.name, 'pt-BR') || (b.coins - a.coins);
        return b.coins - a.coins || a.name.localeCompare(b.name, 'pt-BR');
      });

      return arr.map((item, index) => ({
        ...item,
        position: index + 1
      }));
    }

    function medalForIndex(idx) {
      if (idx === 0) return '🥇';
      if (idx === 1) return '🥈';
      if (idx === 2) return '🥉';
      return null;
    }

    function rankClass(idx) {
      if (idx === 0) return 'is-top1';
      if (idx === 1) return 'is-top2';
      if (idx === 2) return 'is-top3';
      return '';
    }

    function avatarHtml(avatar, name, size = 56) {
      const nm = simpleName(name);
      const icon = defaultAvatarIcon(size).replace(/'/g, "\\'");
      if (avatar) {
        return `<img src="${esc(avatar)}" alt="Foto de ${esc(nm)}"
          onerror="this.remove(); this.parentNode.innerHTML='${USE_DEFAULT_AVATAR_ICON ? icon : esc(initials(nm))}';" />`;
      }
      return USE_DEFAULT_AVATAR_ICON ? defaultAvatarIcon(size) : esc(initials(nm));
    }

    function showCardsLoading() {
      cardsContainer.innerHTML = `
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
        <div class="skeleton-card"></div>
      `;
      cardsNav.style.display = 'none';
    }

    function showRankingLoading() {
      rankingList.innerHTML = `
        <div style="display:flex;flex-direction:column;gap:14px;padding:8px 0;">
          <div class="skeleton-line" style="height:48px"></div>
          <div class="skeleton-line" style="height:48px"></div>
          <div class="skeleton-line" style="height:48px"></div>
          <div class="skeleton-line" style="height:48px"></div>
          <div class="skeleton-line" style="height:48px"></div>
        </div>
      `;
    }

    function renderKPIs(items) {
      const count = items.length;
      const total = items.reduce((acc, it) => acc + num(it.coins), 0);
      const top = items[0] || null;
      const avg = count > 0 ? Math.round(total / count) : 0;

      kpiPeopleEl.textContent = formatInt(count);
      kpiTopCoinsEl.textContent = formatInt(top ? top.coins : 0);
      kpiTopNameEl.textContent = top ? simpleName(top.name) : '—';
      kpiAverageEl.textContent = formatInt(avg);

      cardsSummary.textContent = `Exibindo ${formatInt(count)} pessoa${count === 1 ? '' : 's'}`;
    }

    function animateCardsSwap(doRender) {
      cardsContainer.classList.add('is-animating', 'page-leave');

      window.setTimeout(() => {
        cardsContainer.classList.remove('page-leave');
        doRender();
        cardsContainer.classList.add('page-enter');

        window.setTimeout(() => {
          cardsContainer.classList.remove('page-enter', 'is-animating');
        }, 230);
      }, 170);
    }

    function renderCardsPage(animate = true) {
      if (!allItems || allItems.length === 0) {
        cardsContainer.innerHTML = `
          <div class="empty-box" style="grid-column:1/-1;">
            <svg viewBox="0 0 24 24" width="52" height="52" aria-hidden="true">
              <path fill="#94a3b8" d="M10.5 3a7.5 7.5 0 1 1 0 15a7.5 7.5 0 0 1 0-15Zm0 2a5.5 5.5 0 1 0 0 11a5.5 5.5 0 0 0 0-11Zm8.85 12.44l2.86 2.85a1 1 0 0 1-1.42 1.42l-2.85-2.86a1 1 0 0 1 1.41-1.41Z"/>
            </svg>
            <div>Nenhum resultado encontrado.</div>
            <div style="font-size:13px;font-weight:600;">Tente alterar o setor, o modo ou o termo da busca.</div>
          </div>
        `;
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

        chunk.forEach((it, idx) => {
          const nm = simpleName(it.name);
          const absoluteIndex = start + idx;
          const card = document.createElement('div');
          card.className = 'card';

          const posClass = rankClass(absoluteIndex);
          const badgeClass = posClass ? `card-rank ${posClass}` : 'card-rank';

          card.innerHTML = `
            <div class="${badgeClass}">#${absoluteIndex + 1}</div>
            <div class="avatar">${avatarHtml(it.avatar, it.name, 56)}</div>
            <div class="card-name" title="${esc(it.name)}">${esc(nm)}</div>
            <div class="card-sector" title="${esc(it.sector || '')}">${esc(it.sector || 'Sem setor')}</div>
            <div class="coin-pill">🪙 ${formatInt(it.coins)}</div>
          `;

          cardsContainer.appendChild(card);
        });

        cardsNav.style.display = totalPages > 1 ? 'flex' : 'none';
        prevPageBtn.disabled = (page <= 1);
        nextPageBtn.disabled = (page >= totalPages);
        pageIndicator.textContent = `${page} / ${totalPages}`;
      };

      if (!animate) doRender();
      else animateCardsSwap(doRender);
    }

    function renderRanking(items) {
      if (!items || items.length === 0) {
        rankingList.innerHTML = `
          <div class="empty-box">
            <svg viewBox="0 0 24 24" width="52" height="52" aria-hidden="true">
              <path fill="#94a3b8" d="M19 3H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3.59L12 20.41L15.41 17H19a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2Z"/>
            </svg>
            <div>Sem dados para o ranking.</div>
          </div>
        `;
        return;
      }

      const topCoins = Math.max(...items.map(x => num(x.coins)), 0) || 1;
      rankingList.innerHTML = '';

      items.slice(0, 10).forEach((it, idx) => {
        const pct = Math.max(0, Math.min(100, (num(it.coins) / topCoins) * 100));
        const medal = medalForIndex(idx);
        const nm = simpleName(it.name);
        const row = document.createElement('div');
        row.className = 'rank-item';

        const leftHtml = medal
          ? `<div class="trophy-medal" title="${esc(it.position)}º">${medal}</div>`
          : `<div class="badge" title="${esc(it.position)}º">${esc(it.position)}</div>`;

        row.innerHTML = `
          ${leftHtml}
          <div class="rank-avatar">${avatarHtml(it.avatar, it.name, 28)}</div>
          <div style="min-width:0">
            <div class="rank-name" title="${esc(it.name)}">${esc(nm)}</div>
            <div class="bar"><span style="width:${pct.toFixed(0)}%"></span></div>
          </div>
          <div class="rank-coins">${formatInt(it.coins)}</div>
        `;

        rankingList.appendChild(row);
      });
    }

    async function refresh() {
      saveState();
      setActiveTabs();
      updateSearchClearButton();

      showCardsLoading();
      showRankingLoading();
      page = 1;

      await loadTotal();

      const data = await loadAllRanking();
      const normalized = (data.items || []).map(normalizeItem);
      allItems = sortItems(normalized);

      renderKPIs(allItems);
      renderCardsPage(false);
      renderRanking(allItems);
    }

    function applyStateToControls() {
      filterInput.value = q;
      sortByEl.value = sortBy;
      pageSizeEl.value = String(cardsPerPage);
      sectorEl.value = sector;
      setActiveTabs();
      updateSearchClearButton();
    }

    tabAll.addEventListener('click', () => {
      mode = 'all';
      refresh();
    });

    tabMonth.addEventListener('click', () => {
      mode = 'month';
      refresh();
    });

    sectorEl.addEventListener('change', () => {
      sector = sectorEl.value;
      refresh();
    });

    sortByEl.addEventListener('change', () => {
      sortBy = sortByEl.value;
      allItems = sortItems(allItems);
      renderKPIs(allItems);
      page = 1;
      renderCardsPage(false);
      renderRanking(allItems);
      saveState();
    });

    pageSizeEl.addEventListener('change', () => {
      cardsPerPage = Number(pageSizeEl.value) || 12;
      page = 1;
      renderCardsPage(false);
      saveState();
    });

    let debounce = null;
    filterInput.addEventListener('input', () => {
      q = filterInput.value.trim();
      updateSearchClearButton();
      clearTimeout(debounce);
      debounce = setTimeout(refresh, 250);
    });

    filterInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        q = filterInput.value.trim();
        clearTimeout(debounce);
        refresh();
      }
    });

    clearSearchBtn.addEventListener('click', () => {
      filterInput.value = '';
      q = '';
      updateSearchClearButton();
      refresh();
      filterInput.focus();
    });

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

    loadState();
    setActiveTabs();

    loadSectors().then(() => {
      applyStateToControls();
      refresh();
    });
  })();
