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

$chart = null;
if ($insight) {
  $pctAting = max(0, min(100, (float)$insight['atingimento_mes_pct'] * 100));
  $pctEquiv = max(0, min(300, (float)$insight['equivale_pct'] * 100));
  $pctRitmo = max(0, min(300, (float)$insight['ritmo_pct'] * 100));

  $real = (float)$insight['realizado_ate_hoje'];
  $meta = (float)$insight['meta_mes'];

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

    /* =========================
       ENTRADA SUAVE (rápida)
       ========================= */
    body.page main{
      opacity: 0;
      transform: translateY(4px);
      transition: opacity .18s ease, transform .18s ease;
      will-change: opacity, transform;
    }
    body.page.is-ready main{
      opacity: 1;
      transform: translateY(0);
    }

    /* respiro do primeiro slide */
    .slide--dashboard { padding-top: 10px; }
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

  <!-- ✅ ENTRADA SUAVE (ativa assim que o DOM está pronto) -->
  <script>
  (function(){
    // ativa o fade-in rápido
    const goReady = () => document.body.classList.add('is-ready');
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', goReady, { once:true });
    } else {
      goReady();
    }
  })();
  </script>

  <!-- FORÇA SEMPRE COMEÇAR NO SLIDE 1 -->
  <script>
  (function(){
    const track = document.getElementById('track');
    if (!track) return;

    const go0 = () => track.scrollTo({ left: 0, behavior: 'auto' });

    go0();
    requestAnimationFrame(go0);
    setTimeout(go0, 120);

    window.addEventListener('pageshow', go0);
  })();
  </script>
</body>
</html>