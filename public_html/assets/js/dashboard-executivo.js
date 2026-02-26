/**
 * /assets/js/dashboard-executivo.js
 * - Presets Jan–Dez/2026 via chips (data-ym="2026-02")
 * - Default: window.EXEC_DEFAULT_YM (setado no PHP)
 * - Atualização em tempo real (sem botão aplicar)
 * - TOP 10 destacado + lista completa com scroll (CSS faz o scroll via .top-list {max-height...; overflow:auto})
 */

const CACHE_URL = '/api/dashboard-executivo-save.php';
let chart;

const TOP_N = 10;

// ---------- helpers ----------
function asNumber(v){
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function moneyBR(v){
  return asNumber(v).toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
}

function setText(id, text){
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

function escapeHtml(str){
  return String(str ?? '')
    .replaceAll('&','&amp;')
    .replaceAll('<','&lt;')
    .replaceAll('>','&gt;')
    .replaceAll('"','&quot;')
    .replaceAll("'","&#039;");
}

/**
 * Aceita:
 * - objeto { "A": 123, "B": 456 }
 * - array [ ["A", 123], ["B", 456] ]
 * - array de objetos [ {name:"A", value:123}, ... ]
 */
function normalizeEntries(input){
  if (!input) return [];

  // array
  if (Array.isArray(input)) {
    return input
      .map(it => {
        if (Array.isArray(it)) return [it[0], it[1]];
        if (it && typeof it === 'object') return [it.name ?? it.label ?? it.key, it.value ?? it.val ?? it.total];
        return [null, null];
      })
      .filter(([n]) => n != null && String(n).trim() !== '');
  }

  // objeto
  if (typeof input === 'object') return Object.entries(input);

  return [];
}

// ---------- UI: chips ----------
let currentYm = (typeof window.EXEC_DEFAULT_YM === 'string' && /^\d{4}-\d{2}$/.test(window.EXEC_DEFAULT_YM))
  ? window.EXEC_DEFAULT_YM
  : '2026-02';

function setActiveChip(ym){
  const chipsWrap = document.getElementById('chipsMeses');
  if (!chipsWrap) return;

  const chips = chipsWrap.querySelectorAll('.chip');
  chips.forEach(btn => {
    const bYm = btn.getAttribute('data-ym');
    if (bYm === ym) btn.classList.add('is-active');
    else btn.classList.remove('is-active');
  });
}

function fmtPeriodLabel(ym){
  // ym = YYYY-MM
  const [Y, M] = ym.split('-').map(Number);
  const meses = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
  const mm = Number.isFinite(M) ? meses[M-1] : '—';
  return `${mm}/${String(Y).slice(-2)}`;
}

function applyYm(ym){
  if (!/^\d{4}-\d{2}$/.test(ym)) return;
  currentYm = ym;

  setActiveChip(ym);

  // títulos
  setText('lblPeriodo', `Mês (${fmtPeriodLabel(ym)})`);
  setText('ttlChart', `Faturamento Diário (${fmtPeriodLabel(ym)})`);

  const pl = document.getElementById('periodLabel');
  if (pl) pl.textContent = `Período: ${fmtPeriodLabel(ym)}`;

  // carrega na hora
  refresh();
}

function bindChips(){
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
function renderTopList(containerId, badgeId, input){
  const wrap = document.getElementById(containerId);
  if (!wrap) return;

  const entries = normalizeEntries(input)
    .map(([name, val]) => [String(name), asNumber(val)])
    .filter(([name]) => name.trim() !== '')
    .sort((a,b) => b[1] - a[1]);

  const total = entries.reduce((acc, [,v]) => acc + v, 0);
  const max = entries.length ? entries[0][1] : 0;

  const badge = document.getElementById(badgeId);
  if (badge) badge.textContent = entries.length ? `Top ${Math.min(TOP_N, entries.length)} / ${entries.length}` : '—';

  wrap.innerHTML = '';

  if (!entries.length) {
    wrap.innerHTML = `<div style="opacity:.7;padding:8px 6px;">Sem dados</div>`;
    return;
  }

  // Lista completa (scroll é CSS). TOP_N apenas destacado com .is-top
  for (let idx = 0; idx < entries.length; idx++){
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
function renderChart(diario_mes){
  const diario = (diario_mes && typeof diario_mes === 'object') ? diario_mes : {};

  // keys esperadas: "01","02"... ou "1","2" etc
  const labels = Object.keys(diario).sort((a,b) => Number(a) - Number(b));
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
function buildUrl(){
  const u = new URL(CACHE_URL, window.location.origin);
  // filtro mês 2026-02
  u.searchParams.set('ym', currentYm);
  // cache-bust
  u.searchParams.set('v', String(Date.now()));
  return u.toString();
}

async function load(){
  const res = await fetch(buildUrl(), { cache: 'no-store' });
  if (!res.ok) throw new Error('cache not ok');

  const kpi = await res.json();

  setText('updatedAt', 'Atualizado em: ' + (kpi.updated_at || '—'));

  // KPIs (hoje/ano podem vir independentes do filtro, depende do seu PHP API)
  setText('kpiHoje', moneyBR(kpi.hoje));
  setText('kpiMes',  moneyBR(kpi.mes));
  setText('kpiAno',  moneyBR(kpi.ano));

  setText('kpiNfHoje', `${kpi.qtd_nf_hoje || 0} NF`);
  setText('kpiNfMes',  `${kpi.qtd_nf_mes || 0} NF no mês`);
  setText('kpiClientesMes', `${kpi.clientes_mes || 0} clientes`);

  renderTopList('listTopProdutos', 'badgeTopProdutos', kpi.top_produtos);
  renderTopList('listTopVendedores', 'badgeTopVendedores', kpi.top_vendedores);

  renderChart(kpi.diario_mes);
}

async function refresh(){
  try {
    await load();
  } catch (e){
    console.error(e);
    setText('updatedAt', 'Sem dados (cache não encontrado/erro)');
    renderTopList('listTopProdutos', 'badgeTopProdutos', null);
    renderTopList('listTopVendedores', 'badgeTopVendedores', null);
  }
}

// ---------- init ----------
(function init(){
  bindChips();
  applyYm(currentYm); // isso já chama refresh()
  // TV: atualiza a cada 60s mantendo o mês selecionado
  setInterval(refresh, 60_000);
})();