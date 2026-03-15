/* global window */
(() => {
  'use strict';

  const API_URL = window.COMEX_API_URL || '/api/dashboard/comex-importacoes.php';

  function safeOn(el, ev, fn, opts) {
    if (!el) return;
    el.addEventListener(ev, fn, opts);
  }

  const els = {
    q: document.getElementById('q'),
    fase: document.getElementById('fase'),
    ordem: document.getElementById('ordem'),
    board: document.getElementById('board'),
    empty: document.getElementById('empty'),
    btnReload: document.getElementById('btnReload'),
    btnForce: document.getElementById('btnForce'),

    kTotal: document.getElementById('kTotal'),
    kNext30: document.getElementById('kNext30'),
    kLate: document.getElementById('kLate'),
    kUpdated: document.getElementById('kUpdated'),

    modal: document.getElementById('modal'),
    modalCard: document.querySelector('#modal .modal__card'),
    mTitle: document.getElementById('mTitle'),
    mFase: document.getElementById('mFase'),
    mETD: document.getElementById('mETD'),
    mETA: document.getElementById('mETA'),
    mId: document.getElementById('mId'),
  };

  let RAW = [];
  let rangeMode = 'all';

  // =========================
  // LOADER (igual executivo)
  // =========================
  const LOADER_DELAY_MS = 120;
  const LOADER_MIN_MS = 350;

  let _loaderTimer = null;
  let _loaderShownAt = 0;

  function loaderOpen(title, sub) {
    const api = window.PopperLoading;
    if (!api || typeof api.show !== 'function') return;

    if (_loaderTimer) { clearTimeout(_loaderTimer); _loaderTimer = null; }
    _loaderShownAt = 0;

    _loaderTimer = setTimeout(() => {
      _loaderTimer = null;
      _loaderShownAt = Date.now();
      api.show(title || 'Carregando…', sub || '');
    }, LOADER_DELAY_MS);
  }

  function loaderClose() {
    const api = window.PopperLoading;
    if (!api || typeof api.hide !== 'function') return;

    if (_loaderTimer) { clearTimeout(_loaderTimer); _loaderTimer = null; return; }

    if (_loaderShownAt) {
      const elapsed = Date.now() - _loaderShownAt;
      const wait = Math.max(0, LOADER_MIN_MS - elapsed);
      setTimeout(() => api.hide(), wait);
      _loaderShownAt = 0;
      return;
    }

    api.hide();
  }

  async function waitForLoader(maxMs = 800) {
    const start = Date.now();
    while (Date.now() - start < maxMs) {
      if (window.PopperLoading && typeof window.PopperLoading.show === 'function') return true;
      await new Promise((r) => setTimeout(r, 25));
    }
    return false;
  }

  // ---------- helpers ----------
  function setError(title, sub) {
    if (els.board) els.board.innerHTML = '';
    if (!els.empty) return;
    els.empty.hidden = false;
    const t = els.empty.querySelector('.empty__title');
    const s = els.empty.querySelector('.empty__sub');
    if (t) t.textContent = title || 'Falha ao carregar';
    if (s) s.textContent = sub || 'Verifique a API.';
  }

  function escapeHtml(s) {
    return String(s ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function parseBRDate(s) {
    if (!s) return null;
    const str = String(s).trim();
    const m = str.match(/^(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}):(\d{2}))?$/);
    if (!m) return null;
    const dd = Number(m[1]);
    const mm = Number(m[2]) - 1;
    const yyyy = Number(m[3]);
    const HH = m[4] ? Number(m[4]) : 0;
    const II = m[5] ? Number(m[5]) : 0;
    const d = new Date(yyyy, mm, dd, HH, II, 0, 0);
    return Number.isFinite(d.getTime()) ? d : null;
  }

  function uniq(arr) {
    return [...new Set(arr)].filter(Boolean);
  }

  function normalizePhase(phase) {
    return String(phase || '').trim();
  }

  function isConcluded(phase) {
    const p = String(phase || '').toUpperCase();
    return p.includes('CONCLU');
  }

  const FASE_ORDER = [
    'EM PRODUÇÃO',
    'COTAÇÃO FRETE INTERNACIONAL',
    'EMBARQUE',
    'EM TRÂNSITO',
    'NO PORTO',
    'DESEMBARAÇO',
    'ENTREGA',
    'CONCLUÍDO',
  ];

  function orderPhases(phasesRaw) {
    const list = uniq((phasesRaw || []).map(normalizePhase));
    const ordered = FASE_ORDER.filter((p) => list.includes(p));
    const extras = list
      .filter((p) => !FASE_ORDER.includes(p))
      .sort((a, b) => String(a).localeCompare(String(b), 'pt-BR'));
    return [...ordered, ...extras];
  }

  function phaseBadge(phase) {
    const raw = normalizePhase(phase);
    const p = raw.toUpperCase();

    if (p.includes('CONCLU')) return ['badge badge--good', 'Concluído'];
    if (p.includes('ATRAS')) return ['badge badge--bad', 'Atrasado'];
    if (p.includes('TRÂNS') || p.includes('TRANS')) return ['badge badge--warn', raw || 'Em trânsito'];
    if (!p) return ['badge badge--neutral', '—'];
    return ['badge badge--neutral', raw];
  }

  function isLate(it, now = new Date()) {
    if (isConcluded(it?.fase)) return false;
    const eta = parseBRDate(it?.previsao_entrega_eta);
    return !!eta && eta < now;
  }

  function computeKPIs(items) {
    if (!els.kTotal) return;

    const now = new Date();
    const in30 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 30);

    let next30 = 0;
    let late = 0;

    for (const it of items) {
      if (isConcluded(it?.fase)) continue;
      const eta = parseBRDate(it?.previsao_entrega_eta);
      if (!eta) continue;

      if (eta < now) late++;
      else if (eta <= in30) next30++;
    }

    els.kTotal.textContent = String(items.length);
    if (els.kNext30) els.kNext30.textContent = String(next30);
    if (els.kLate) els.kLate.textContent = String(late);
  }

  function itemCardTemplate(it) {
    const [badgeCls, badgeText] = phaseBadge(it?.fase);
    const etd = it?.previsao_embarque_etd || '—';
    const eta = it?.previsao_entrega_eta || '—';
    const name = it?.container || it?.titulo || '—';
    const id = it?.card_id || '';

    const late = isLate(it);
    const lateTag = late ? `<span class="tag tag--late">Atrasado</span>` : '';

    return `
      <div class="itcard" data-open="${escapeHtml(id)}" role="button" tabindex="0" aria-label="Abrir detalhes">
        <div class="itcard__top">
          <div class="itcard__title">${escapeHtml(name)}</div>
          ${lateTag}
        </div>

        <div class="itcard__meta">
          <span class="${escapeHtml(badgeCls)}">${escapeHtml(badgeText)}</span>
          <span class="itcard__dates">
            <span class="itcard__dt"><b>ETD</b> ${escapeHtml(etd)}</span>
            <span class="itcard__dt"><b>ETA</b> ${escapeHtml(eta)}</span>
          </span>
        </div>
      </div>
    `;
  }

  function render(items) {
    if (!els.board) return;

    const map = new Map();
    for (const it of items) {
      const key = normalizePhase(it?.fase) || '—';
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(it);
    }

    const phases = orderPhases([...map.keys()]);

    els.board.innerHTML = phases.map((phase) => {
      const list = map.get(phase) || [];
      const lateCount = list.reduce((acc, it) => acc + (isLate(it) ? 1 : 0), 0);

      const headBadges = `
        <span class="pill">${list.length}</span>
        ${lateCount > 0 ? `<span class="pill pill--late">${lateCount} atras.</span>` : ''}
      `;

      return `
        <section class="statuscard">
          <header class="statuscard__head">
            <div class="statuscard__title">${escapeHtml(phase)}</div>
            <div class="statuscard__badges">${headBadges}</div>
          </header>

          <div class="statuscard__list">
            ${list.map(itemCardTemplate).join('')}
          </div>
        </section>
      `;
    }).join('');

    if (els.empty) els.empty.hidden = items.length > 0;
  }

  function applyFilters() {
    if (!els.q || !els.fase || !els.ordem) return;

    const q = (els.q.value || '').trim().toLowerCase();
    const f = (els.fase.value || '').trim();
    let items = RAW.slice();

    const now = new Date();
    const in30 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 30);

    if (rangeMode === 'next30') {
      items = items.filter((it) => {
        if (isConcluded(it?.fase)) return false;
        const eta = parseBRDate(it?.previsao_entrega_eta);
        return eta && eta >= now && eta <= in30;
      });
    } else if (rangeMode === 'late') {
      items = items.filter((it) => {
        if (isConcluded(it?.fase)) return false;
        const eta = parseBRDate(it?.previsao_entrega_eta);
        return eta && eta < now;
      });
    }

    if (f) items = items.filter((it) => String(it?.fase || '') === f);

    if (q) {
      items = items.filter((it) => {
        const s = `${it?.container || ''} ${it?.fase || ''} ${it?.previsao_embarque_etd || ''} ${it?.previsao_entrega_eta || ''}`.toLowerCase();
        return s.includes(q);
      });
    }

    const ord = els.ordem.value || 'eta_asc';
    const getEta = (it) => parseBRDate(it?.previsao_entrega_eta)?.getTime() ?? Number.POSITIVE_INFINITY;
    const getEtd = (it) => parseBRDate(it?.previsao_embarque_etd)?.getTime() ?? Number.POSITIVE_INFINITY;
    const getNome = (it) => String(it?.container || it?.titulo || '');

    items.sort((a, b) => {
      if (ord === 'eta_asc') return getEta(a) - getEta(b);
      if (ord === 'eta_desc') return getEta(b) - getEta(a);
      if (ord === 'etd_asc') return getEtd(a) - getEtd(b);
      if (ord === 'etd_desc') return getEtd(b) - getEtd(a);
      if (ord === 'nome_desc') return getNome(b).localeCompare(getNome(a), 'pt-BR');
      return getNome(a).localeCompare(getNome(b), 'pt-BR');
    });

    render(items);
    computeKPIs(items);
  }

  // ======================================================
  // MODAL UX PACK (COMPLETO)
  // ======================================================

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  let _modalOpen = false;
  let _lastActive = null;

  function lockBodyScroll() {
    const sbw = window.innerWidth - document.documentElement.clientWidth;
    document.body.dataset.modalScrollLock = '1';
    document.body.style.overflow = 'hidden';
    if (sbw > 0) document.body.style.paddingRight = sbw + 'px';
  }

  function unlockBodyScroll() {
    if (document.body.dataset.modalScrollLock !== '1') return;
    delete document.body.dataset.modalScrollLock;
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
  }

  function updateUrl(id) {
    const url = new URL(window.location.href);
    if (id) url.searchParams.set('id', id);
    else url.searchParams.delete('id');
    history.replaceState({}, '', url.toString());
  }

  function trapFocus(ev) {
    if (!_modalOpen || ev.key !== 'Tab' || !els.modalCard) return;

    const focusables = Array.from(els.modalCard.querySelectorAll(focusableSelector))
      .filter(el => el.offsetParent !== null);

    if (!focusables.length) {
      ev.preventDefault();
      els.modalCard.focus();
      return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];

    if (ev.shiftKey && document.activeElement === first) {
      ev.preventDefault();
      last.focus();
    } else if (!ev.shiftKey && document.activeElement === last) {
      ev.preventDefault();
      first.focus();
    }
  }

  const springOpenEasing = 'cubic-bezier(0.16, 1, 0.3, 1)';
  const springCloseEasing = 'cubic-bezier(0.7, 0, 0.84, 0)';

  function animateOpen() {
    if (!els.modal || !els.modalCard) return;

    els.modal.classList.add('is-open');
    els.modal.style.display = 'flex';

    const backdrop = els.modal.querySelector('.modal__backdrop');
    backdrop?.animate?.([{ opacity: 0 }, { opacity: 1 }], {
      duration: 220,
      easing: springOpenEasing,
      fill: 'forwards'
    });

    els.modalCard.animate?.(
      [
        { transform: 'translateY(18px) scale(0.97)', opacity: 0 },
        { transform: 'translateY(0px) scale(1)', opacity: 1 }
      ],
      { duration: 280, easing: springOpenEasing, fill: 'forwards' }
    );
  }

  function animateClose() {
    if (!els.modal || !els.modalCard) return;

    const backdrop = els.modal.querySelector('.modal__backdrop');
    backdrop?.animate?.([{ opacity: 1 }, { opacity: 0 }], {
      duration: 180,
      easing: springCloseEasing,
      fill: 'forwards'
    });

    const a = els.modalCard.animate?.(
      [
        { transform: 'translateY(0px) scale(1)', opacity: 1 },
        { transform: 'translateY(10px) scale(0.985)', opacity: 0 }
      ],
      { duration: 200, easing: springCloseEasing, fill: 'forwards' }
    );

    if (a) {
      a.onfinish = () => {
        els.modal.classList.remove('is-open');
        els.modal.style.display = 'none';
      };
    } else {
      els.modal.classList.remove('is-open');
      els.modal.style.display = 'none';
    }
  }

  function openModal(it) {
    if (!els.modal) return;

    if (els.mTitle) els.mTitle.textContent = it?.container || it?.titulo || '—';
    if (els.mFase)  els.mFase.textContent  = it?.fase || '—';
    if (els.mETD)   els.mETD.textContent   = it?.previsao_embarque_etd || '—';
    if (els.mETA)   els.mETA.textContent   = it?.previsao_entrega_eta || '—';
    if (els.mId)    els.mId.textContent    = it?.card_id || '—';

    _lastActive = document.activeElement;
    _modalOpen = true;

    els.modal.setAttribute('aria-hidden', 'false');
    lockBodyScroll();
    animateOpen();
    updateUrl(String(it?.card_id || '').trim() || null);

    setTimeout(() => {
      const closeBtn = els.modal.querySelector('[data-close]');
      (closeBtn || els.modalCard)?.focus?.();
    }, 0);
  }

  function closeModal() {
    if (!els.modal || !_modalOpen) return;

    _modalOpen = false;

    els.modal.setAttribute('aria-hidden', 'true');
    animateClose();
    unlockBodyScroll();
    updateUrl(null);

    setTimeout(() => {
      try { _lastActive?.focus?.(); } catch (e) {}
      _lastActive = null;
    }, 0);
  }

  function openById(id) {
    const it = RAW.find((x) => String(x?.card_id) === String(id));
    if (it) openModal(it);
  }

  // click fora fecha + data-close
  safeOn(els.modal, 'click', (ev) => {
    const target = ev.target;
    const close = target?.closest?.('[data-close]');
    if (close) { closeModal(); return; }
    if (target?.classList?.contains('modal__backdrop')) { closeModal(); return; }
  });

  // ESC + focus trap
  document.addEventListener('keydown', (ev) => {
    if (!_modalOpen) return;
    if (ev.key === 'Escape') {
      ev.preventDefault();
      closeModal();
      return;
    }
    trapFocus(ev);
  });

  // =========================
  // CLICK/KEY DELEGAÇÃO (UM SÓ)
  // =========================
  safeOn(els.board, 'click', (ev) => {
    const base = (ev.target instanceof Element) ? ev.target : ev.target?.parentElement;
    if (!base) return;
    const t = base.closest('[data-open]');
    if (!t) return;
    ev.preventDefault();
    openById(t.getAttribute('data-open'));
  });

  safeOn(els.board, 'keydown', (ev) => {
    if (ev.key !== 'Enter' && ev.key !== ' ') return;
    const base = (ev.target instanceof Element) ? ev.target : ev.target?.parentElement;
    if (!base) return;
    const t = base.closest('[data-open]');
    if (!t) return;
    ev.preventDefault();
    openById(t.getAttribute('data-open'));
  });

  // ======================================================
  // DATA LOAD
  // ======================================================
  async function loadData(force = false) {
    await waitForLoader(800);

    loaderOpen(
      force ? 'Atualizando importações…' : 'Carregando importações…',
      force ? 'Ignorando cache…' : 'Buscando dados…'
    );

    try {
      const url = force ? `${API_URL}?force=1` : API_URL;

      const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      const text = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status} - ${text.slice(0, 200)}`);

      let json;
      try { json = JSON.parse(text); }
      catch { throw new Error(`JSON inválido: ${text.slice(0, 200)}`); }

      if (!json || json.ok !== true) {
        throw new Error(`API ok=false: ${text.slice(0, 200)}`);
      }

      RAW = Array.isArray(json.items) ? json.items : [];

      if (els.fase) {
        const fases = orderPhases(RAW.map((it) => it?.fase));
        els.fase.innerHTML =
          `<option value="">Todas</option>` +
          fases.map((f) => `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('');
      }

      if (els.kUpdated) els.kUpdated.textContent = json.cached_at ? String(json.cached_at) : '—';

      applyFilters();

      // deep-link ?id=...
      const urlNow = new URL(window.location.href);
      const wanted = (urlNow.searchParams.get('id') || '').trim();
      if (wanted) setTimeout(() => openById(wanted), 50);
    } catch (e) {
      console.error('COMEX load error:', e);
      setError('Falha ao carregar', String(e?.message || e));
      window.PopperLoading?.error?.(String(e?.message || e));
    } finally {
      loaderClose();
    }
  }

  // chips
  document.querySelectorAll('.chip').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.chip').forEach((b) => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      rangeMode = btn.dataset.range || 'all';
      applyFilters();
    });
  });

  safeOn(els.q, 'input', applyFilters);
  safeOn(els.fase, 'change', applyFilters);
  safeOn(els.ordem, 'change', applyFilters);

  safeOn(els.btnReload, 'click', () => loadData(false));
  safeOn(els.btnForce, 'click', () => loadData(true));

  // start
  loadData(false);
})();