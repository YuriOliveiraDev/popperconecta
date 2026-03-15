<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once APP_ROOT . '/app/config/config-totvs.php';
require_once APP_ROOT . '/app/services/fornecedores.php';

$FORNECEDORES = carregarFornecedores();

require_login();
require_dash_perm('dash.financeiro.contasp');

date_default_timezone_set('America/Sao_Paulo');

$me = current_user();
$u = $me;
$activePage = 'financeiro';
$page_title = 'Dashboard - Contas a Pagar';
$html_class = 'contas-pagar-page';

// =========================================================
// DASHBOARDS (para o header)
// =========================================================
try {
    $dashboards = db()
        ->query("SELECT slug, name, icon FROM dashboards WHERE is_active = TRUE ORDER BY sort_order ASC")
        ->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $dashboards = null;
}

// =========================================================
// ASSETS DA PÁGINA
// =========================================================
$extra_css = [
    '/assets/css/base.css?v=' . @filemtime(APP_ROOT . '/assets/css/base.css'),
    '/assets/css/users.css?v=' . @filemtime(APP_ROOT . '/assets/css/users.css'),
    '/assets/css/dashboard.css?v=' . @filemtime(APP_ROOT . '/assets/css/dashboard.css'),
    '/assets/css/dropdowns.css?v=' . @filemtime(APP_ROOT . '/assets/css/dropdowns.css'),
    '/assets/css/rh_rewards.css?v=' . @filemtime(APP_ROOT . '/assets/css/rh_rewards.css'),
    '/assets/css/header.css?v=' . @filemtime(APP_ROOT . '/assets/css/header.css'),
    '/assets/css/util.css?v=' . @filemtime(APP_ROOT . '/assets/css/util.css'),
    '/assets/css/contas-pagar.css?v=' . @filemtime(APP_ROOT . '/assets/css/contas-pagar.css'),
    '/assets/css/loader.css?v=' . @filemtime(APP_ROOT . '/assets/css/loader.css'),
];

$extra_js_head = [
    '/assets/js/loader.js?v=' . @filemtime(APP_ROOT . '/assets/js/loader.js'),
];

// =========================================================
// HELPERS LOCAIS
// =========================================================
function selfLinkWithRange(int $fromTs, int $toTs): string
{
    return basename(__FILE__) . '?from=' . urlencode(tsToHtmlDate($fromTs)) . '&to=' . urlencode(tsToHtmlDate($toTs));
}

// =========================================================
// PERÍODOS
// =========================================================
$todayTs     = strtotime('today');
$yesterdayTs = strtotime('yesterday');
$tomorrowTs  = strtotime('tomorrow');
$next3Ts     = strtotime('+3 days', $todayTs);
$next7Ts     = strtotime('+7 days', $todayTs);
$next15Ts    = strtotime('+15 days', $todayTs);

$curY = (int) date('Y');
$curM = (int) date('m');
[$curMonthFrom, $curMonthTo] = rangeMonthTs($curY, $curM);

$prevM = $curM - 1;
$prevY = $curY;
if ($prevM === 0) {
    $prevM = 12;
    $prevY--;
}
[$prevMonthFrom, $prevMonthTo] = rangeMonthTs($prevY, $prevM);

$nextM = $curM + 1;
$nextY = $curY;
if ($nextM === 13) {
    $nextM = 1;
    $nextY++;
}
[$nextMonthFrom, $nextMonthTo] = rangeMonthTs($nextY, $nextM);

// =========================================================
// FILTRO
// =========================================================
$defaultFromTs = $curMonthFrom;
$defaultToTs   = $curMonthTo;

$fromTs = htmlDateToTs($_GET['from'] ?? '') ?? $defaultFromTs;
$toTs   = htmlDateToTs($_GET['to'] ?? '') ?? $defaultToTs;

if ($fromTs > $toTs) {
    [$fromTs, $toTs] = [$toTs, $fromTs];
}

$active = tsToHtmlDate($fromTs) . '|' . tsToHtmlDate($toTs);
$mkActive = static fn(string $a): string => $a === $active ? 'active' : '';

// =========================================================
// BUSCAR DADOS
// =========================================================
$result = callTotvsApi();
$items = ($result['success'] && isset($result['data']['items']) && is_array($result['data']['items']))
    ? $result['data']['items']
    : [];

// =========================================================
// FILTRAR POR PERÍODO
// =========================================================
$itemsFiltered = array_values(array_filter($items, static function ($row) use ($fromTs, $toTs) {
    $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
    return $vencTs !== null && $vencTs >= $fromTs && $vencTs <= $toTs;
}));

// =========================================================
// PROCESSAR DADOS
// =========================================================
$totalValor = 0.0;
$totalQtd = 0;
$topCentro = [];
$topFornecedor = [];
$centroFornecedores = [];
$titulosPorFornecedor = [];

foreach ($itemsFiltered as $row) {
    $forn = trim((string) ($row['E2_FORNECE'] ?? ''));
    $vencrea = $row['E2_VENCREA'] ?? '';
    $valor = (float) ($row['E2_VALOR'] ?? 0);
    $ccdRaw = $row['E2_CCD'] ?? '';

    $ccdNomeado = nomeSetorCCD($ccdRaw);
    $fornNomeado = nomeFornecedor($forn);
    $vencTs = toDateTs($vencrea);

    $totalValor += $valor;
    $totalQtd++;

    if (!isset($topCentro[$ccdNomeado])) {
        $topCentro[$ccdNomeado] = [
            'key' => $ccdNomeado,
            'total' => 0.0,
            'qtd' => 0,
        ];
    }
    $topCentro[$ccdNomeado]['total'] += $valor;
    $topCentro[$ccdNomeado]['qtd']++;

    if (!isset($centroFornecedores[$ccdNomeado])) {
        $centroFornecedores[$ccdNomeado] = [];
    }
    if (!isset($centroFornecedores[$ccdNomeado][$fornNomeado])) {
        $centroFornecedores[$ccdNomeado][$fornNomeado] = [
            'nome' => $fornNomeado,
            'qtd' => 0,
            'total' => 0.0,
        ];
    }
    $centroFornecedores[$ccdNomeado][$fornNomeado]['qtd']++;
    $centroFornecedores[$ccdNomeado][$fornNomeado]['total'] += $valor;

    if (!isset($topFornecedor[$fornNomeado])) {
        $topFornecedor[$fornNomeado] = [
            'key' => $fornNomeado,
            'total' => 0.0,
            'qtd' => 0,
        ];
    }
    $topFornecedor[$fornNomeado]['total'] += $valor;
    $topFornecedor[$fornNomeado]['qtd']++;

    if (!isset($titulosPorFornecedor[$fornNomeado])) {
        $titulosPorFornecedor[$fornNomeado] = [
            'fornecedor' => $fornNomeado,
            'titulos' => [],
        ];
    }

    $titulosPorFornecedor[$fornNomeado]['titulos'][] = [
        'filial'     => (string) ($row['E2_FILIAL'] ?? ''),
        'emissao'    => ddmmyyyy($row['E2_EMISSAO'] ?? ''),
        'vencimento' => ddmmyyyy($row['E2_VENCREA'] ?? ''),
        'centro'     => $ccdNomeado,
        'valor'      => (float) ($row['E2_VALOR'] ?? 0),
        'numero'     => (string) ($row['E2_NUM'] ?? ''),
        'parcela'    => (string) ($row['E2_PARCELA'] ?? ''),
        'tipo'       => (string) ($row['E2_TIPO'] ?? ''),
        'historico'  => (string) ($row['E2_HIST'] ?? ''),
    ];
}

// =========================================================
// ORDENAR RANKINGS
// =========================================================
$topCentroList = array_values($topCentro);
usort($topCentroList, static fn($a, $b) => $b['total'] <=> $a['total']);

$topFornecedorList = array_values($topFornecedor);
usort($topFornecedorList, static fn($a, $b) => $b['total'] <=> $a['total']);

$maxCentro = empty($topCentroList) ? 0 : max(array_column($topCentroList, 'total'));
$maxForn   = empty($topFornecedorList) ? 0 : max(array_column($topFornecedorList, 'total'));

// =========================================================
// PRÓXIMOS VENCIMENTOS
// =========================================================
$proximos3 = [];
$proximos7 = [];
$proximos15 = [];

foreach ($items as $row) {
    $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
    if ($vencTs === null) {
        continue;
    }

    if ($vencTs >= $todayTs && $vencTs <= $next3Ts) {
        $proximos3[] = $row;
    }
    if ($vencTs >= $todayTs && $vencTs <= $next7Ts) {
        $proximos7[] = $row;
    }
    if ($vencTs >= $todayTs && $vencTs <= $next15Ts) {
        $proximos15[] = $row;
    }
}

usort($proximos3, static fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? PHP_INT_MAX) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? PHP_INT_MAX));
usort($proximos7, static fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? PHP_INT_MAX) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? PHP_INT_MAX));
usort($proximos15, static fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? PHP_INT_MAX) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? PHP_INT_MAX));

$sumProx3  = array_sum(array_map(static fn($r) => (float) ($r['E2_VALOR'] ?? 0), $proximos3));
$sumProx7  = array_sum(array_map(static fn($r) => (float) ($r['E2_VALOR'] ?? 0), $proximos7));
$sumProx15 = array_sum(array_map(static fn($r) => (float) ($r['E2_VALOR'] ?? 0), $proximos15));

// =========================================================
// HEADER
// =========================================================
require_once APP_ROOT . '/app/layout/header.php';
?>

<script>
(function () {
  function showLoader() {
    if (window.PopperLoading && typeof window.PopperLoading.show === 'function') {
      window.PopperLoading.show('Carregando…', 'Buscando Contas a Pagar (TOTVS)');
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', showLoader, { once: true });
  } else {
    showLoader();
  }
})();
</script>

<div class="wrap">

<?php if (!$result['success']): ?>
  <div class="err">
    <h2>Não foi possível carregar os dados</h2>
    <div class="muted">
      HTTP: <?= safe($result['info']['http_code'] ?? '') ?>
      |
      Content-Type: <?= safe($result['info']['content_type'] ?? '') ?>
    </div>
    <div class="muted">
      cURL: <?= safe(($result['info']['error'] ?? '') ?: 'sem erro de cURL') ?>
      |
      JSON: <?= safe(($result['json_error'] ?? '') ?: 'ok') ?>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      if (window.PopperLoading?.hide) {
        window.PopperLoading.hide();
      }
    });
  </script>

<?php else: ?>

  <div class="card" style="margin-bottom:14px;">
    <div class="card-hd">
      <h2>Filtro vencimento:</h2>
      <span class="badge"><?= safe(ddmmyyyy(date('Ymd', $fromTs))) ?> até <?= safe(ddmmyyyy(date('Ymd', $toTs))) ?></span>
    </div>

    <div style="padding:14px 16px;">
      <form method="GET" class="filter" id="filterForm">
        <div class="f">
          <label>Data de</label>
          <input type="date" name="from" value="<?= safe(tsToHtmlDate($fromTs)) ?>">
        </div>

        <div class="f">
          <label>Data até</label>
          <input type="date" name="to" value="<?= safe(tsToHtmlDate($toTs)) ?>">
        </div>

        <button type="submit" id="btnAplicar">Aplicar</button>
        <a href="<?= safe(basename(__FILE__)) ?>">Limpar</a>
      </form>

      <div class="quick">
        <a class="<?= $mkActive(tsToHtmlDate(strtotime('-2 days', $todayTs)) . '|' . tsToHtmlDate($todayTs)) ?>" href="<?= safe(selfLinkWithRange(strtotime('-2 days', $todayTs), $todayTs)) ?>">Últimos 3 dias</a>
        <a class="<?= $mkActive(tsToHtmlDate(strtotime('-6 days', $todayTs)) . '|' . tsToHtmlDate($todayTs)) ?>" href="<?= safe(selfLinkWithRange(strtotime('-6 days', $todayTs), $todayTs)) ?>">Últimos 7 dias</a>
        <a class="<?= $mkActive(tsToHtmlDate($yesterdayTs) . '|' . tsToHtmlDate($yesterdayTs)) ?>" href="<?= safe(selfLinkWithRange($yesterdayTs, $yesterdayTs)) ?>">Ontem</a>
        <a class="<?= $mkActive(tsToHtmlDate($todayTs) . '|' . tsToHtmlDate($todayTs)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $todayTs)) ?>">Hoje</a>
        <a class="<?= $mkActive(tsToHtmlDate($tomorrowTs) . '|' . tsToHtmlDate($tomorrowTs)) ?>" href="<?= safe(selfLinkWithRange($tomorrowTs, $tomorrowTs)) ?>">Amanhã</a>
        <a class="<?= $mkActive(tsToHtmlDate($todayTs) . '|' . tsToHtmlDate($next3Ts)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $next3Ts)) ?>">Próximos 3 dias</a>
        <a class="<?= $mkActive(tsToHtmlDate($todayTs) . '|' . tsToHtmlDate($next7Ts)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $next7Ts)) ?>">Próximos 7 dias</a>
        <a class="<?= $mkActive(tsToHtmlDate($todayTs) . '|' . tsToHtmlDate($next15Ts)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $next15Ts)) ?>">Próximos 15 dias</a>
        <a class="<?= $mkActive(tsToHtmlDate($prevMonthFrom) . '|' . tsToHtmlDate($prevMonthTo)) ?>" href="<?= safe(selfLinkWithRange($prevMonthFrom, $prevMonthTo)) ?>">Mês passado</a>
        <a class="<?= $mkActive(tsToHtmlDate($curMonthFrom) . '|' . tsToHtmlDate($curMonthTo)) ?>" href="<?= safe(selfLinkWithRange($curMonthFrom, $curMonthTo)) ?>">Mês atual</a>
        <a class="<?= $mkActive(tsToHtmlDate($nextMonthFrom) . '|' . tsToHtmlDate($nextMonthTo)) ?>" href="<?= safe(selfLinkWithRange($nextMonthFrom, $nextMonthTo)) ?>">Próximo mês</a>
      </div>
    </div>
  </div>

  <div class="grid-cards">
    <div class="card">
      <div class="kpi">
        <div>
          <div class="label">Total (registros)</div>
          <div class="value"><?= number_format($totalQtd, 0, ',', '.') ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="kpi">
        <div>
          <div class="label">Total a pagar (período)</div>
          <div class="value"><?= moneyBR($totalValor) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="kpi">
        <div>
          <div class="label">Próximos 3 dias</div>
          <div class="value"><?= moneyBR($sumProx3) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="kpi">
        <div>
          <div class="label">Próximos 7 dias</div>
          <div class="value"><?= moneyBR($sumProx7) ?></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="kpi">
        <div>
          <div class="label">Próximos 15 dias</div>
          <div class="value"><?= moneyBR($sumProx15) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="grid-2">

    <div class="card">
      <div class="card-hd">
        <h2>Top gastos por Centro de Custo <span class="click-hint">(clique para detalhes)</span></h2>
        <span class="badge">Top <?= count($topCentroList) ?></span>
      </div>

      <div class="scroll-10">
        <ul class="rank">
          <?php
          $p = 1;
          foreach ($topCentroList as $t):
              $cls = ($p === 1 ? 'p1' : ($p === 2 ? 'p2' : ($p === 3 ? 'p3' : '')));
              $w = $maxCentro > 0 ? ($t['total'] / $maxCentro) * 100 : 0;
              $pct = $totalValor > 0 ? ($t['total'] / $totalValor) * 100 : 0;

              $fornecedoresCentro = array_values($centroFornecedores[$t['key']] ?? []);
              usort($fornecedoresCentro, static fn($a, $b) => $b['total'] <=> $a['total']);

              foreach ($fornecedoresCentro as &$f) {
                  $f['percent'] = $t['total'] > 0 ? round(($f['total'] / $t['total']) * 100, 1) : 0;
              }
              unset($f);

              $modalData = json_encode([
                  'centro' => $t['key'],
                  'total' => $t['total'],
                  'fornecedores' => $fornecedoresCentro,
              ], JSON_UNESCAPED_UNICODE);
          ?>
            <li class="centro-custo-item" data-centro='<?= safe($modalData) ?>'>
              <div class="pos <?= $cls ?>"><?= $p ?></div>
              <div class="rinfo">
                <div class="rtitle" title="<?= safe($t['key']) ?>"><?= safe($t['key']) ?></div>
                <div class="rmeta"><?= (int) $t['qtd'] ?> títulos • <?= number_format($pct, 1, ',', '.') ?>%</div>
                <div class="bar"><div class="fill" style="width:<?= number_format($w, 2, '.', '') ?>%"></div></div>
              </div>
              <div class="rval">
                <div class="v"><?= moneyBR($t['total']) ?></div>
              </div>
            </li>
          <?php
              $p++;
          endforeach;
          ?>
        </ul>
      </div>
    </div>

    <div class="card">
      <div class="card-hd">
        <h2>Top gastos por Fornecedor <span class="click-hint">(clique para detalhes)</span></h2>
        <span class="badge">Top <?= count($topFornecedorList) ?></span>
      </div>

      <div class="scroll-10">
        <ul class="rank">
          <?php
          $p = 1;
          foreach ($topFornecedorList as $t):
              $cls = ($p === 1 ? 'p1' : ($p === 2 ? 'p2' : ($p === 3 ? 'p3' : '')));
              $w = $maxForn > 0 ? ($t['total'] / $maxForn) * 100 : 0;
              $pct = $totalValor > 0 ? ($t['total'] / $totalValor) * 100 : 0;

              $fornKey = $t['key'];
              $payload = $titulosPorFornecedor[$fornKey] ?? ['fornecedor' => $fornKey, 'titulos' => []];
              $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
          ?>
            <li class="fornecedor-item" data-fornecedor='<?= safe($payloadJson) ?>'>
              <div class="pos <?= $cls ?>"><?= $p ?></div>
              <div class="rinfo">
                <div class="rtitle" title="<?= safe($t['key']) ?>"><?= safe($t['key']) ?></div>
                <div class="rmeta"><?= (int) $t['qtd'] ?> títulos • <?= number_format($pct, 1, ',', '.') ?>%</div>
                <div class="bar"><div class="fill green" style="width:<?= number_format($w, 2, '.', '') ?>%"></div></div>
              </div>
              <div class="rval">
                <div class="v"><?= moneyBR($t['total']) ?></div>
              </div>
            </li>
          <?php
              $p++;
          endforeach;
          ?>
        </ul>
      </div>
    </div>

  </div>

  <div class="grid-3">
    <?php
    $tabelas = [
        ['Próximos 3 dias', $proximos3, $sumProx3],
        ['Próximos 7 dias', $proximos7, $sumProx7],
        ['Próximos 15 dias', $proximos15, $sumProx15],
    ];

    foreach ($tabelas as [$titulo, $dados, $total]):
    ?>
      <div class="card">
        <div class="card-hd">
          <h2>Contas a pagar — <?= safe($titulo) ?></h2>
          <span class="badge"><?= number_format(count($dados), 0, ',', '.') ?> itens • <?= moneyBR($total) ?></span>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Filial</th>
                <th>Emissão</th>
                <th>Fornecedor</th>
                <th>Venc.</th>
                <th>Centro</th>
                <th style="text-align:right;">Valor</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($dados)): ?>
                <tr>
                  <td colspan="6" class="muted">Sem títulos para este período.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($dados as $r):
                    $ccdRaw = $r['E2_CCD'] ?? '';
                    $fornRaw = $r['E2_FORNECE'] ?? '';
                ?>
                  <tr>
                    <td><?= safe($r['E2_FILIAL'] ?? '') ?></td>
                    <td><?= safe(ddmmyyyy($r['E2_EMISSAO'] ?? '')) ?></td>
                    <td title="<?= safe((string) $fornRaw) ?>"><?= safe(nomeFornecedor((string) $fornRaw)) ?></td>
                    <td><?= safe(ddmmyyyy($r['E2_VENCREA'] ?? '')) ?></td>
                    <td class="ccd">
                      <span title="<?= safe(normalizarCCD((string) $ccdRaw)) ?>">
                        <?= safe(nomeSetorCCD((string) $ccdRaw)) ?>
                      </span>
                    </td>
                    <td class="val"><?= safe(moneyBR($r['E2_VALOR'] ?? 0)) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    window.addEventListener('load', function () {
      if (window.PopperLoading?.hide) {
        window.PopperLoading.hide();
      }
    });

    document.addEventListener('DOMContentLoaded', function () {
      const form = document.getElementById('filterForm');
      if (form) {
        form.addEventListener('submit', function () {
          if (window.PopperLoading?.show) {
            window.PopperLoading.show('Carregando…', 'Aplicando filtro');
          }
        });
      }

      document.querySelectorAll('.quick a').forEach(function (a) {
        a.addEventListener('click', function () {
          if (window.PopperLoading?.show) {
            window.PopperLoading.show('Carregando…', 'Atualizando período');
          }
        });
      });
    });
  </script>

<?php endif; ?>
</div>

<div id="modal-overlay" class="modal-overlay">
  <div class="modal-container">
    <div class="modal-header">
      <h3 id="modal-title" class="modal-title"></h3>
      <button id="modal-close" class="modal-close" type="button">&times;</button>
    </div>
    <div class="modal-body">
      <table class="modal-table">
        <thead>
          <tr>
            <th>Fornecedor</th>
            <th style="text-align:center;">Títulos</th>
            <th style="text-align:right;">Valor</th>
            <th style="text-align:right;">% do Centro</th>
          </tr>
        </thead>
        <tbody id="modal-table-body"></tbody>
      </table>
    </div>
    <div class="modal-footer">
      <span class="modal-total-label">Total do Centro de Custo</span>
      <span id="modal-total" class="modal-total-value"></span>
    </div>
  </div>
</div>

<div id="modal-forn-overlay" class="modal-overlay">
  <div class="modal-container modal-container--fornecedor">
    <div class="modal-header">
      <h3 id="modal-forn-title" class="modal-title"></h3>
      <button id="modal-forn-close" class="modal-close" type="button">&times;</button>
    </div>

    <div class="modal-body modal-body--fornecedor">
      <table class="modal-table modal-table--fornecedor">
        <thead>
          <tr>
            <th>Filial</th>
            <th>Emissão</th>
            <th>Venc.</th>
            <th>Centro</th>
            <th>Número</th>
            <th>Parc.</th>
            <th>Tipo</th>
            <th>Histórico</th>
            <th style="text-align:right;">Valor</th>
          </tr>
        </thead>
        <tbody id="modal-forn-body"></tbody>
      </table>
    </div>

    <div class="modal-footer">
      <span class="modal-total-label">Total do Fornecedor</span>
      <span id="modal-forn-total" class="modal-total-value"></span>
    </div>
  </div>
</div>

<script src="/assets/js/contas-pagar.js?v=<?= @filemtime(APP_ROOT . '/assets/js/contas-pagar.js') ?>"></script>
<script src="/assets/js/header.js?v=<?= @filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
<script src="/assets/js/dropdowns.js?v=<?= @filemtime(APP_ROOT . '/assets/js/dropdowns.js') ?>"></script>
<script src="/assets/js/index-carousel.js?v=<?= @filemtime(APP_ROOT . '/assets/js/index-carousel.js') ?>"></script>

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>