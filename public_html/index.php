<?php
declare(strict_types=1);


require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

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
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Início — <?= h((string) APP_NAME) ?></title>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <link rel="stylesheet"
    href="/assets/css/apps/geo-vendas.css?v=<?= filemtime(__DIR__ . '/assets/css/apps/geo-vendas.css') ?>">
  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>">
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>">
  <link rel="stylesheet" href="/assets/css/carousel.css?v=<?= filemtime(__DIR__ . '/assets/css/carousel.css') ?>">
  <link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>">
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>">
</head>

<body class="page page--gav">

  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

  <main>
    <section class="carousel carousel--full full-bleed" id="mainCarousel">

      <button class="carousel__fullscreen" id="fullscreenBtn" aria-label="Tela cheia">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>

      <button class="carousel__arrow carousel__arrow--prev" id="prevBtn" aria-label="Anterior">
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
                  Dias úteis: <span id="tv-dias"></span>
                  · Produtividade: <span id="tv-prod"></span>
                </div>
              </div>

              <div class="dash-tv-card">
                <div class="kpi-label">Deveria ter até hoje</div>
                <div class="kpi-value" id="tv-deveria">--</div>

                <div class="kpi-detail">
                  Atingimento (mês): <span id="tv-ating"></span>
                </div>
              </div>

              <div class="dash-tv-card">
                <div class="kpi-label">Projeção de fechamento (mês)</div>
                <div class="kpi-value" id="tv-projecao">--</div>

                <div class="kpi-detail">
                  Projeção: <span id="tv-proj-pct"></span>
                </div>
              </div>

              <div class="dash-tv-card">
                <div class="kpi-label">Hoje</div>
                <div class="kpi-value" id="tv-hoje">--</div>

                <div class="kpi-detail">
                  Faturado: <span id="tv-hoje-fat"></span>
                  · Imediato p/hoje: <span id="tv-hoje-im"></span>
                  <br>
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
                  Restante no mês: <span id="tv-restante"></span>
                  <br>
                  Meta(Média Mensal)do dia: <span id="tv-meta-teo"></span>
                  <br>
                </div>
              </div>

            </div>

            <!-- ✅ DATA/HORA CENTRALIZADA (ABAIXO DOS CARDS) -->
            <div class="dash-tv-updated">
              Atualizado em <span id="tv-updated-footer">--</span>
            </div>

          </article>

          <!-- CHARTS SLIDE -->
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

              <div class="chart-card grid-col-span-3 exec-chart">
                <h3 class="chart-title" id="ttlChart">Faturamento Diário (mês)</h3>
                <div class="chart-box chart-box--daily">
                  <canvas id="chartDiario"></canvas>
                </div>
              </div>

            </div>
          </article>

          <!-- TOPS SLIDE -->
          <article class="slide slide--tops" data-id="tops">
            <div class="tops-slide">

              <div class="tops-slide__head">
                <h2 class="tops-slide__title">Tops do mês</h2>
                <div class="tops-slide__sub">
                  Período: <span id="topsPeriod">--</span> · Atualizado: <span id="topsUpdated">--</span>
                </div>
              </div>

              <div class="tops-row tops-row--carousel">

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
          <!-- GEO VENDAS SLIDE -->
          <article class="slide slide--geo-vendas" data-id="geo-vendas">
            <div id="geoVendasHome" data-geo-vendas-app data-endpoint="/api/dashboard/clientes_insights.php"
              data-geojson="/assets/maps/brasil-ufs.geojson" data-ym="<?= date('Y-m') ?>"
              data-title="Top Regiões e Mapa de Vendas" data-subtitle-prefix="Distribuição por UF e região"
              data-map-title="Mapa do Brasil por venda" data-regions-title="Top Regiões" data-states-title="Top Estados"
              style="height:100%; padding:18px 22px; box-sizing:border-box;">
            </div>
          </article>
          <!-- COMUNICADOS -->
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

      <button class="carousel__arrow carousel__arrow--next" id="nextBtn" aria-label="Próximo">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
            stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__dots" id="dots"></div>

    </section>
  </main>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
  <script src="/assets/js/header.js"></script>
  <script src="/assets/js/index-carousel.js"></script>
  <script src="/assets/js/index.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="/assets/js/apps/geo-vendas.js?v=<?= filemtime(__DIR__ . '/assets/js/apps/geo-vendas.js') ?>"></script>
  <script>
    document.addEventListener('DOMContentLoaded', async () => {
      try {
        const app = GeoVendasApp.create('#geoVendasHome');
        await app.load();

        window.addEventListener('resize', () => {
          app.refreshSize();
        });
      } catch (e) {
        console.error('Erro ao iniciar GeoVendasApp no index:', e);
      }
    });
  </script>
</body>

</html>