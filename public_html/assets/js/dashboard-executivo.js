/**
 * /assets/js/dashboard-executivo.js
 * - Chips Jan–Dez/2026 via data-ym="2026-02"
 * - Default: window.EXEC_DEFAULT_YM
 * - Carrega /api/dashboard-executivo-save.php?ym=YYYY-MM
 * - Renderiza 3 cards: Hoje / Mês / Ano (Total + Faturado + Agendado)
 * - Renderiza gráfico diário + Tops
 */

const CACHE_URL = '/api/dashboard-executivo-save.php';
let chart;

const TOP_N = 10;

// ---------- helpers ----------
function asNumber(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function moneyBR(v) {
  return asNumber(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');
}

/**
 * Aceita:
 * - objeto { "A": 123, "B": 456 }
 * - array [ ["A", 123], ["B", 456] ]
 * - array de objetos [ {name:"A", value:123}, ... ]
 */
function normalizeEntries(input) {
  if (!input) return [];

  if (Array.isArray(input)) {
    return input
      .map(it => {
        if (Array.isArray(it)) return [it[0], it[1]];
        if (it && typeof it === 'object') return [it.name ?? it.label ?? it.key, it.value ?? it.val ?? it.total];
        return [null, null];
      })
      .filter(([n]) => n != null && String(n).trim() !== '');
  }

  if (typeof input === 'object') return Object.entries(input);

  return [];
}

// ---------- UI: chips ----------
let currentYm = (typeof window.EXEC_DEFAULT_YM === 'string' && /^\d{4}-\d{2}$/.test(window.EXEC_DEFAULT_YM))
  ? window.EXEC_DEFAULT_YM
  : '2026-02';

function setActiveChip(ym) {
  const chipsWrap = document.getElementById('chipsMeses');
  if (!chipsWrap) return;

  chipsWrap.querySelectorAll('.chip').forEach(btn => {
    const bYm = btn.getAttribute('data-ym');
    btn.classList.toggle('is-active', bYm === ym);
  });
}

function fmtPeriodLabel(ym) {
  const [Y, M] = ym.split('-').map(Number);
  const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
  const mm = Number.isFinite(M) ? meses[M - 1] : '—';
  return `${mm}/${String(Y).slice(-2)}`;
}

function applyYm(ym) {
  if (!/^\d{4}-\d{2}$/.test(ym)) return;

  currentYm = ym;
  setActiveChip(ym);

  setText('ttlChart', `Faturamento Diário (${fmtPeriodLabel(ym)})`);

  const pl = document.getElementById('periodLabel');
  if (pl) pl.textContent = `Período: ${fmtPeriodLabel(ym)}`;

  refresh();
}

function bindChips() {
  const chipsWrap = document.getElementById('chipsMeses');
  if (!chipsWrap) return;

  chipsWrap.addEventListener('click', (ev) => {
    const btn = ev.target?.closest?.('.chip');
    if (!btn) return;
    const ym = btn.getAttribute('data-ym');
    if (!ym) return;
    applyYm(ym);
  });
}

// ---------- TOP LIST ----------
function renderTopList(containerId, badgeId, input) {
  const wrap = document.getElementById(containerId);
  if (!wrap) return;

  const entries = normalizeEntries(input)
    .map(([name, val]) => [String(name), asNumber(val)])
    .filter(([name]) => name.trim() !== '')
    .sort((a, b) => b[1] - a[1]);

  const total = entries.reduce((acc, [, v]) => acc + v, 0);
  const max = entries.length ? entries[0][1] : 0;

  const badge = document.getElementById(badgeId);
  if (badge) badge.textContent = entries.length ? `Top ${Math.min(TOP_N, entries.length)} / ${entries.length}` : '—';

  wrap.innerHTML = '';

  if (!entries.length) {
    wrap.innerHTML = `<div style="opacity:.7;padding:8px 6px;">Sem dados</div>`;
    return;
  }

  for (let idx = 0; idx < entries.length; idx++) {
    const [name, val] = entries[idx];
    const rank = idx + 1;

    const pct = total > 0 ? (val / total) * 100 : 0;
    const width = max > 0 ? (val / max) * 100 : 0;

    const row = document.createElement('div');
    row.className = 'top-item' + (rank <= TOP_N ? ' is-top' : '');

    row.innerHTML = `
      <div class="top-rank">${rank}</div>

      <div class="top-main">
        <div class="top-name" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
        <div class="top-sub"><span>${pct.toFixed(1)}%</span></div>
        <div class="top-bar"><i style="width:${width.toFixed(1)}%"></i></div>
      </div>

      <div class="top-val">${moneyBR(val)}</div>
    `;

    wrap.appendChild(row);
  }
}

// ---------- CHART ----------
function renderChart(diario_mes) {
  const diario = (diario_mes && typeof diario_mes === 'object') ? diario_mes : {};

  // Ordena por dia numérico (chaves "02","3","26"...)
  const labels = Object.keys(diario).sort((a, b) => Number(a) - Number(b));
  const values = labels.map(k => asNumber(diario[k]));

  const canvas = document.getElementById('chartDiario');
  if (!canvas) return;

  if (chart) chart.destroy();

  chart = new Chart(canvas, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Faturamento',
        data: values,
        tension: 0.25,
        pointRadius: 2
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { ticks: { maxRotation: 0 } }
      }
    }
  });
}

// ---------- DATA LOAD ----------
function buildUrl() {
  const u = new URL(CACHE_URL, window.location.origin);
  u.searchParams.set('ym', currentYm);
  u.searchParams.set('v', String(Date.now())); // cache-bust
  return u.toString();
}

async function load() {
  const res = await fetch(buildUrl(), { cache: 'no-store' });
  if (!res.ok) throw new Error(`HTTP ${res.status}`);

  const payload = await res.json();
  if (!payload || payload.success === false) throw new Error('payload inválido');

  const v = payload.values || {};
  const updatedAt = payload.updated_at || '—';

  // Header
  setText('updatedAt', `Atualizado em: ${updatedAt}`);

  // HOJE
  setText('kpiHojeTotal', moneyBR(v.hoje_total));
  setText('kpiHojeFat', moneyBR(v.hoje_faturado));
  setText('kpiHojeAg', moneyBR(v.hoje_agendado));
  setText('kpiHojeTrend', `Atualizado: ${updatedAt}`);

  // MÊS
  setText('kpiMesTotal', moneyBR(v.mes_total));
  setText('kpiMesFat', moneyBR(v.mes_faturado));
  setText('kpiMesAg', moneyBR(v.mes_agendado));
  setText('kpiMesTrend', `Atualizado: ${updatedAt}`);

  // ANO
  setText('kpiAnoTotal', moneyBR(v.ano_total));
  setText('kpiAnoFat', moneyBR(v.ano_faturado));
  setText('kpiAnoAg', moneyBR(v.ano_agendado));
  setText('kpiAnoTrend', `Atualizado: ${updatedAt}`);

  // Tops + gráfico
  renderTopList('listTopProdutos', 'badgeTopProdutos', payload.top_produtos);
  renderTopList('listTopVendedores', 'badgeTopVendedores', payload.top_vendedores);
  renderChart(payload.diario_mes);
}

async function refresh() {
  try {
    await load();
  } catch (e) {
    console.error(e);
    setText('updatedAt', 'Sem dados (erro ao carregar)');
    renderTopList('listTopProdutos', 'badgeTopProdutos', null);
    renderTopList('listTopVendedores', 'badgeTopVendedores', null);
  }
}

// ---------- init ----------
(function init() {
  bindChips();
  applyYm(currentYm);        // já chama refresh()
  setInterval(refresh, 60_000);
})();