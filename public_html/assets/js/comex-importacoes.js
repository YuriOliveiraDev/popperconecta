/* global window */
(() => {
  'use strict';

  const API_URL = window.COMEX_API_URL || '/api/comex-importacoes.php';

  const els = {
    q: document.getElementById('q'),
    fase: document.getElementById('fase'),
    ordem: document.getElementById('ordem'),
    board: document.getElementById('board'),
    empty: document.getElementById('empty'),
    btnReload: document.getElementById('btnReload'),
    btnForce: document.getElementById('btnForce'),
    loading: document.getElementById('loading'),
    kTotal: document.getElementById('kTotal'),
    kNext30: document.getElementById('kNext30'),
    kLate: document.getElementById('kLate'),
    kUpdated: document.getElementById('kUpdated'),

    modal: document.getElementById('modal'),
    mTitle: document.getElementById('mTitle'),
    mFase: document.getElementById('mFase'),
    mETD: document.getElementById('mETD'),
    mETA: document.getElementById('mETA'),
    mId: document.getElementById('mId'),
  };

  let RAW = [];
  let rangeMode = 'all';

  // ---------- helpers ----------
  function safeOn(el, evt, fn, opts) {
    if (el) el.addEventListener(evt, fn, opts);
  }

  function openLoading(on) {
    if (!els.loading) return;
    els.loading.classList.toggle('is-open', !!on);
    els.loading.setAttribute('aria-hidden', on ? 'false' : 'true');
  }

  function setError(title, sub) {
    if (els.board) els.board.innerHTML = '';
    if (!els.empty) return;
    els.empty.hidden = false;
    const t = els.empty.querySelector('.empty__title');
    const s = els.empty.querySelector('.empty__sub');
    if (t) t.textContent = title || 'Falha ao carregar';
    if (s) s.textContent = sub || 'Verifique a API do Pipefy (ou o endpoint interno).';
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

  // ---------- status / regras ----------
  function normalizePhase(phase) {
    return String(phase || '').trim();
  }

  function isConcluded(phase) {
    const p = String(phase || '').toUpperCase();
    return p.includes('CONCLU');
  }

  // ===============================
  // ORDEM FIXA DAS FASES (KANBAN)
  // ===============================
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

    // 1) segue sua sequência oficial
    const ordered = FASE_ORDER.filter((p) => list.includes(p));

    // 2) fases novas/desconhecidas vão pro final (pra não sumir nada)
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
    // ✅ regra: concluído não entra como atrasado
    if (isConcluded(it?.fase)) return false;
    const eta = parseBRDate(it?.previsao_entrega_eta);
    return !!eta && eta < now;
  }

  // ---------- KPIs ----------
  function computeKPIs(items) {
    if (!els.kTotal) return;

    const now = new Date();
    const in30 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 30);

    let next30 = 0;
    let late = 0;

    for (const it of items) {
      if (isConcluded(it?.fase)) continue; // ✅ concluído não conta

      const eta = parseBRDate(it?.previsao_entrega_eta);
      if (!eta) continue;

      if (eta < now) late++;
      else if (eta <= in30) next30++;
    }

    // Total é do filtro atual (inclui concluídos)
    els.kTotal.textContent = String(items.length);
    if (els.kNext30) els.kNext30.textContent = String(next30);
    if (els.kLate) els.kLate.textContent = String(late);
  }

  // ---------- render (cards por status) ----------
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

    // agrupa por fase (status)
    const map = new Map();
    for (const it of items) {
      const key = normalizePhase(it?.fase) || '—';
      if (!map.has(key)) map.set(key, []);
      map.get(key).push(it);
    }

    // ordena fases na sequência oficial
    const phases = orderPhases([...map.keys()]);

    // monta cards de status
    els.board.innerHTML = phases
      .map((phase) => {
        const list = map.get(phase) || [];

        // atrasados por status (ignorando concluídos por regra)
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
      })
      .join('');

    if (els.empty) els.empty.hidden = items.length > 0;
  }

  // ---------- filtros / ordenação ----------
  function applyFilters() {
    if (!els.q || !els.fase || !els.ordem) return;

    const q = (els.q.value || '').trim().toLowerCase();
    const f = (els.fase.value || '').trim();
    let items = RAW.slice();

    const now = new Date();
    const in30 = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 30);

    if (rangeMode === 'next30') {
      items = items.filter((it) => {
        if (isConcluded(it?.fase)) return false; // ✅
        const eta = parseBRDate(it?.previsao_entrega_eta);
        return eta && eta >= now && eta <= in30;
      });
    } else if (rangeMode === 'late') {
      items = items.filter((it) => {
        if (isConcluded(it?.fase)) return false; // ✅
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

  // ---------- modal ----------
  function openModal(it) {
    if (!els.modal) return;
    if (els.mTitle) els.mTitle.textContent = it?.container || it?.titulo || '—';
    if (els.mFase) els.mFase.textContent = it?.fase || '—';
    if (els.mETD) els.mETD.textContent = it?.previsao_embarque_etd || '—';
    if (els.mETA) els.mETA.textContent = it?.previsao_entrega_eta || '—';
    if (els.mId) els.mId.textContent = it?.card_id || '—';

    els.modal.classList.add('is-open');
    els.modal.setAttribute('aria-hidden', 'false');
  }

  function closeModal() {
    if (!els.modal) return;
    els.modal.classList.remove('is-open');
    els.modal.setAttribute('aria-hidden', 'true');
  }

  // ---------- data ----------
  async function loadData(force = false) {
    openLoading(true);
    try {
      const url = force ? `${API_URL}?force=1` : API_URL;

      const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });

      const text = await res.text();
      if (!res.ok) throw new Error(`HTTP ${res.status} - ${text.slice(0, 200)}`);

      let json;
      try {
        json = JSON.parse(text);
      } catch {
        throw new Error(`JSON inválido. Início da resposta: ${text.slice(0, 200)}`);
      }

      if (!json || json.ok !== true) {
        throw new Error(`API retornou ok=false. Resposta: ${text.slice(0, 200)}`);
      }

      RAW = Array.isArray(json.items) ? json.items : [];

      // dropdown de fases (na ordem fixa)
      if (els.fase) {
        const fases = orderPhases(RAW.map((it) => it?.fase));

        els.fase.innerHTML =
          `<option value="">Todas</option>` +
          fases.map((f) => `<option value="${escapeHtml(f)}">${escapeHtml(f)}</option>`).join('');
      }

      if (els.kUpdated) els.kUpdated.textContent = json.cached_at ? String(json.cached_at) : '—';

      applyFilters();
    } catch (e) {
      console.error('COMEX load error:', e);
      setError('Falha ao carregar', String(e?.message || e));
    } finally {
      openLoading(false);
    }
  }

  // ---------- events ----------
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

  // abrir modal: click (delegation)
  safeOn(els.board, 'click', (ev) => {
    const t = ev.target.closest('[data-open]');
    if (!t) return;
    ev.preventDefault();
    const id = t.getAttribute('data-open');
    const it = RAW.find((x) => String(x?.card_id) === String(id));
    if (it) openModal(it);
  });

  // abrir modal: teclado (Enter)
  safeOn(els.board, 'keydown', (ev) => {
    if (ev.key !== 'Enter') return;
    const t = ev.target.closest('[data-open]');
    if (!t) return;
    ev.preventDefault();
    const id = t.getAttribute('data-open');
    const it = RAW.find((x) => String(x?.card_id) === String(id));
    if (it) openModal(it);
  });

  // fechar modal
  safeOn(els.modal, 'click', (ev) => {
    const close = ev.target.closest('[data-close]');
    if (close) closeModal();
  });

  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape') closeModal();
  });

  // start
  loadData(false);
})();