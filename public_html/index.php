<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

/* =========================================================
   ANTI-CACHE (TV Box / fullscreen)
========================================================= */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$activePage = 'home';

// =========================
// HELPERS
// =========================
function h(?string $s): string
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

// =========================
// COMUNICADOS
// =========================
$stmt = db()->prepare('
  SELECT id, titulo, conteudo, imagem_path
  FROM comunicados
  WHERE ativo = TRUE
  ORDER BY ordem ASC, id ASC
');
$stmt->execute();
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dashboards ativos
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}
?>
<!doctype html>
<html lang="pt-br">

<head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Início — <?= h((string) APP_NAME) ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>">
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>">
  <link rel="stylesheet" href="/assets/css/carousel.css?v=<?= filemtime(__DIR__ . '/assets/css/carousel.css') ?>">
  <link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>">
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>">

  <style>
    html,
    body {
      height: 100%;
      overflow: hidden;
    }

    body.page main {
      opacity: 0;
      transform: translateY(4px);
      transition: opacity .18s ease, transform .18s ease;
    }

    body.page.is-ready main {
      opacity: 1;
      transform: translateY(0);
    }

    .slide--dashboard {
      padding-top: 10px;
    }
  </style>

</head>

<body class="page page--gav">

  <?php require_once __DIR__ . '/app/header.php'; ?>

  <main>

    <section class="carousel carousel--full full-bleed" id="mainCarousel">

      <button class="carousel__fullscreen" id="fullscreenBtn" aria-label="Tela cheia">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>

      <button class="carousel__arrow carousel__arrow--prev" id="prevBtn">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__viewport">
        <div class="carousel__track" id="track">

          <!-- DASHBOARD SLIDE -->

          <article class="slide slide--dashboard" data-id="dashboard">

            <div class="dash-tv-grid">

              <div class="dash-tv-card">

                <div class="kpi-label">Meta do mês</div>
                <div class="kpi-value" id="tv-meta">--</div>
                <div class="meta-progress">
                  <div class="meta-progress-bar" id="meta-bar">
                    <span class="meta-progress-pct" id="meta-pct"></span>
                  </div>
                </div>

                <div class="kpi-sub">
                  Atualizado: <span id="tv-updated"></span>
                </div>

                <div class="kpi-detail">
                  Realizado: <span id="tv-realizado"></span> ·
                  Falta: <span id="tv-falta"></span>
                </div>

              </div>


              <div class="dash-tv-card">

                <div class="kpi-label">Vendas do mês (atual)</div>
                <div class="kpi-value" id="tv-mes">--</div>

                <div class="kpi-detail">

                  Faturado: <span id="tv-mes-fat"></span> ·
                  Imediato: <span id="tv-mes-im"></span>

                  <br>

                  Agendado: <span id="tv-mes-ag"></span>

                  <br>

                  Dias úteis:
                  <span id="tv-dias"></span>

                  · Produtividade:
                  <span id="tv-prod"></span>

                </div>

              </div>


              <div class="dash-tv-card">

                <div class="kpi-label">Deveria ter até hoje</div>

                <div class="kpi-value" id="tv-deveria">--</div>

                <div class="kpi-detail">

                  Atingimento (mês):
                  <span id="tv-ating"></span>

                </div>

              </div>


              <div class="dash-tv-card">

                <div class="kpi-label">Projeção de fechamento (mês)</div>

                <div class="kpi-value" id="tv-projecao">--</div>

                <div class="kpi-detail">

                  Proj:
                  <span id="tv-proj-pct"></span>

                </div>

              </div>


              <div class="dash-tv-card">

                <div class="kpi-label">Hoje</div>

                <div class="kpi-value" id="tv-hoje">--</div>

                <div class="kpi-detail">

                  Faturado:
                  <span id="tv-hoje-fat"></span>

                  · Imediato p/hoje :
                  <span id="tv-hoje-im"></span>

                  <br>

                  Agendado:
                  <span id="tv-hoje-ag"></span>

                </div>

              </div>


              <div class="dash-tv-card">

                <div class="kpi-value" id="tv-meta-dia">--</div>

                <div class="meta-progress">
                  <div class="meta-progress-bar meta-dia-bar" id="meta-dia-bar">
                    <span class="meta-progress-pct" id="meta-dia-pct"></span>
                  </div>
                </div>

                <div class="kpi-label">Meta Dinâmica do dia</div>

                <div class="kpi-detail">

                  Faltam <span id="tv-dias-rest"></span> dias úteis

                  <br>

                  Restante no mês:
                  <span id="tv-restante"></span>

                  <br>

                  Meta(Média Mensal)do dia:
                  <span id="tv-meta-teo"></span>

                  <br>

                  <span id="tv-gap"></span>

                </div>

              </div>

            </div>

          </article>

          <article class="slide slide--charts" data-id="charts">

            <div class="charts-grid">

              <div class="chart-card">
                <h3>Progresso (Mês)</h3>
                <div class="chart-box chart-box--small">
                  <canvas id="chartMes"></canvas>
                </div>
              </div>

              <div class="chart-card">
                <h3>Progresso (Ano)</h3>
                <div class="chart-box chart-box--small">
                  <canvas id="chartAno"></canvas>
                </div>
              </div>

              <div class="chart-card">
                <h3>Ritmo (Dia útil)</h3>
                <div class="chart-box chart-box--small">
                  <canvas id="chartRitmo"></canvas>
                </div>
              </div>

              <!-- Gráfico -->
              <div class="chart-card grid-col-span-3 exec-chart">
                <h3 class="chart-title" id="ttlChart">Faturamento Diário (mês)</h3>
                <div class="chart-box">
                  <canvas id="chartDiario"></canvas>
                </div>
              </div>

            </div>

          </article>

          <!-- TOPS SLIDE (3ª PÁGINA FIXA) -->
          <article class="slide slide--tops" data-id="tops">

            <div class="tops-slide">

              <div class="tops-slide__head">
                <h2 class="tops-slide__title">Tops do mês</h2>
                <div class="tops-slide__sub">
                  Período: <span id="topsPeriod">--</span> · Atualizado: <span id="topsUpdated">--</span>
                </div>
              </div>

              <div class="tops-row tops-row--carousel">

                <!-- TOP PRODUTOS -->
                <div class="data-table-card top-card">
                  <div class="top-head">
                    <div>
                      <h3 class="table-title">Top Produtos</h3>
                      <div class="top-sub">(scroll para ver todos)</div>
                    </div>
                    <div class="top-badge" id="badgeTopProdutos">—</div>
                  </div>
                  <div class="top-list" id="listTopProdutos"></div>
                </div>

                <!-- TOP CLIENTES -->
                <div class="data-table-card top-card">
                  <div class="top-head">
                    <div>
                      <h3 class="table-title">Top Clientes</h3>
                      <div class="top-sub">(scroll para ver todos)</div>
                    </div>
                    <div class="top-badge" id="badgeTopClientes">—</div>
                  </div>
                  <div class="top-list" id="listTopClientes"></div>
                </div>

              </div>

            </div>

          </article>

          <?php foreach ($comunicados as $c): ?>

            <?php
            $id = (int) ($c['id'] ?? 0);
            $img = trim((string) ($c['imagem_path'] ?? ''));
            $titulo = trim((string) ($c['titulo'] ?? ''));
            $conteudo = trim((string) ($c['conteudo'] ?? ''));
            $hasImage = ($img !== '');
            ?>

            <?php if ($hasImage): ?>

              <article class="slide slide--image" data-id="<?= $id ?>">
                <div class="slide__inner">
                  <img class="slide__img-full" src="<?= h($img) ?>" alt="Comunicado">
                </div>
              </article>

            <?php else: ?>

              <article class="slide slide--text" data-id="<?= $id ?>">

                <div class="slide__doc">

                  <?php if ($titulo): ?>
                    <div class="doc__title"><?= h($titulo) ?></div>
                  <?php endif; ?>

                  <?php if ($conteudo): ?>
                    <div class="doc__body"><?= nl2br(h($conteudo)) ?></div>
                  <?php endif; ?>

                </div>

              </article>

            <?php endif; ?>

          <?php endforeach; ?>

        </div>
      </div>

      <button class="carousel__arrow carousel__arrow--next" id="nextBtn">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__dots" id="dots"></div>

    </section>

  </main>

  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>

  <script src="/assets/js/header.js"></script>
  <script src="/assets/js/dropdowns.js"></script>
  <script src="/assets/js/index-carousel.js"></script>
  

<script>
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

/* ======================================================
   BASE / HELPERS
====================================================== */
const now = new Date();
const CURRENT_YM = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");

function brl(v) {
  return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(Number(v || 0));
}
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

/* ======================================================
   AUTO SCROLL TOPS
   - desce por 30s, volta topo instantâneo, repete
   - só roda quando slide "tops" está ativo/visível
====================================================== */
const _autoScrollHandles = new Map();

function stopAutoScroll(containerId) {
  const h = _autoScrollHandles.get(containerId);
  if (!h) return;
  h.stop = true;
  if (h.raf) cancelAnimationFrame(h.raf);
  _autoScrollHandles.delete(containerId);
}
function stopAllAutoScroll() {
  stopAutoScroll("listTopProdutos");
  stopAutoScroll("listTopClientes");
}
function isTopsSlideActive() {
  const slide = document.querySelector('.slide[data-id="tops"]');
  if (!slide) return true;
  if (slide.classList.contains("is-active")) return true; // se seu carousel seta isso
  const r = slide.getBoundingClientRect();
  return r.width > 40 && r.height > 40 && r.bottom > 0 && r.right > 0;
}

/**
 * Loop:
 * - por durationMs (30s) ele desce suave até "Top 20"
 * - no final volta pro topo de uma vez e reinicia
 */
function startAutoScrollLoop(containerId, durationMs = 30000, opts = {}) {
  stopAutoScroll(containerId);

  const el = document.getElementById(containerId);
  if (!el) return;

  const {
    pxPerSecond = 14, // 🔽 velocidade (Top Produtos mais lento)
    maxItems = 20,    // até Top 20
  } = opts;

  const h = { stop: false, raf: 0, t0: 0 };
  _autoScrollHandles.set(containerId, h);

  const tick = (ts) => {
    if (h.stop) return;

    const list = document.getElementById(containerId);
    if (!list) return;

    // só rola quando o slide está ativo/visível
    if (!isTopsSlideActive()) {
      h.raf = requestAnimationFrame(tick);
      return;
    }

    const first = list.querySelector(".top-item");
    const itemH = first ? Math.max(1, first.offsetHeight) : 0;

    const hardMax = Math.max(0, list.scrollHeight - list.clientHeight);
    if (hardMax <= 2) {
      list.scrollTop = 0;
      h.raf = requestAnimationFrame(tick);
      return;
    }

    const target = itemH ? Math.min(hardMax, itemH * maxItems) : hardMax;

    if (!h.t0) h.t0 = ts;
    const elapsed = ts - h.t0;

    if (elapsed >= durationMs) {
      // volta topo instantâneo e reinicia
      list.scrollTop = 0;
      h.t0 = ts;
      h.raf = requestAnimationFrame(tick);
      return;
    }

    // desce suave por velocidade (px/s), travando no target
    const desired = (elapsed / 1000) * pxPerSecond;
    list.scrollTop = Math.min(desired, target);

    h.raf = requestAnimationFrame(tick);
  };

  h.raf = requestAnimationFrame(tick);
}

function startTopAutoScroll() {
  stopAllAutoScroll();

  const a = document.getElementById("listTopProdutos");
  const b = document.getElementById("listTopClientes");
  if (a) a.scrollTop = 0;
  if (b) b.scrollTop = 0;

  // ✅ ajuste fino aqui
  startAutoScrollLoop("listTopProdutos", 30000, { pxPerSecond: 12, maxItems: 20 });
  startAutoScrollLoop("listTopClientes", 30000, { pxPerSecond: 16, maxItems: 20 });
}

/* ======================================================
   CARREGA KPIs
====================================================== */
const API = "/api/dashboard-data.php?dash=executivo";

async function loadDashboard() {
  try {
    const r = await fetch(API + "&_=" + Date.now(), { cache: "no-store" });
    const data = await r.json();
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
    set("tv-dias-rest", v.dias_uteis_trabalhar - v.dias_uteis_trabalhados);
    set("tv-restante", brl(v.falta_meta_mes));
    set("tv-meta-teo", brl(v.meta_dia_util));

    set("tv-updated", data.updated_at || "--");

    const gap = (v.realizado_dia_util || 0) - (v.meta_dia_util || 0);
    const gapEl = document.getElementById("tv-gap");
    if (gapEl) {
      gapEl.textContent = gap >= 0 ? "Acima da meta do dia" : "Abaixo da meta do dia";
      gapEl.className = gap >= 0 ? "kpi-green" : "kpi-red";
    }

    const metaBar = document.getElementById("meta-bar");
    const metaPct = document.getElementById("meta-pct");
    if (metaBar) {
      const pct = (v.atingimento_mes_pct || 0) * 100;
      metaBar.style.width = Math.min(pct, 100) + "%";
      if (metaPct) metaPct.textContent = Math.round(pct) + "%";
    }

    const metaDiaBar = document.getElementById("meta-dia-bar");
    const metaDiaPct = document.getElementById("meta-dia-pct");
    if (metaDiaBar) {
      const realizado = v.realizado_dia_util || 0;
      const meta = v.a_faturar_dia_util || 0;
      let pct = 0;
      if (meta > 0) pct = (realizado / meta) * 100;
      metaDiaBar.style.width = Math.min(pct, 100) + "%";
      if (metaDiaPct) metaDiaPct.textContent = Math.round(pct) + "%";
    }

    loadCharts(v);
  } catch (e) {
    console.warn("Erro dashboard:", e);
  }
}

/* ======================================================
   GRÁFICOS
====================================================== */
if (window.Chart && window.ChartDataLabels) Chart.register(ChartDataLabels);

let chartMes, chartAno, chartRitmo;

function loadCharts(v) {
  if (!window.Chart) return;

  const commonBarOptions = {
    resizeDelay: 80,
    responsive: true,
    maintainAspectRatio: false,
    animation: { duration: 350 },
    plugins: {
      legend: { display: false },
      datalabels: {
        color: "#ffffff",
        textStrokeColor: "rgba(0,0,0,.35)",
        textStrokeWidth: 3,
        anchor: "center",
        align: "center",
        clamp: true,
        clip: true,
        font: { weight: "700", size: 12 },
        formatter: (val) => brlShort(val)
      }
    },
    scales: {
      y: { beginAtZero: true, ticks: { callback: (value) => tickShort(value) } }
    }
  };

  const barDatasetBase = {
    borderSkipped: "bottom",
    borderRadius: { topLeft: 14, topRight: 14, bottomLeft: 0, bottomRight: 0 }
  };

  const elMes = document.getElementById("chartMes");
  const elAno = document.getElementById("chartAno");
  const elRit = document.getElementById("chartRitmo");

  if (elMes) {
    if (chartMes) chartMes.destroy();
    chartMes = new Chart(elMes, {
      type: "bar",
      data: {
        labels: ["Realizado", "Meta"],
        datasets: [{
          ...barDatasetBase,
          data: [v.realizado_ate_hoje || 0, v.meta_mes || 0],
          backgroundColor: ["#6b46c1", "#b5d334"]
        }]
      },
      options: commonBarOptions
    });
  }

  if (elAno) {
    if (chartAno) chartAno.destroy();
    chartAno = new Chart(elAno, {
      type: "bar",
      data: {
        labels: ["Realizado", "Meta"],
        datasets: [{
          ...barDatasetBase,
          data: [v.realizado_ano_acum || 0, v.meta_ano || 0],
          backgroundColor: ["#6b46c1", "#b5d334"]
        }]
      },
      options: commonBarOptions
    });
  }

  if (elRit) {
    if (chartRitmo) chartRitmo.destroy();
    chartRitmo = new Chart(elRit, {
      type: "bar",
      data: {
        labels: ["Meta/dia", "Realizado/dia"],
        datasets: [{
          ...barDatasetBase,
          data: [v.meta_dia_util || 0, v.realizado_dia_util || 0],
          backgroundColor: ["#f59e0b", "#22c55e"]
        }]
      },
      options: {
        ...commonBarOptions,
        plugins: {
          ...commonBarOptions.plugins,
          datalabels: {
            anchor: "center",
            align: "center",
            clamp: true,
            clip: true,
            color: "#ffffff",
            textStrokeColor: "rgba(0,0,0,.35)",
            textStrokeWidth: 3,
            font: { weight: "800", size: 12 },
            display: (ctx) => {
              const val = ctx.dataset?.data?.[ctx.dataIndex];
              const n = Number(val);
              return Number.isFinite(n) && n > 0;
            },
            formatter: (val) => brlShort(val)
          }
        }
      }
    });
  }
}

/* ======================================================
   FATURAMENTO DIÁRIO
====================================================== */
let chartDiario;

async function loadDailyChart() {
  try {
    if (!window.Chart) return;

    const r = await fetch("/api/dashboard-executivo-save.php?ym=" + CURRENT_YM + "&_=" + Date.now(), { cache: "no-store" });
    const data = await r.json();
    if (!data || !data.diario_mes) return;

    const labels = Object.keys(data.diario_mes).sort((a, b) => Number(a) - Number(b));
    const valores = Object.values(data.diario_mes);

    const el = document.getElementById("chartDiario");
    if (!el) return;

    if (chartDiario) chartDiario.destroy();

    chartDiario = new Chart(el, {
      type: "line",
      data: {
        labels,
        datasets: [{
          label: "Faturamento",
          data: valores,
          borderColor: "#5c2c8c",
          backgroundColor: "rgba(92,44,140,.12)",
          fill: true,
          tension: .35,
          pointRadius: 4,
          pointHoverRadius: 6,
          datalabels: {
            anchor: "end",
            align: "top",
            offset: 10,
            color: "#1f2937",
            formatter: (v) => {
              const n = Number(v || 0);
              if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + "M";
              if (n >= 1_000) return (n / 1_000).toFixed(0) + "k";
              return n.toLocaleString("pt-BR");
            },
            font: { size: 15, weight: "700" },
            textStrokeColor: "#ffffff",
            textStrokeWidth: 3
          }
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: { padding: { top: 30 } },
        plugins: {
          legend: { display: false },
          datalabels: { anchor: "end", align: "top", offset: 10, clamp: true, clip: false }
        },
        scales: { y: { beginAtZero: true } }
      }
    });

    const mes = now.toLocaleString("pt-BR", { month: "short" });
    const ano = now.getFullYear();
    const titulo = document.getElementById("ttlChart");
    if (titulo) titulo.textContent = "Faturamento Diário (" + mes + "/" + ano + ")";
  } catch (e) {
    console.warn("Erro gráfico diário", e);
  }
}

/* ======================================================
   TOPS (Slide 3)
====================================================== */
const TOP_N = 10;

function asNumber(v) {
  const n = Number(v);
  return Number.isFinite(n) ? n : 0;
}
function moneyBR(v) {
  return asNumber(v).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
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
      .map(it => {
        if (Array.isArray(it)) return [it[0], it[1]];
        if (it && typeof it === "object") return [it.name ?? it.label ?? it.key, it.value ?? it.val ?? it.total];
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
  if (badge) badge.textContent = entries.length ? `Top ${Math.min(TOP_N, entries.length)} / ${entries.length}` : "—";

  wrap.innerHTML = "";
  wrap.scrollTop = 0;

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

async function loadTopsCarousel() {
  try {
    const ym = CURRENT_YM;

    const rProd = await fetch("/api/dashboard-executivo-save.php?ym=" + ym + "&_=" + Date.now(), { cache: "no-store" });
    const prodPayload = await rProd.json();

    const rCli = await fetch("/api/clientes_insights.php?ym=" + ym + "&_=" + Date.now(), { cache: "no-store" });
    const cliPayload = await rCli.json();

    const upd = prodPayload?.updated_at || cliPayload?.updated_at || "--";

    const per = document.getElementById("topsPeriod");
    if (per) per.textContent = fmtYM(ym);

    const upEl = document.getElementById("topsUpdated");
    if (upEl) upEl.textContent = upd;

    renderTopList("listTopProdutos", "badgeTopProdutos", prodPayload?.top_produtos || null);

    const top50 = cliPayload?.ranking?.top50 || [];
    const clientesAsEntries = Array.isArray(top50) ? top50.map(x => [x.cliente, x.valor]) : null;
    renderTopList("listTopClientes", "badgeTopClientes", clientesAsEntries);

    // ✅ reinicia o auto-scroll sempre que renderizar
    setTimeout(startTopAutoScroll, 180);

  } catch (e) {
    console.warn("Erro tops carousel:", e);
    renderTopList("listTopProdutos", "badgeTopProdutos", null);
    renderTopList("listTopClientes", "badgeTopClientes", null);
    stopAllAutoScroll();
  }
}

/* ======================================================
   BOOT
====================================================== */
(function boot() {
  const run = async () => {
    await loadDashboard();
    await loadTopsCarousel();
    setTimeout(loadDailyChart, 800);
  };

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", run, { once: true });
  } else {
    run();
  }

  setInterval(loadTopsCarousel, 1800000);
  setInterval(loadDashboard, 1800000);
  setInterval(loadDailyChart, 1800000);
})();
</script>

</body>

</html>