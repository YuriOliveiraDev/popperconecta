<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$activePage = 'home';

function h(?string $s): string
{
  return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

$stmt = db()->prepare('
  SELECT id, titulo, conteudo, imagem_path
  FROM comunicados
  WHERE ativo = TRUE
  ORDER BY ordem ASC, id ASC
');
$stmt->execute();
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
  <link rel="stylesheet" href="/assets/css/tv.css?v=<?= filemtime(__DIR__ . '/assets/css/tv.css') ?>">
</head>

<body class="page page--gav">
  <main>
    <section class="carousel carousel--full full-bleed" id="mainCarousel">

      <button class="carousel__fullscreen" id="fullscreenBtn" aria-label="Tela cheia">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <button class="carousel__arrow carousel__arrow--prev" id="prevBtn" aria-label="Anterior">
        <svg viewBox="0 0 24 24" fill="none">
          <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__viewport">
        <div class="carousel__track" id="track">

          <!-- DASHBOARD -->
          <article class="slide slide--dashboard" data-id="dashboard">
            <div class="dash-tv-wrap">

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
                    Atualizado: <span id="tv-updated">--</span>
                  </div>

                  <div class="kpi-detail">
                    Realizado: <span id="tv-realizado">--</span> ·
                    Falta: <span id="tv-falta">--</span>
                  </div>
                </div>

                <div class="dash-tv-card">
                  <div class="kpi-label">Vendas do mês (atual)</div>
                  <div class="kpi-value" id="tv-mes">--</div>

                  <div class="kpi-detail">
                    Faturado: <span id="tv-mes-fat">--</span> ·
                    Imediato: <span id="tv-mes-im">--</span>
                    <br>
                    Agendado: <span id="tv-mes-ag">--</span>
                    <br>
                    Dias úteis: <span id="tv-dias">--</span>
                    · Produtividade: <span id="tv-prod">--</span>
                  </div>
                </div>

                <div class="dash-tv-card">
                  <div class="kpi-label">Deveria ter até hoje</div>
                  <div class="kpi-value" id="tv-deveria">--</div>

                  <div class="kpi-detail">
                    Atingimento (mês): <span id="tv-ating">--</span>
                  </div>
                </div>

                <div class="dash-tv-card">
                  <div class="kpi-label">Projeção de fechamento (mês)</div>
                  <div class="kpi-value" id="tv-projecao">--</div>

                  <div class="kpi-detail">
                    Projeção: <span id="tv-proj-pct">--</span>
                  </div>
                </div>

                <div class="dash-tv-card">
                  <div class="kpi-label">Hoje</div>
                  <div class="kpi-value" id="tv-hoje">--</div>

                  <div class="kpi-detail">
                    Faturado: <span id="tv-hoje-fat">--</span>
                    · Imediato p/hoje: <span id="tv-hoje-im">--</span>
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
                    Faltam <span id="tv-dias-rest">--</span> dias úteis
                    <br>
                    Restante no mês: <span id="tv-restante">--</span>
                    <br>
                    Meta (média mensal) do dia: <span id="tv-meta-teo">--</span>
                    <br>
                  </div>
                </div>

              </div>

              <div class="chart-card chart-wide exec-chart">
                <h3 class="chart-title" id="ttlChart">Faturamento Diário (mês)</h3>
                <div class="chart-box chart-box--daily">
                  <canvas id="chartDiario"></canvas>
                </div>
              </div>

              <div class="dash-tv-updated">
                Atualizado em <span id="tv-updated-footer">--</span>
              </div>

            </div>
          </article>

          <!-- CHARTS -->
          <article class="slide slide--charts" data-id="charts">
            <div class="charts-grid charts-grid--full">

              <div class="chart-card chart-card--full">
                <h3 class="chart-title" id="titleProgressMonth">Progresso (Mês)</h3>
                <div class="chart-box chart-box--full">
                  <canvas id="salesExpensesChartMonth"></canvas>
                </div>
              </div>

              <div class="chart-card chart-card--full">
                <h3 class="chart-title" id="titleProgressYear">Progresso (Ano)</h3>
                <div class="chart-box chart-box--full">
                  <canvas id="salesExpensesChartYear"></canvas>
                </div>
              </div>

              <div class="chart-card chart-card--full">
                <h3 class="chart-title" id="titlePace">Ritmo (Dia útil)</h3>
                <div class="chart-box chart-box--full">
                  <canvas id="salesBySectorChart"></canvas>
                </div>
              </div>

            </div>
          </article>

          <!-- TOPS -->
          <article class="slide slide--tops" data-id="tops">
            <div class="tops-slide">
              <div class="tops-row tops-row--carousel">

                <div class="data-table-card top-card">
                  <div class="top-head">
                    <div>
                      <h3 class="table-title">Top Produtos</h3>
                      <div class="top-sub"><span id="topsUpdated">--</span></div>
                    </div>
                    <div class="top-badge" id="badgeTopProdutos">Top 50</div>
                  </div>
                  <div class="top-list" id="listTopProdutos"></div>
                </div>

                <div class="data-table-card top-card">
                  <div class="top-head">
                    <div>
                      <h3 class="table-title">Top Clientes</h3>
                      <div class="top-sub">(scroll automático)</div>
                    </div>
                    <div class="top-badge" id="badgeTopClientes">Top 50</div>
                  </div>
                  <div class="top-list" id="listTopClientes"></div>
                </div>

              </div>
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
          <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__dots" id="dots"></div>
    </section>
  </main>

  <script>
    (function () {
      const goReady = () => document.body.classList.add('is-ready');
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', goReady, { once: true });
      } else {
        goReady();
      }
      window.DASH_CURRENT = 'executivo';
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>
  <script src="/assets/js/tv.js?v=<?= filemtime(__DIR__ . '/assets/js/tv.js') ?>"></script>
</body>
</html>