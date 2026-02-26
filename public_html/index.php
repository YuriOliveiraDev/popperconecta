<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/db.php';

require_login();

$u = current_user();
$activePage = 'home';

// =========================
// HELPERS
// =========================
function brl(float $v): string {
  return 'R$ ' . number_format($v, 2, ',', '.');
}
function h(?string $s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// =========================
// COMUNICADOS
// =========================
$stmt = db()->prepare('SELECT titulo, conteudo, imagem_path FROM comunicados WHERE ativo = TRUE ORDER BY ordem ASC, id ASC');
$stmt->execute();
$comunicados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dashboards ativos (para o dropdown "Dashboards")
try {
  $dashboards = db()
    ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
    ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $dashboards = null;
}

// =========================
// INSIGHT (server-side)
// =========================
$insight = null;

try {
  $dashboard_slug = 'executivo';

  $stmt = db()->prepare("
    SELECT metric_key, metric_value_num, metric_value_text, updated_at
    FROM metrics
    WHERE dashboard_slug = ?
    ORDER BY updated_at DESC
  ");
  $stmt->execute([$dashboard_slug]);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if ($rows) {
    $m = [];
    $latestUpdatedAt = null;

    foreach ($rows as $r) {
      $ua = isset($r['updated_at']) ? (string)$r['updated_at'] : null;
      if ($ua && ($latestUpdatedAt === null || strtotime($ua) > strtotime((string)$latestUpdatedAt))) {
        $latestUpdatedAt = $ua;
      }

      $key = (string)($r['metric_key'] ?? '');
      if ($key === '') continue;

      $txt = $r['metric_value_text'] ?? null;
      $num = $r['metric_value_num'] ?? null;

      if ($txt !== null && $txt !== '') {
        $m[$key] = (string)$txt;
      } else {
        $m[$key] = (float)($num ?? 0);
      }
    }

    // --- cálculos ---
    $meta_mes = (float)($m['meta_mes'] ?? 0);
    $realizado_mes = (float)($m['realizado_ate_hoje'] ?? 0);
    $falta_mes = max(0, $meta_mes - $realizado_mes);
    $atingimento_mes_pct = ($meta_mes > 0) ? ($realizado_mes / $meta_mes) : 0;

    $dias_totais = (int)($m['dias_uteis_trabalhar'] ?? 0);
    if ($dias_totais <= 0) $dias_totais = 1;

    $dias_passados = (int)($m['dias_uteis_trabalhados'] ?? 0);

    $realizado_dia_util = ($dias_passados > 0) ? ($realizado_mes / $dias_passados) : 0;
    $projecao_fechamento = $realizado_dia_util * $dias_totais;

    $equivale_pct = ($meta_mes > 0) ? ($projecao_fechamento / $meta_mes) : 0;
    $vai_bater = ($projecao_fechamento >= $meta_mes) ? "SIM" : "NÃO";

    $meta_dia_util = ($dias_totais > 0) ? ($meta_mes / $dias_totais) : 0;

    $deveria_ate_hoje = ($dias_totais > 0)
      ? ($meta_mes * ($dias_passados / $dias_totais))
      : 0;

    $gap_vs_deveria = $realizado_mes - $deveria_ate_hoje; // >0 acima do ritmo
    $ritmo_pct = ($meta_dia_util > 0) ? ($realizado_dia_util / $meta_dia_util) : 0;

    $dias_restantes = max(0, $dias_totais - $dias_passados);
    $a_faturar_por_dia = ($dias_restantes > 0) ? ($falta_mes / $dias_restantes) : $falta_mes;

    // hoje (faturado + agendado)
    $hoje = date('Y-m-d');
    $stmtDaily = db()->prepare("
      SELECT faturado_dia, agendado_hoje
      FROM dashboard_daily
      WHERE dash_slug = ? AND ref_date = ?
      LIMIT 1
    ");
    $stmtDaily->execute([$dashboard_slug, $hoje]);
    $dailyRow = $stmtDaily->fetch(PDO::FETCH_ASSOC) ?: [];

    $faturado_hoje = (float)($dailyRow['faturado_dia'] ?? 0);
    $agendado_hoje = (float)($dailyRow['agendado_hoje'] ?? 0);
    $hoje_total = $faturado_hoje + $agendado_hoje;

    if ($meta_mes > 0 || $realizado_mes > 0) {
      $insight = [
        'updated_at' => $latestUpdatedAt ? date('d/m/Y, H:i', strtotime($latestUpdatedAt)) : date('d/m/Y, H:i'),

        'meta_mes' => $meta_mes,
        'realizado_ate_hoje' => $realizado_mes,
        'falta_meta_mes' => $falta_mes,
        'atingimento_mes_pct' => $atingimento_mes_pct,

        'dias_uteis_trabalhar' => $dias_totais,
        'dias_uteis_trabalhados' => $dias_passados,
        'vai_bater_meta' => $vai_bater,

        'fechar_em' => $projecao_fechamento,
        'equivale_pct' => $equivale_pct,

        'a_faturar_dia_util' => $a_faturar_por_dia,
        'meta_dia_util' => $meta_dia_util,

        'deveria_ate_hoje' => $deveria_ate_hoje,
        'gap_vs_deveria' => $gap_vs_deveria,
        'ritmo_pct' => $ritmo_pct,
        'dias_restantes' => $dias_restantes,

        'hoje_total' => $hoje_total,
        'faturado_hoje' => $faturado_hoje,
        'agendado_hoje' => $agendado_hoje,

        'realizado_dia_util_calc' => $realizado_dia_util,
      ];
    }
  }
} catch (Throwable $e) {
  $insight = null;
}

// =========================
// PRÉ-CÁLCULOS PARA GRÁFICOS (SVG)
// =========================
$chart = null;
if ($insight) {
  $pctAting = max(0, min(100, (float)$insight['atingimento_mes_pct'] * 100));
  $pctEquiv = max(0, min(300, (float)$insight['equivale_pct'] * 100));
  $pctRitmo = max(0, min(300, (float)$insight['ritmo_pct'] * 100));

  $real = (float)$insight['realizado_ate_hoje'];
  $meta = (float)$insight['meta_mes'];
  $falta = max(0, $meta - $real);

  $maxProg = max(1.0, $meta);
  $realW = (int)round(100 * ($real / $maxProg));
  $realW = max(0, min(100, $realW));
  $faltaW = max(0, 100 - $realW);

  $realDia = (float)($insight['realizado_dia_util_calc'] ?? 0);
  $metaDia = (float)($insight['meta_dia_util'] ?? 0);

  $maxRitmo = max(1.0, $realDia, $metaDia);
  $wRealDia = (int)round(100 * ($realDia / $maxRitmo));
  $wMetaDia = (int)round(100 * ($metaDia / $maxRitmo));

  $chart = [
    'pctAting' => $pctAting,
    'pctEquiv' => $pctEquiv,
    'pctRitmo' => $pctRitmo,
    'real' => $real,
    'meta' => $meta,
    'falta' => $falta,
    'realW' => $realW,
    'faltaW' => $faltaW,
    'realDia' => $realDia,
    'metaDia' => $metaDia,
    'wRealDia' => max(0, min(100, $wRealDia)),
    'wMetaDia' => max(0, min(100, $wMetaDia)),
  ];
}
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Início — <?= h((string)APP_NAME) ?></title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
  <link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
  <link rel="stylesheet" href="/assets/css/carousel.css?v=<?= filemtime(__DIR__ . '/assets/css/carousel.css') ?>" />
  <link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" />
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />

  <style>
    html, body { height:100%; overflow:hidden; }

    /* garante que o slide 1 (dashboard) tenha respiro e não “cole” no header */
    .slide--dashboard {
      padding-top: 10px;
    }
  </style>
</head>

<body class="page">
  <?php require_once __DIR__ . '/app/header.php'; ?>

  <main>
    <section class="carousel carousel--full full-bleed" id="mainCarousel">
      <button class="carousel__arrow carousel__arrow--prev" type="button" id="prevBtn" aria-label="Anterior">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M15 18l-6-6 6-6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__viewport">
        <div class="carousel__track" id="track">

          <!-- =====================================================
               SLIDE 1 FIXO: DASHBOARD (INSIGHT)
               ===================================================== -->
          <article class="slide slide--dashboard" data-fixed="1" aria-label="Dashboard">
            <?php if ($insight): ?>
              <?php
                $pctAtingLocal = max(0, min(100, (float)$insight['atingimento_mes_pct'] * 100));
                $pctEquivLocal = max(0, min(999, (float)$insight['equivale_pct'] * 100));
                $ok = ((string)$insight['vai_bater_meta'] === 'SIM');
                $cls = $ok ? '' : 'insight--warn';

                $pctRitmoLocal = max(0, min(300, (float)$insight['ritmo_pct'] * 100));
                $gap = (float)($insight['gap_vs_deveria'] ?? 0);
                $gapOk = $gap >= 0;
                $gapText = $gapOk ? 'Acima do ritmo' : 'Abaixo do ritmo';
              ?>
              <article class="slide slide--insight">
                <div class="insight <?= $cls ?>">
                  <div class="insight__top">
                    <div class="insight__badge">
                      <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 2l3 7h7l-5.5 4 2.2 7-6-4.2L6.8 20l2.2-7L3.5 9h7L12 2z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" />
                      </svg>
                      Insight de Faturamento
                    </div>
                    <div class="insight__updated">Atualizado: <?= h((string)$insight['updated_at']) ?></div>
                  </div>

                  <h3 class="insight__headline">
                    <?= $ok ? 'No ritmo atual, a meta do mês deve ser batida.' : 'Atenção: no ritmo atual, a meta do mês pode não ser batida.' ?>
                  </h3>

                  <div class="insight__sub">
                    Projeção: <strong><?= number_format($pctEquivLocal, 1, ',', '.') ?>%</strong> da meta • Dias úteis:
                    <strong><?= (int)$insight['dias_uteis_trabalhados'] ?>/<?= (int)$insight['dias_uteis_trabalhar'] ?></strong>
                    • Ritmo: <strong><?= number_format($pctRitmoLocal, 0, ',', '.') ?>%</strong>
                  </div>

                  <div class="insight__grid insight__grid--6">
                    <div class="insight__card">
                      <div class="insight__label"><span>Realizado no mês</span><span>&nbsp;</span></div>
                      <div class="insight__value"><?= h(brl((float)$insight['realizado_ate_hoje'])) ?></div>

                      <div class="progressTop progressTop--discreto">
                        <span>&nbsp;</span>
                        <span class="progressTop__pct"><?= (int)round($pctAtingLocal) ?>%</span>
                      </div>

                      <div class="insight__progress"><span style="width:<?= (int)round($pctAtingLocal) ?>%"></span></div>
                    </div>

                    <div class="insight__card">
                      <div class="insight__label"><span>Meta do mês</span><span>&nbsp;</span></div>
                      <div class="insight__value"><?= h(brl((float)$insight['meta_mes'])) ?></div>
                      <div class="insight__hint">Falta: <?= h(brl((float)$insight['falta_meta_mes'])) ?></div>
                    </div>

                    <div class="insight__card">
                      <div class="insight__label"><span>Projeção de fechamento</span><span><?= number_format($pctEquivLocal, 1, ',', '.') ?>%</span></div>
                      <div class="insight__value"><?= h(brl((float)$insight['fechar_em'])) ?></div>
                      <div class="insight__hint"><?= $ok ? 'Tendência: bater meta' : 'Tendência: abaixo da meta' ?></div>
                    </div>

                    <div class="insight__card">
                      <div class="insight__label"><span>Faturado + agendado hoje</span><span>&nbsp;</span></div>
                      <div class="insight__value"><?= h(brl((float)($insight['hoje_total'] ?? 0))) ?></div>
                      <div class="insight__hint">
                        Faturado: <?= h(brl((float)($insight['faturado_hoje'] ?? 0))) ?> ·
                        Agendado: <?= h(brl((float)($insight['agendado_hoje'] ?? 0))) ?>
                      </div>
                    </div>

                    <div class="insight__card">
                      <div class="insight__label"><span>Deveria ter hoje</span><span>&nbsp;</span></div>
                      <div class="insight__value"><?= h(brl((float)$insight['deveria_ate_hoje'])) ?></div>
                      <div class="insight__hint"><?= $gapText ?>: <?= h(brl(abs($gap))) ?></div>
                    </div>

                    <div class="insight__card">
                      <div class="insight__label"><span>Precisa fazer/dia útil</span><span><?= (int)($insight['dias_restantes'] ?? 0) ?> dias</span></div>
                      <div class="insight__value"><?= h(brl((float)$insight['a_faturar_dia_util'])) ?></div>
                      <div class="insight__hint">Restantes: <?= (int)($insight['dias_restantes'] ?? 0) ?> dia(s) útil(eis)</div>
                    </div>
                  </div>

                  <?php if ($chart): ?>
                    <div class="insight__charts">

                      <!-- GRÁFICO 1: Progresso do mês -->
                      <div class="chartCard" aria-label="Gráfico de progresso do mês">
                        <div class="chartCard__title">Progresso do mês</div>

                        <?php
                          $wReal = (int)($chart['realW'] ?? 0);
                          $wFalta = (int)($chart['faltaW'] ?? 0);

                          $vReal = (float)($chart['real'] ?? 0);
                          $vFalta = (float)($chart['falta'] ?? 0);

                          $xReal = max(8, min(92, $wReal > 0 ? (int)round($wReal / 2) : 8));
                          $xFalta = max(8, min(92, $wFalta > 0 ? (int)round($wReal + ($wFalta / 2)) : 92));
                          if ($wFalta <= 0) $xFalta = 92;

                          $xStart = $wReal;
                          $w = $wFalta;

                          $y = 12;
                          $h = 5;
                          $r = 3;
                        ?>

                        <svg class="chartSvg chartSvg--thin" viewBox="0 0 100 24" role="img" aria-label="Realizado e falta para meta">
                          <text x="<?= (int)$xReal ?>" y="10.8" font-size="3.3" text-anchor="middle" fill="currentColor" opacity="0.45">
                            <?= h(brl($vReal)) ?>
                          </text>

                          <?php if ($wFalta > 0): ?>
                            <text x="<?= (int)$xFalta ?>" y="10.8" font-size="3.3" text-anchor="middle" fill="currentColor" opacity="0.45">
                              <?= h(brl($vFalta)) ?>
                            </text>
                          <?php endif; ?>

                          <rect x="0" y="<?= (int)$y ?>" width="100" height="<?= (int)$h ?>" rx="3" fill="rgba(255,255,255,.14)"></rect>

                          <?php if ($wReal > 0): ?>
                            <path
                              d="M3,<?= (int)$y ?> h<?= max(0, $wReal - 3) ?> v<?= (int)$h ?> h-<?= max(0, $wReal - 3) ?> a3,3 0 0 1 -3,-3 a3,3 0 0 1 3,-2 z"
                              fill="rgba(34,197,94,.95)"></path>
                          <?php endif; ?>

                          <?php if ($w > 0): ?>
                            <?php if ($w <= $r): ?>
                              <rect x="<?= (int)$xStart ?>" y="<?= (int)$y ?>" width="<?= (int)$w ?>" height="<?= (int)$h ?>" rx="0" fill="rgba(244,63,94,.85)"></rect>
                            <?php else: ?>
                              <path d="M<?= (int)$xStart ?>,<?= (int)$y ?> h<?= (int)($w - $r) ?> a<?= (int)$r ?>,<?= (int)$r ?> 0 0 1 <?= (int)$r ?>,<?= (int)$r ?>
                                   v<?= (int)($h - (2 * $r)) ?>
                                   a<?= (int)$r ?>,<?= (int)$r ?> 0 0 1 -<?= (int)$r ?>,<?= (int)$r ?>
                                   h-<?= (int)($w - $r) ?> z" fill="rgba(244,63,94,.85)"></path>
                            <?php endif; ?>
                          <?php endif; ?>
                        </svg>

                        <div class="chartLegend">
                          <span><span class="legendDot" style="background:rgba(34,197,94,.95)"></span>Realizado</span>
                          <span><span class="legendDot" style="background:rgba(244,63,94,.85)"></span>Falta</span>
                        </div>
                      </div>

                      <!-- GRÁFICO 2: Ritmo (dia útil) -->
                      <div class="chartCard" aria-label="Gráfico de ritmo versus meta diária">
                        <div class="chartCard__title">Ritmo (dia útil)</div>

                        <?php
                          $vMetaDia = (float)($chart['metaDia'] ?? 0);
                          $vRealDia = (float)($chart['realDia'] ?? 0);

                          $wMeta = (int)($chart['wMetaDia'] ?? 0);
                          $wReal2 = (int)($chart['wRealDia'] ?? 0);

                          $xMetaTxt = max(14, min(96, $wMeta));
                          $xRealTxt = max(14, min(96, $wReal2));
                        ?>

                        <svg class="chartSvg" viewBox="0 0 100 52" role="img" aria-label="Ritmo por dia útil">
                          <text x="0" y="10" font-size="5" fill="currentColor" opacity="0.92">Meta/dia</text>
                          <text x="<?= (int)$xMetaTxt ?>" y="10" font-size="4.6" text-anchor="end" fill="currentColor" opacity="0.70">
                            <?= h(brl($vMetaDia)) ?>
                          </text>
                          <rect x="0" y="14" width="100" height="8" rx="4" fill="rgba(255,255,255,.14)"></rect>
                          <rect x="0" y="14" width="<?= (int)$wMeta ?>" height="8" rx="4" fill="rgba(59,130,246,.95)"></rect>

                          <text x="0" y="34" font-size="5" fill="currentColor" opacity="0.92">Realizado/dia</text>
                          <text x="<?= (int)$xRealTxt ?>" y="34" font-size="4.6" text-anchor="end" fill="currentColor" opacity="0.70">
                            <?= h(brl($vRealDia)) ?>
                          </text>
                          <rect x="0" y="38" width="100" height="8" rx="4" fill="rgba(255,255,255,.14)"></rect>
                          <rect x="0" y="38" width="<?= (int)$wReal2 ?>" height="8" rx="4" fill="rgba(34,197,94,.95)"></rect>
                        </svg>

                        <div class="chartLegend">
                          <span><span class="legendDot" style="background:rgba(59,130,246,.95)"></span>Meta/dia</span>
                          <span><span class="legendDot" style="background:rgba(34,197,94,.95)"></span>Realizado/dia</span>
                        </div>
                      </div>

                    </div>
                  <?php endif; ?>

                </div>
              </article>
            <?php else: ?>
              <article class="slide slide--text">
                <div class="slide__doc">
                  <div class="doc__title">Dashboard</div>
                  <div class="doc__body">Sem dados suficientes para exibir o Insight.</div>
                </div>
              </article>
            <?php endif; ?>
          </article>
          <!-- /SLIDE 1 -->

          <!-- =====================================================
               SLIDES 2+: COMUNICADOS
               ===================================================== -->
          <?php if (empty($comunicados)): ?>
            <article class="slide slide--text">
              <div class="slide__doc">
                <div class="doc__title">Bem-vindo</div>
                <div class="doc__body">Fique atento aos novos comunicados da Popper aqui.</div>
              </div>
            </article>
          <?php else: ?>
            <?php foreach ($comunicados as $c): ?>
              <?php
                $img = trim((string)($c['imagem_path'] ?? ''));
                $titulo = trim((string)($c['titulo'] ?? ''));
                $conteudo = trim((string)($c['conteudo'] ?? ''));
                $hasImage = ($img !== '');
                $hasText = ($titulo !== '' || $conteudo !== '');
              ?>

              <?php if ($hasImage): ?>
                <article class="slide slide--image">
                  <div class="slide__inner">
                    <img class="slide__img-full" src="<?= h($img) ?>" alt="Comunicado">
                  </div>
                </article>
              <?php else: ?>
                <article class="slide slide--text">
                  <div class="slide__doc">
                    <?php if ($titulo !== ''): ?>
                      <div class="doc__title"><?= h($titulo) ?></div>
                    <?php endif; ?>

                    <?php if ($conteudo !== ''): ?>
                      <?php $conteudoSafe = nl2br(h($conteudo)); ?>
                      <div class="doc__body"><?= $conteudoSafe ?></div>
                    <?php endif; ?>

                    <?php if (!$hasText): ?>
                      <div class="doc__title">Comunicado</div>
                      <div class="doc__body">Sem conteúdo.</div>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endif; ?>

            <?php endforeach; ?>
          <?php endif; ?>

        </div>
      </div>

      <button class="carousel__arrow carousel__arrow--next" type="button" id="nextBtn" aria-label="Próximo">
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
          <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
      </button>

      <div class="carousel__dots" id="dots" aria-label="Indicadores do carrossel"></div>
    </section>
  </main>

  <?php require_once __DIR__ . '/app/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>

  <!-- FORÇA SEMPRE COMEÇAR NO SLIDE 1 (dashboard) -->
  <script>
  (function(){
    const track = document.getElementById('track');
    if (!track) return;

    // sempre volta pro começo (sem animação)
    const go0 = () => track.scrollTo({ left: 0, behavior: 'auto' });

    go0();
    requestAnimationFrame(go0);
    setTimeout(go0, 120);

    // se seu index-carousel.js salva/restaura posição, isso garante o “slide 1”
    window.addEventListener('pageshow', go0);
  })();
  </script>
</body>
</html>