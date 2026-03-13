
/* ======================================================
   ENTRADA SUAVE
====================================================== */
(function () {
  const goReady = () => document.body.classList.add('is-ready');
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', goReady, { once: true });
  } else {
    goReady();
  }
})();

const now = new Date();
const CURRENT_YM =
  now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");

/* ======================================================
   FORMATADORES / HELPERS
====================================================== */
function brl(v) {
  return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(v || 0);
}
function num(v) {
  v = Number(v);
  return Number.isFinite(v) ? v : 0;
}
function parseBrlText(text) {
  if (!text) return 0;
  const cleaned = String(text)
    .replace(/\s+/g, '')
    .replace(/R\$/g, '')
    .replace(/\./g, '')
    .replace(',', '.');
  const n = Number(cleaned);
  return Number.isFinite(n) ? n : 0;
}

function getTextNumber(id) {
  const el = document.getElementById(id);
  return el ? parseBrlText(el.textContent) : 0;
}

function pickFirstValid(...vals) {
  for (const v of vals) {
    const n = Number(v);
    if (Number.isFinite(n) && n > 0) return n;
  }
  return 0;
}
/* ======================================================
   CACHE (10 min) - NÃO RECARREGA NO F5
====================================================== */
const CACHE_TTL = 10 * 60 * 1000; // 10 min

const KEY_DASH = (ym) => `tvcache:dash:executivo:${ym}`;
const KEY_TOPS = (ym) => `tvcache:tops:${ym}`;
const KEY_DAILY = (ym) => `tvcache:daily:${ym}`;

function cacheGet(key) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return null;
    const obj = JSON.parse(raw);
    if (!obj || typeof obj !== "object") return null;
    if (!obj.ts || (Date.now() - obj.ts) > CACHE_TTL) return null;
    return obj.data ?? null;
  } catch {
    return null;
  }
}

function cacheSet(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify({ ts: Date.now(), data }));
  } catch { }
}

function cacheAgeMs(key) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return Infinity;
    const obj = JSON.parse(raw);
    if (!obj?.ts) return Infinity;
    return Math.max(0, Date.now() - obj.ts);
  } catch {
    return Infinity;
  }
}

function msToNextRefresh(key) {
  const age = cacheAgeMs(key);
  const left = CACHE_TTL - age;
  // no mínimo 2s, pra evitar loop agressivo
  return Math.max(2000, left);
}

/* ======================================================
   CARREGA KPIs (com cache local)
====================================================== */
const API = "/api/dashboard-data.php?dash=executivo";

function applyDashboard(data) {
  if (!data || !data.values) return;

  const v = data.values;
  const set = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  set("tv-meta", brl(v.meta_mes));
  set("tv-realizado", brl(v.realizado_ate_hoje));
  set("tv-falta", brl(v.falta_meta_mes));

  set("tv-mes", brl(v.mes_total));
  set("tv-mes-fat", brl(v.mes_faturado));
  set("tv-mes-im", brl(v.mes_im));
  set("tv-mes-ag", brl(v.mes_ag));

  set("tv-dias", v.dias_uteis_trabalhados + " / " + v.dias_uteis_trabalhar);
  set("tv-prod", Math.round((v.realizado_dia_util_pct || 0) * 100) + "%");

  set("tv-deveria", brl(v.deveria_ate_hoje));
  set("tv-ating", Math.round((v.atingimento_mes_pct || 0) * 100) + "%");

  set("tv-projecao", brl(v.fechar_em));
  set("tv-proj-pct", Math.round((v.equivale_pct || 0) * 100) + "%");

  set("tv-hoje", brl(v.hoje_total));
  set("tv-hoje-fat", brl(v.hoje_faturado));
  set("tv-hoje-im", brl(v.hoje_im));
  set("tv-hoje-ag", brl(v.hoje_ag));

  set("tv-meta-dia", brl(v.a_faturar_dia_util));

  const diasRest = Math.max(0, num(v.dias_uteis_trabalhar) - num(v.dias_uteis_trabalhados));
  set("tv-dias-rest", diasRest);

  set("tv-restante", brl(v.falta_meta_mes));
  set("tv-meta-teo", brl(v.meta_dia_util));
  set("tv-updated", data.updated_at || "--");
  set("tv-updated-footer", data.updated_at || "--");

  // GAP HOJE vs META TEÓRICA DO DIA
  const hojeTotal = num(v.hoje_total);
  const metaTeoDia = num(v.meta_dia_util);
  const gapHoje = hojeTotal - metaTeoDia;

  const gapEl = document.getElementById("tv-gap");
  if (gapEl) {
    gapEl.textContent = gapHoje >= 0 ? "Acima da meta do dia" : "Abaixo da meta do dia";
    gapEl.className = gapHoje >= 0 ? "kpi-green" : "kpi-red";
  }

  // Barra meta mês
  const metaBar = document.getElementById("meta-bar");
  const metaPct = document.getElementById("meta-pct");
  if (metaBar) {
    const pct = (num(v.atingimento_mes_pct)) * 100;
    metaBar.style.width = Math.min(pct, 100) + "%";
    if (metaPct) metaPct.textContent = Math.round(pct) + "%";
  }

  // Barra meta dinâmica do dia (HOJE / a_faturar_dia_util)
  const metaDiaBar = document.getElementById("meta-dia-bar");
  const metaDiaPct = document.getElementById("meta-dia-pct");
  if (metaDiaBar) {
    const metaDinamicaDia = num(v.a_faturar_dia_util);
    let pct = 0;
    if (metaDinamicaDia > 0) pct = (hojeTotal / metaDinamicaDia) * 100;
    metaDiaBar.style.width = Math.min(pct, 100) + "%";
    if (metaDiaPct) metaDiaPct.textContent = Math.round(pct) + "%";
  }

  // gráficos de barras (mes/ano/ritmo)
  loadCharts(v);
}

async function loadDashboard(opts = { force: false }) {
  const key = KEY_DASH(CURRENT_YM);

  // 1) tenta cache (não recarrega no F5)
  if (!opts.force) {
    const cached = cacheGet(key);
    if (cached) {
      applyDashboard(cached);
      return;
    }
  }

  // 2) busca só quando expirar ou force
  try {
    const r = await fetch(API, { cache: "default" });
    const data = await r.json();
    if (!data || !data.values) return;

    cacheSet(key, data);
    applyDashboard(data);
  } catch (e) {
    console.warn("Erro dashboard:", e);
  }
}

/* ======================================================
   GRÁFICOS (mes/ano/ritmo)
====================================================== */
Chart.register(ChartDataLabels);

let chartMes, chartAno, chartRitmo;

function brlShort(n) {
  const v = Number(n || 0);
  if (v >= 1_000_000) return "R$ " + (v / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " mi";
  if (v >= 1_000) return "R$ " + (v / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 0 }) + " mil";
  return "R$ " + v.toLocaleString("pt-BR", { maximumFractionDigits: 0 });
}
function tickShort(n) {
  const v = Number(n || 0);
  if (v >= 1_000_000) return (v / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " mi";
  if (v >= 1_000) return (v / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 0 }) + " mil";
  return v.toLocaleString("pt-BR", { maximumFractionDigits: 0 });
}

function loadCharts(v) {
  const valueLabelPlugin = {
    id: "valueLabelPlugin",
    afterDatasetsDraw(chart) {
      const { ctx } = chart;
      const dataset = chart.data.datasets?.[0];
      if (!dataset) return;

      const meta = chart.getDatasetMeta(0);
      if (!meta || !meta.data) return;

      ctx.save();
      ctx.textAlign = "center";
      ctx.textBaseline = "bottom";
      ctx.fillStyle = "#1f2937";
      ctx.font = "700 12px Poppins, Arial, sans-serif";

      meta.data.forEach((bar, i) => {
        const raw = Number(dataset.data?.[i] ?? 0);
        if (!Number.isFinite(raw) || raw <= 0) return;

        const x = bar.x;
        const y = bar.y - 8;

        let line1 = brlShort(raw);
        let line2 = "";

        if (chart.canvas.id === "chartMes") {
          const metaMes = Number(v.meta_mes || 0);
          if (metaMes > 0) {
            line2 = Math.round((raw / metaMes) * 100) + "%";
          }
        }

        if (chart.canvas.id === "chartAno") {
          const metaAno = Number(v.meta_ano || 0);
          if (metaAno > 0) {
            line2 = Math.round((raw / metaAno) * 100) + "%";
          }
        }

        ctx.fillText(line1, x, y);
        if (line2) {
          ctx.fillText(line2, x, y - 16);
        }
      });

      ctx.restore();
    }
  };

  const commonBarOptions = {
    resizeDelay: 80,
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 350 },
    plugins: {
      legend: { display: false },
      datalabels: false,
      tooltip: {
        callbacks: {
          label: (ctx) => brl(ctx.raw)
        }
      }
    },
    scales: {
      y: {
        beginAtZero: true,
        ticks: {
          callback: (value) => tickShort(value)
        }
      }
    }
  };

  const barDatasetBase = {
    borderSkipped: "bottom",
    borderRadius: { topLeft: 14, topRight: 14, bottomLeft: 0, bottomRight: 0 }
  };

  const elMes = document.getElementById("chartMes");
  const elAno = document.getElementById("chartAno");
  const elRitmo = document.getElementById("chartRitmo");

  if (!elMes || !elAno || !elRitmo) return;

  const mesRealizado = num(v.realizado_ate_hoje || 0);
  const mesMeta = num(v.meta_mes || 0);

  const anoRealizado = num(v.realizado_ano_acum || 0);
  const anoMeta = num(v.meta_ano || 0);

  const metaDia = num(v.meta_dia_util || 0);
  const hojePrincipal = num(v.hoje_total || 0);

  if (chartMes) {
    try { chartMes.destroy(); } catch (e) {}
  }
  chartMes = new Chart(elMes, {
    type: "bar",
    data: {
      labels: ["Realizado", "Meta"],
      datasets: [{
        ...barDatasetBase,
        data: [mesRealizado, mesMeta],
        backgroundColor: ["#6b46c1", "#b5d334"]
      }]
    },
    options: commonBarOptions,
    plugins: [valueLabelPlugin]
  });

  if (chartAno) {
    try { chartAno.destroy(); } catch (e) {}
  }
  chartAno = new Chart(elAno, {
    type: "bar",
    data: {
      labels: ["Realizado", "Meta"],
      datasets: [{
        ...barDatasetBase,
        data: [anoRealizado, anoMeta],
        backgroundColor: ["#6b46c1", "#b5d334"]
      }]
    },
    options: commonBarOptions,
    plugins: [valueLabelPlugin]
  });

  if (chartRitmo) {
    try { chartRitmo.destroy(); } catch (e) {}
  }
  chartRitmo = new Chart(elRitmo, {
    type: "bar",
    data: {
      labels: ["Meta do dia", "Realizado hoje"],
      datasets: [{
        ...barDatasetBase,
        data: [metaDia, hojePrincipal],
        backgroundColor: ["#f59e0b", "#22c55e"]
      }]
    },
    options: commonBarOptions,
    plugins: [valueLabelPlugin]
  });
}

/* ======================================================
   FATURAMENTO DIÁRIO (CORRIGIDO)
====================================================== */
let chartDiario;

function normalizeDailyValue(v) {
  const n = Number(v);

  // inválido vira 0
  if (!Number.isFinite(n)) return 0;

  // arredonda só para limpar ruído decimal
  return Math.round(n * 100) / 100;
}

function dailyLabelFromYm(ym) {
  const [Y, M] = String(ym).split('-').map(Number);
  const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
  const mm = Number.isFinite(M) ? meses[M - 1] : '--';
  return `${mm}/${String(Y).slice(-2)}`;
}

function applyDailyChart(payload) {
  const diario = (payload && typeof payload.diario_mes === 'object' && payload.diario_mes)
    ? payload.diario_mes
    : {};

  const labels = Object.keys(diario).sort((a, b) => Number(a) - Number(b));
  const valores = labels.map((dia) => normalizeDailyValue(diario[dia]));

  if (chartDiario) {
    try { chartDiario.destroy(); } catch (e) {}
  }

  const ctx = document.getElementById("chartDiario");
  if (!ctx) return;

  chartDiario = new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [{
        label: "Faturamento",
        data: valores,
        borderColor: "#5c2c8c",
        backgroundColor: "rgba(92,44,140,.12)",
        fill: true,
        tension: 0.25,
        pointRadius: 5,
        pointHoverRadius: 8,
        pointHitRadius: 12,
        pointBorderWidth: 2,
        spanGaps: true,
        datalabels: {
          anchor: "end",
          align: "top",
          offset: 10,
          color: "#1f2937",
          formatter: (v) => {
            const n = normalizeDailyValue(v);
            if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace('.', ',') + "M";
            if (n >= 1_000) return (n / 1_000).toFixed(0) + "k";
            return String(Math.round(n));
          },
          font: { size: 15, weight: "700" },
          textStrokeColor: "#ffffff",
          textStrokeWidth: 3,
          clamp: true,
          clip: false
        }
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      layout: { padding: { top: 48, right: 8, left: 8 } },
      plugins: {
        legend: { display: false },
        datalabels: {
          anchor: "end",
          align: "top",
          offset: 10,
          clamp: true,
          clip: false
        },
        tooltip: {
          callbacks: {
            label: (ctx) => brl(normalizeDailyValue(ctx.raw))
          }
        }
      },
      scales: {
        x: {
          ticks: { maxRotation: 0 }
        },
        y: {
          beginAtZero: true,
          min: 0,
          ticks: {
            callback: (value) => brlShort(value)
          }
        }
      }
    }
  });

  const titulo = document.getElementById("ttlChart");
  if (titulo) titulo.textContent = "Faturamento Diário (" + dailyLabelFromYm(CURRENT_YM) + ")";
}

async function loadDailyChart(opts = { force: false }) {
  const key = KEY_DAILY(CURRENT_YM);

  // usa cache local só se não for force
  if (!opts.force) {
    const cached = cacheGet(key);
    if (cached) {
      applyDailyChart(cached);
      return;
    }
  }

  try {
    const url = new URL("/api/dashboard-executivo-save.php", window.location.origin);
    url.searchParams.set("ym", CURRENT_YM);
    url.searchParams.set("v", String(Date.now())); // evita cache antigo do browser

    const r = await fetch(url.toString(), { cache: "no-store" });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);

    const data = await r.json();
    if (!data || data.success === false) throw new Error("Payload inválido");

    cacheSet(key, data);
    applyDailyChart(data);
  } catch (e) {
    console.warn("Erro gráfico diário:", e);
  }
}
/* ======================================================
   TOPS (com cache local)
====================================================== */
const TOP_N = 10;

function asNumber(v) { const n = Number(v); return Number.isFinite(n) ? n : 0; }
function moneyBR(v) { return asNumber(v).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }); }
function escapeHtml(str) {
  return String(str ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", "&#039;");
}

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
    let cls = 'top-item';
    if (rank === 1) cls += ' is-top1';
    else if (rank === 2) cls += ' is-top2';
    else if (rank === 3) cls += ' is-top3';
    else if (rank <= TOP_N) cls += ' is-top';

    row.className = cls;

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

function fmtYM(ym) {
  const [Y, M] = String(ym).split('-').map(Number);
  const meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
  const mm = Number.isFinite(M) ? meses[M - 1] : '--';
  return `${mm}/${String(Y).slice(-2)}`;
}

function applyTopsCarousel(prodPayload, cliPayload) {
  const ym = CURRENT_YM;

  const upd = prodPayload?.updated_at || cliPayload?.updated_at || "--";

  const per = document.getElementById("topsPeriod");
  if (per) per.textContent = fmtYM(ym);

  const upEl = document.getElementById("topsUpdated");
  if (upEl) upEl.textContent = upd;

  renderTopList("listTopProdutos", "badgeTopProdutos", prodPayload?.top_produtos || null);

  const top50 = cliPayload?.ranking?.top50 || [];
  const clientesAsEntries = Array.isArray(top50) ? top50.map(x => [x.cliente, x.valor]) : null;
  renderTopList("listTopClientes", "badgeTopClientes", clientesAsEntries);
}

async function loadTopsCarousel(opts = { force: false }) {
  const key = KEY_TOPS(CURRENT_YM);

  if (!opts.force) {
    const cached = cacheGet(key);
    if (cached) {
      applyTopsCarousel(cached.prod, cached.cli);
      return;
    }
  }

  try {
    const ym = CURRENT_YM;

    const rProd = await fetch("/api/dashboard-executivo-save.php?ym=" + ym, { cache: "default" });
    const prodPayload = await rProd.json();

    const rCli = await fetch("/api/clientes_insights.php?ym=" + ym, { cache: "default" });
    const cliPayload = await rCli.json();

    const pack = { prod: prodPayload, cli: cliPayload };
    cacheSet(key, pack);

    applyTopsCarousel(prodPayload, cliPayload);
  } catch (e) {
    console.warn("Erro tops carousel:", e);
    renderTopList("listTopProdutos", "badgeTopProdutos", null);
    renderTopList("listTopClientes", "badgeTopClientes", null);
  }
}

/* ======================================================
   BOOT + REFRESH (10 min, alinhado ao TTL)
====================================================== */
// 1) pinta imediatamente do cache (se existir)
loadDashboard({ force: false });
loadTopsCarousel({ force: false });

// diário: espera o carousel montar, mas usa cache se tiver
// diário
setTimeout(() => loadDailyChart({ force: false }), 200);


// 2) agenda próxima atualização exatamente quando o cache vencer
function scheduleRefresh() {
  // dashboard
  setTimeout(() => {
    loadDashboard({ force: true });
    setInterval(() => loadDashboard({ force: true }), CACHE_TTL);
  }, msToNextRefresh(KEY_DASH(CURRENT_YM)));

  // tops
  setTimeout(() => {
    loadTopsCarousel({ force: true });
    setInterval(() => loadTopsCarousel({ force: true }), CACHE_TTL);
  }, msToNextRefresh(KEY_TOPS(CURRENT_YM)));

  // diário
  setTimeout(() => {
    loadDailyChart({ force: true });
    setInterval(() => loadDailyChart({ force: true }), CACHE_TTL);
  }, msToNextRefresh(KEY_DAILY(CURRENT_YM)));
}

scheduleRefresh();