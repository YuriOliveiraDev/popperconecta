/* ======================================================
   ENTRADA SUAVE
====================================================== */
(function () {
  const goReady = () => document.body.classList.add("is-ready");
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", goReady, { once: true });
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
  return new Intl.NumberFormat("pt-BR", {
    style: "currency",
    currency: "BRL"
  }).format(Number(v) || 0);
}

function num(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function parseBrlText(text) {
  if (!text) return 0;

  const cleaned = String(text)
    .replace(/\s+/g, "")
    .replace(/R\$/g, "")
    .replace(/\./g, "")
    .replace(",", ".");

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

function safeText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function qsUrl(path, params = {}) {
  const url = new URL(path, window.location.origin);
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== "") {
      url.searchParams.set(key, String(value));
    }
  });
  return url.toString();
}
function setCardsLoading(isLoading) {
  document.querySelectorAll(".dash-tv-grid .dash-tv-card").forEach((card) => {
    card.classList.toggle("is-loading", isLoading);
  });
}

function setCardLoadingBySelector(selector, isLoading) {
  document.querySelectorAll(selector).forEach((el) => {
    el.classList.toggle("is-loading", isLoading);
  });
}
/* ======================================================
   CACHE (10 min)
====================================================== */
const CACHE_TTL = 10 * 60 * 1000;

// versionadas para matar cache antigo/incompatível
const KEY_DASH = (ym) => `tvcache:v2:dash:executivo:${ym}`;
const KEY_TOPS = (ym) => `tvcache:v2:tops:${ym}`;
const KEY_DAILY = (ym) => `tvcache:v2:daily:${ym}`;

function cacheGet(key) {
  try {
    const raw = localStorage.getItem(key);
    if (!raw) return null;

    const obj = JSON.parse(raw);
    if (!obj || typeof obj !== "object") return null;
    if (!obj.ts || Date.now() - obj.ts > CACHE_TTL) return null;

    return obj.data ?? null;
  } catch {
    return null;
  }
}

function cacheSet(key, data) {
  try {
    localStorage.setItem(key, JSON.stringify({
      ts: Date.now(),
      data
    }));
  } catch {
    // silencioso
  }
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
  return Math.max(2000, left);
}

function isValidDashboardPayload(data) {
  return !!(
    data &&
    typeof data === "object" &&
    data.values &&
    typeof data.values === "object" &&
    "mes_total" in data.values &&
    "realizado_ate_hoje" in data.values &&
    "mes_faturado" in data.values &&
    "mes_im" in data.values
  );
}

function isValidDailyPayload(data) {
  return !!(
    data &&
    typeof data === "object" &&
    typeof data.diario_mes === "object"
  );
}

function isValidTopsPayload(data) {
  return !!(
    data &&
    typeof data === "object" &&
    data.prod &&
    data.cli
  );
}

/* ======================================================
   DASHBOARD KPIs
====================================================== */
const API = "/api/dashboard/dashboard-data.php";

function applyDashboard(data) {
  if (!isValidDashboardPayload(data)) return;

  const v = data.values;

  safeText("tv-meta", brl(v.meta_mes));
  safeText("tv-realizado", brl(v.realizado_ate_hoje));
  safeText("tv-falta", brl(v.falta_meta_mes));

  safeText("tv-mes", brl(v.mes_total));
  safeText("tv-mes-fat", brl(v.mes_faturado));
  safeText("tv-mes-im", brl(v.mes_im));
  safeText("tv-mes-ag", brl(v.mes_ag));

  safeText("tv-dias", `${num(v.dias_uteis_trabalhados)} / ${num(v.dias_uteis_trabalhar)}`);
  safeText("tv-prod", `${Math.round(num(v.realizado_dia_util_pct) * 100)}%`);

  safeText("tv-deveria", brl(v.deveria_ate_hoje));
  safeText("tv-ating", `${Math.round(num(v.atingimento_mes_pct) * 100)}%`);

  safeText("tv-projecao", brl(v.fechar_em));
  safeText("tv-proj-pct", `${Math.round(num(v.equivale_pct) * 100)}%`);

  safeText("tv-hoje", brl(v.hoje_total));
  safeText("tv-hoje-fat", brl(v.hoje_faturado));
  safeText("tv-hoje-im", brl(v.hoje_im));
  safeText("tv-hoje-ag", brl(v.hoje_ag));

  safeText("tv-meta-dia", brl(v.a_faturar_dia_util));

  const diasRest = Math.max(0, num(v.dias_uteis_trabalhar) - num(v.dias_uteis_trabalhados));
  safeText("tv-dias-rest", diasRest);

  safeText("tv-restante", brl(v.falta_meta_mes));
  safeText("tv-meta-teo", brl(v.meta_dia_util));
  safeText("tv-updated", data.updated_at || "--");
  safeText("tv-updated-footer", data.updated_at || "--");

  const hojeTotal = num(v.hoje_total);
  const metaTeoDia = num(v.meta_dia_util);
  const gapHoje = hojeTotal - metaTeoDia;

  const gapEl = document.getElementById("tv-gap");
  if (gapEl) {
    gapEl.textContent = gapHoje >= 0 ? "Acima da meta do dia" : "Abaixo da meta do dia";
    gapEl.className = gapHoje >= 0 ? "kpi-green" : "kpi-red";
  }

  const metaBar = document.getElementById("meta-bar");
  const metaPct = document.getElementById("meta-pct");
  if (metaBar) {
    const pct = num(v.atingimento_mes_pct) * 100;
    metaBar.style.width = `${Math.min(pct, 100)}%`;
    if (metaPct) metaPct.textContent = `${Math.round(pct)}%`;
  }

  const metaDiaBar = document.getElementById("meta-dia-bar");
  const metaDiaPct = document.getElementById("meta-dia-pct");
  if (metaDiaBar) {
    const metaDinamicaDia = num(v.a_faturar_dia_util);
    const pct = metaDinamicaDia > 0 ? (hojeTotal / metaDinamicaDia) * 100 : 0;
    metaDiaBar.style.width = `${Math.min(pct, 100)}%`;
    if (metaDiaPct) metaDiaPct.textContent = `${Math.round(pct)}%`;
  }

  loadCharts(v);
}

async function loadDashboard(opts = { force: false, preferCache: false }) {
  const key = KEY_DASH(CURRENT_YM);

  setCardsLoading(true);

  if (opts.preferCache && !opts.force) {
    const cached = cacheGet(key);
    if (cached && isValidDashboardPayload(cached)) {
      applyDashboard(cached);
      setCardsLoading(false);
      return cached;
    }
  }

  try {
    const url = qsUrl(API, {
      dash: "executivo",
      force: opts.force ? 1 : undefined,
      _ts: Date.now()
    });

    const r = await fetch(url, { cache: "no-store" });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);

    const data = await r.json();
    if (!isValidDashboardPayload(data)) throw new Error("Payload inválido do dashboard");

    cacheSet(key, data);
    applyDashboard(data);
    setCardsLoading(false);
    return data;
  } catch (e) {
    console.warn("Erro dashboard:", e);

    const fallback = cacheGet(key);
    if (fallback && isValidDashboardPayload(fallback)) {
      applyDashboard(fallback);
      setCardsLoading(false);
      return fallback;
    }

    setCardsLoading(false);
    return null;
  }
}

/* ======================================================
   GRÁFICOS (mês / ano / ritmo)
====================================================== */
Chart.register(ChartDataLabels);

let chartMes = null;
let chartAno = null;
let chartRitmo = null;

function brlShort(n) {
  const v = Number(n || 0);
  if (v >= 1_000_000) {
    return "R$ " + (v / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " mi";
  }
  if (v >= 1_000) {
    return "R$ " + (v / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 0 }) + " mil";
  }
  return "R$ " + v.toLocaleString("pt-BR", { maximumFractionDigits: 0 });
}

function tickShort(n) {
  const v = Number(n || 0);
  if (v >= 1_000_000) {
    return (v / 1_000_000).toLocaleString("pt-BR", { maximumFractionDigits: 1 }) + " mi";
  }
  if (v >= 1_000) {
    return (v / 1_000).toLocaleString("pt-BR", { maximumFractionDigits: 0 }) + " mil";
  }
  return v.toLocaleString("pt-BR", { maximumFractionDigits: 0 });
}

const valueLabelPlugin = {
  id: "valueLabelPlugin",
  afterDatasetsDraw(chart) {
    const { ctx, chartArea } = chart;
    const dataset = chart.data.datasets?.[0];
    if (!dataset) return;

    const meta = chart.getDatasetMeta(0);
    if (!meta || !meta.data) return;

    const v = chart.$customValues || {};

    ctx.save();
    ctx.textAlign = "center";
    ctx.textBaseline = "bottom";
    ctx.fillStyle = "#1f2937";
    ctx.font = "700 12px Poppins, Arial, sans-serif";

    meta.data.forEach((bar, i) => {
      const raw = Number(dataset.data?.[i] ?? 0);
      if (!Number.isFinite(raw) || raw <= 0) return;

      const x = bar.x;
      const y = Math.max(bar.y - 8, chartArea.top + 14);
      const valueLabel = brlShort(raw);

      let pct = "";
      let showPct = false;

      if (chart.canvas.id === "chartMes" && i === 0) {
        const metaMes = Number(v.meta_mes || 0);
        if (metaMes > 0) {
          pct = Math.round((raw / metaMes) * 100) + "%";
          showPct = true;
        }
      }

      if (chart.canvas.id === "chartAno" && i === 0) {
        const metaAno = Number(v.meta_ano || 0);
        if (metaAno > 0) {
          pct = Math.round((raw / metaAno) * 100) + "%";
          showPct = true;
        }
      }

      if (chart.canvas.id === "chartRitmo" && i === 1) {
        const metaDia = Number(v.meta_dia_util || 0);
        if (metaDia > 0) {
          pct = Math.round((raw / metaDia) * 100) + "%";
          showPct = true;
        }
      }

      if (showPct && pct) {
        ctx.fillStyle = "#64748b";
        ctx.font = "700 11px Poppins, Arial, sans-serif";
        ctx.fillText(pct, x, y - 16);
      }

      ctx.fillStyle = "#1f2937";
      ctx.font = "700 12px Poppins, Arial, sans-serif";
      ctx.fillText(valueLabel, x, y);
    });

    ctx.restore();
  }
};

function destroyChart(instance) {
  if (!instance) return null;
  try {
    instance.destroy();
  } catch {
    // silencioso
  }
  return null;
}

function loadCharts(v) {
  const elMes = document.getElementById("chartMes");
  const elAno = document.getElementById("chartAno");
  const elRitmo = document.getElementById("chartRitmo");

  if (!elMes || !elAno || !elRitmo) return;

  const commonBarOptions = {
    devicePixelRatio: window.devicePixelRatio || 1,
    resizeDelay: 80,
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 450 },
    layout: {
      padding: {
        top: 42,
        right: 10,
        left: 10,
        bottom: 2
      }
    },
    plugins: {
      legend: { display: false },
      datalabels: false,
      tooltip: {
        backgroundColor: "#111827",
        titleColor: "#fff",
        bodyColor: "#fff",
        padding: 12,
        cornerRadius: 12,
        displayColors: false,
        callbacks: {
          label: (ctx) => brl(ctx.raw)
        }
      }
    },
    scales: {
      x: {
        grid: {
          display: false,
          drawBorder: false
        },
        ticks: {
          color: "#4b5563",
          font: {
            size: 12,
            weight: "600"
          }
        }
      },
      y: {
        beginAtZero: true,
        grace: "20%",
        grid: {
          color: "rgba(15,23,42,.08)",
          drawBorder: false
        },
        ticks: {
          color: "#6b7280",
          font: {
            size: 11,
            weight: "600"
          },
          callback: (value) => tickShort(value)
        }
      }
    }
  };

  const barDatasetBase = {
    borderSkipped: false,
    borderRadius: 16,
    barPercentage: 0.7,
    categoryPercentage: 0.72
  };

  const mesRealizado = num(v.realizado_ate_hoje);
  const mesMeta = num(v.meta_mes);

  const anoRealizado = num(v.realizado_ano_acum);
  const anoMeta = num(v.meta_ano);

  const metaDia = num(v.meta_dia_util);
  const hojePrincipal = num(v.hoje_total);

  chartMes = destroyChart(chartMes);
  chartAno = destroyChart(chartAno);
  chartRitmo = destroyChart(chartRitmo);

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
  chartMes.$customValues = v;

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
  chartAno.$customValues = v;

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
  chartRitmo.$customValues = v;
}

/* ======================================================
   FATURAMENTO DIÁRIO
====================================================== */
let chartDiario = null;

function normalizeDailyValue(v) {
  const n = Number(v);
  if (!Number.isFinite(n)) return 0;
  return Math.round(n * 100) / 100;
}

function dailyLabelFromYm(ym) {
  const [Y, M] = String(ym).split("-").map(Number);
  const meses = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
  const mm = Number.isFinite(M) ? meses[M - 1] : "--";
  return `${mm}/${String(Y).slice(-2)}`;
}

function applyDailyChart(payload) {
  if (!isValidDailyPayload(payload)) return;

  const diario = payload.diario_mes || {};
  const labels = Object.keys(diario).sort((a, b) => Number(a) - Number(b));
  const valores = labels.map((dia) => normalizeDailyValue(diario[dia]));

  const ctx = document.getElementById("chartDiario");
  if (!ctx) return;

  chartDiario = destroyChart(chartDiario);

  chartDiario = new Chart(ctx, {
    type: "line",
    data: {
      labels,
      datasets: [{
        label: "Faturamento",
        data: valores,
        borderColor: "#5c2c8c",
        backgroundColor: "rgba(92,44,140,.10)",
        fill: true,
        tension: 0.18,
        borderWidth: 3,
        pointRadius: 4,
        pointHoverRadius: 6,
        pointHitRadius: 10,
        pointBorderWidth: 2,
        pointBackgroundColor: "#ffffff",
        pointBorderColor: "#5c2c8c",
        spanGaps: true,
        datalabels: {
          anchor: "end",
          align: "top",
          offset: 8,
          color: "#1f2937",
          formatter: (v) => {
            const n = normalizeDailyValue(v);
            if (n >= 1_000_000) return (n / 1_000_000).toFixed(1).replace(".", ",") + "M";
            if (n >= 1_000) return (n / 1_000).toFixed(0) + "k";
            return String(Math.round(n));
          },
          font: { size: 14, weight: "700" },
          textStrokeColor: "#ffffff",
          textStrokeWidth: 4,
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
  if (titulo) {
    titulo.textContent = "Faturamento Diário (" + dailyLabelFromYm(CURRENT_YM) + ")";
  }
}

async function loadDailyChart(opts = { force: false, preferCache: false }) {
  const key = KEY_DAILY(CURRENT_YM);

  if (opts.preferCache && !opts.force) {
    const cached = cacheGet(key);
    if (cached && isValidDailyPayload(cached)) {
      applyDailyChart(cached);
      return cached;
    }
  }

  try {
    const url = qsUrl("/api/dashboard/dashboard-executivo-save.php", {
      ym: CURRENT_YM,
      force: opts.force ? 1 : undefined,
      _ts: Date.now()
    });

    const r = await fetch(url, { cache: "no-store" });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);

    const data = await r.json();
    if (data && data.success === false) throw new Error("Payload inválido");
    if (!isValidDailyPayload(data)) throw new Error("Payload diário inválido");

    cacheSet(key, data);
    applyDailyChart(data);
    return data;
  } catch (e) {
    console.warn("Erro gráfico diário:", e);

    const fallback = cacheGet(key);
    if (fallback && isValidDailyPayload(fallback)) {
      applyDailyChart(fallback);
      return fallback;
    }

    return null;
  }
}

/* ======================================================
   TOPS
====================================================== */
const TOP_N = 10;

function asNumber(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}

function moneyBR(v) {
  return asNumber(v).toLocaleString("pt-BR", {
    style: "currency",
    currency: "BRL"
  });
}

function escapeHtml(str) {
  return String(str ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function normalizeEntries(input) {
  if (!input) return [];

  if (Array.isArray(input)) {
    return input
      .map((it) => {
        if (Array.isArray(it)) return [it[0], it[1]];
        if (it && typeof it === "object") {
          return [it.name ?? it.label ?? it.key, it.value ?? it.val ?? it.total];
        }
        return [null, null];
      })
      .filter(([n]) => n != null && String(n).trim() !== "");
  }

  if (typeof input === "object") return Object.entries(input);

  return [];
}

function renderTopList(containerId, badgeId, input) {
  const wrap = document.getElementById(containerId);
  if (!wrap) return;

  const entries = normalizeEntries(input)
    .map(([name, val]) => [String(name), asNumber(val)])
    .filter(([name]) => name.trim() !== "")
    .sort((a, b) => b[1] - a[1]);

  const total = entries.reduce((acc, [, v]) => acc + v, 0);
  const max = entries.length ? entries[0][1] : 0;

  const badge = document.getElementById(badgeId);
  if (badge) {
    badge.textContent = entries.length
      ? `Top ${Math.min(TOP_N, entries.length)} / ${entries.length}`
      : "—";
  }

  wrap.innerHTML = "";

  if (!entries.length) {
    wrap.innerHTML = `<div style="opacity:.7;padding:8px 6px;">Sem dados</div>`;
    return;
  }

  for (let idx = 0; idx < entries.length; idx++) {
    const [name, val] = entries[idx];
    const rank = idx + 1;

    const pct = total > 0 ? (val / total) * 100 : 0;
    const width = max > 0 ? (val / max) * 100 : 0;

    const row = document.createElement("div");
    let cls = "top-item";

    if (rank === 1) cls += " is-top1";
    else if (rank === 2) cls += " is-top2";
    else if (rank === 3) cls += " is-top3";
    else if (rank <= TOP_N) cls += " is-top";

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
  const [Y, M] = String(ym).split("-").map(Number);
  const meses = ["Jan", "Fev", "Mar", "Abr", "Mai", "Jun", "Jul", "Ago", "Set", "Out", "Nov", "Dez"];
  const mm = Number.isFinite(M) ? meses[M - 1] : "--";
  return `${mm}/${String(Y).slice(-2)}`;
}

function applyTopsCarousel(prodPayload, cliPayload) {
  const upd = prodPayload?.updated_at || cliPayload?.updated_at || "--";

  safeText("topsPeriod", fmtYM(CURRENT_YM));
  safeText("topsUpdated", upd);

  renderTopList("listTopProdutos", "badgeTopProdutos", prodPayload?.top_produtos || null);

  const top50 = cliPayload?.ranking?.top50 || [];
  const clientesAsEntries = Array.isArray(top50)
    ? top50.map((x) => [x.cliente, x.valor])
    : null;

  renderTopList("listTopClientes", "badgeTopClientes", clientesAsEntries);
}

async function loadTopsCarousel(opts = { force: false, preferCache: false }) {
  const key = KEY_TOPS(CURRENT_YM);

  if (opts.preferCache && !opts.force) {
    const cached = cacheGet(key);
    if (cached && isValidTopsPayload(cached)) {
      applyTopsCarousel(cached.prod, cached.cli);
      return cached;
    }
  }

  try {
    const prodUrl = qsUrl("/api/dashboard/dashboard-executivo-save.php", {
      ym: CURRENT_YM,
      force: opts.force ? 1 : undefined,
      _ts: Date.now()
    });

    const cliUrl = qsUrl("/api/dashboard/clientes_insights.php", {
      ym: CURRENT_YM,
      _ts: Date.now()
    });

    const [rProd, rCli] = await Promise.all([
      fetch(prodUrl, { cache: "no-store" }),
      fetch(cliUrl, { cache: "no-store" })
    ]);

    if (!rProd.ok) throw new Error(`Produtos HTTP ${rProd.status}`);
    if (!rCli.ok) throw new Error(`Clientes HTTP ${rCli.status}`);

    const [prodPayload, cliPayload] = await Promise.all([
      rProd.json(),
      rCli.json()
    ]);

    const pack = { prod: prodPayload, cli: cliPayload };
    cacheSet(key, pack);

    applyTopsCarousel(prodPayload, cliPayload);
    return pack;
  } catch (e) {
    console.warn("Erro tops carousel:", e);

    const fallback = cacheGet(key);
    if (fallback && isValidTopsPayload(fallback)) {
      applyTopsCarousel(fallback.prod, fallback.cli);
      return fallback;
    }

    renderTopList("listTopProdutos", "badgeTopProdutos", null);
    renderTopList("listTopClientes", "badgeTopClientes", null);
    return null;
  }
}

/* ======================================================
   BOOT + REFRESH
====================================================== */
let dashboardTimer = null;
let topsTimer = null;
let dailyTimer = null;

function clearRefreshTimers() {
  if (dashboardTimer) clearTimeout(dashboardTimer);
  if (topsTimer) clearTimeout(topsTimer);
  if (dailyTimer) clearTimeout(dailyTimer);

  dashboardTimer = null;
  topsTimer = null;
  dailyTimer = null;
}

function scheduleRefresh() {
  clearRefreshTimers();

  dashboardTimer = setTimeout(() => {
    loadDashboard({ force: true, preferCache: false });
    dashboardTimer = setInterval(() => {
      loadDashboard({ force: true, preferCache: false });
    }, CACHE_TTL);
  }, msToNextRefresh(KEY_DASH(CURRENT_YM)));

  topsTimer = setTimeout(() => {
    loadTopsCarousel({ force: true, preferCache: false });
    topsTimer = setInterval(() => {
      loadTopsCarousel({ force: true, preferCache: false });
    }, CACHE_TTL);
  }, msToNextRefresh(KEY_TOPS(CURRENT_YM)));

  dailyTimer = setTimeout(() => {
    loadDailyChart({ force: true, preferCache: false });
    dailyTimer = setInterval(() => {
      loadDailyChart({ force: true, preferCache: false });
    }, CACHE_TTL);
  }, msToNextRefresh(KEY_DAILY(CURRENT_YM)));
}

async function bootDashboard() {
  setCardsLoading(true);

  await loadDashboard({ force: true, preferCache: false });
  await loadTopsCarousel({ force: true, preferCache: false });

  setTimeout(() => {
    loadDailyChart({ force: true, preferCache: false });
  }, 200);

  scheduleRefresh();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", bootDashboard, { once: true });
} else {
  bootDashboard();
}