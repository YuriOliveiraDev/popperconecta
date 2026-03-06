<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_login();

require_once __DIR__ . '/app/config-totvs.php';

const CACHE_DIR = __DIR__ . '/cache';
const CACHE_TTL = 600;
const CONSULTA_RANKINGS = '000070';

$nowY = (int) date('Y');
$nowM = (int) date('m');

$activeYm = ($nowY === 2026)
  ? sprintf('2026-%02d', $nowM)
  : '2026-02';

if (isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string) $_GET['ym'])) {
  $activeYm = (string) $_GET['ym'];
}

$meses = [
  1 => 'Jan',
  2 => 'Fev',
  3 => 'Mar',
  4 => 'Abr',
  5 => 'Mai',
  6 => 'Jun',
  7 => 'Jul',
  8 => 'Ago',
  9 => 'Set',
  10 => 'Out',
  11 => 'Nov',
  12 => 'Dez'
];

$y = (int) substr($activeYm, 0, 4);
$m = (int) substr($activeYm, 5, 2);

$monthStart = sprintf('%04d-%02d-01', $y, $m);
$monthEnd = date('Y-m-t', strtotime($monthStart));

// ======================================
// FILTRO PERSONALIZADO DE DATA
// ======================================
$dtIni = isset($_GET['dt_ini']) ? trim((string) $_GET['dt_ini']) : '';
$dtFim = isset($_GET['dt_fim']) ? trim((string) $_GET['dt_fim']) : '';

$hasCustomRange =
  preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtIni) &&
  preg_match('/^\d{4}-\d{2}-\d{2}$/', $dtFim);

if ($hasCustomRange) {
  $iniTs = strtotime($dtIni . ' 00:00:00');
  $fimTs = strtotime($dtFim . ' 23:59:59');

  if ($iniTs !== false && $fimTs !== false) {
    // garante ordem correta mesmo se usuário inverter
    if ($iniTs > $fimTs) {
      [$dtIni, $dtFim] = [$dtFim, $dtIni];
      [$iniTs, $fimTs] = [$fimTs, $iniTs];
    }

    $monthStart = $dtIni;
    $monthEnd = $dtFim;
  }
}

$monthStartTs = strtotime($monthStart . ' 00:00:00');
$monthEndTs = strtotime($monthEnd . ' 23:59:59');

// =========================
// CACHE
// =========================
function ensureCacheDir(): void
{
  if (!is_dir(CACHE_DIR))
    @mkdir(CACHE_DIR, 0775, true);
}
function cacheFile(string $key): string
{
  $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
  return CACHE_DIR . '/dash_' . $safeKey . '.json';
}
function cacheRead(string $key): ?array
{
  $p = cacheFile($key);
  if (!is_file($p))
    return null;
  if ((time() - (int) filemtime($p)) > CACHE_TTL)
    return null;
  $raw = (string) @file_get_contents($p);
  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}
function cacheWrite(string $key, array $data): void
{
  ensureCacheDir();
  @file_put_contents(cacheFile($key), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// =========================
// HELPERS
// =========================
function pickString(array $row, array $keys): ?string
{
  foreach ($keys as $k) {
    if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '')
      return (string) $row[$k];
  }
  $lower = [];
  foreach ($row as $k => $v)
    $lower[strtolower((string) $k)] = $v;
  foreach ($keys as $k) {
    $lk = strtolower($k);
    if (array_key_exists($lk, $lower) && $lower[$lk] !== null && $lower[$lk] !== '')
      return (string) $lower[$lk];
  }
  return null;
}
function pickFloat(array $row, array $keys): float
{
  $v = pickString($row, $keys);
  if ($v === null)
    return 0.0;

  $v = str_replace(['R$', ' ', "\u{00A0}"], '', $v);

  if (str_contains($v, ',') && str_contains($v, '.')) {
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
  } else {
    $v = str_replace(',', '.', $v);
  }

  return is_numeric($v) ? (float) $v : 0.0;
}
function yyyymmdd_to_ts(?string $yyyymmdd): ?int
{
  if (!$yyyymmdd || !preg_match('/^\d{8}$/', $yyyymmdd))
    return null;
  $y = (int) substr($yyyymmdd, 0, 4);
  $m = (int) substr($yyyymmdd, 4, 2);
  $d = (int) substr($yyyymmdd, 6, 2);
  if (!checkdate($m, $d, $y))
    return null;
  return strtotime(sprintf('%04d-%02d-%02d 12:00:00', $y, $m, $d));
}
function totvsNormalizeItems(array $data): array
{
  if (isset($data['items']) && is_array($data['items']))
    return $data['items'];
  if (isset($data['Itms']) && is_array($data['Itms']))
    return $data['Itms'];
  if (isset($data['result']) && is_array($data['result']))
    return $data['result'];
  if (isset($data['value']) && is_array($data['value']))
    return $data['value'];
  if (isset($data[0]) && is_array($data[0]))
    return $data;
  return [];
}

function totvsFetchItems(string $consulta, string $monthStart, string $monthEnd, array &$debug): array
{
  $baseUrl = totvsConsultaUrl($consulta);

  $urlWith = $baseUrl . '?dt_ini=' . urlencode($monthStart) . '&dt_fim=' . urlencode($monthEnd);
  $resp1 = callTotvsApi($urlWith);

  $debug['try1_' . $consulta] = [
    'url' => $urlWith,
    'http' => (int) ($resp1['info']['http_code'] ?? 0),
    'success' => (bool) $resp1['success'],
  ];

  $msg1 = '';
  if (is_array($resp1['data']) && isset($resp1['data']['errorMessage']))
    $msg1 = (string) $resp1['data']['errorMessage'];
  if ($msg1 === '' && (string) ($resp1['raw'] ?? '') !== '') {
    $j = json_decode((string) $resp1['raw'], true);
    if (is_array($j) && isset($j['errorMessage']))
      $msg1 = (string) $j['errorMessage'];
  }

  if ((int) ($resp1['info']['http_code'] ?? 0) === 400 && stripos($msg1, 'não permite') !== false) {
    $resp2 = callTotvsApi($baseUrl);

    $debug['try2_' . $consulta] = [
      'url' => $baseUrl,
      'http' => (int) ($resp2['info']['http_code'] ?? 0),
      'success' => (bool) $resp2['success'],
    ];

    if (!$resp2['success'] || !is_array($resp2['data'])) {
      $debug['totvs_info_' . $consulta] = $resp2['info'] ?? [];
      $debug['raw_preview_' . $consulta] = substr((string) ($resp2['raw'] ?? ''), 0, 700);
      throw new Exception('Falha TOTVS ' . $consulta . '. HTTP ' . (int) ($resp2['info']['http_code'] ?? 0));
    }
    return totvsNormalizeItems($resp2['data']);
  }

  if (!$resp1['success'] || !is_array($resp1['data'])) {
    $debug['totvs_info_' . $consulta] = $resp1['info'] ?? [];
    $debug['raw_preview_' . $consulta] = substr((string) ($resp1['raw'] ?? ''), 0, 700);
    throw new Exception('Falha TOTVS ' . $consulta . '. HTTP ' . (int) ($resp1['info']['http_code'] ?? 0) . ($msg1 ? (' - ' . $msg1) : ''));
  }

  return totvsNormalizeItems($resp1['data']);
}

function totvsFetchItemsRaw(string $consulta, array &$debug): array
{
  $baseUrl = totvsConsultaUrl($consulta);
  $resp = callTotvsApi($baseUrl);

  $debug['raw_fetch_' . $consulta] = [
    'url' => $baseUrl,
    'http' => (int) ($resp['info']['http_code'] ?? 0),
    'success' => (bool) $resp['success'],
  ];

  if (!$resp['success'] || !is_array($resp['data'])) {
    $debug['totvs_info_' . $consulta] = $resp['info'] ?? [];
    $debug['raw_preview_' . $consulta] = substr((string) ($resp['raw'] ?? ''), 0, 700);
    throw new Exception('Falha TOTVS ' . $consulta . '. HTTP ' . (int) ($resp['info']['http_code'] ?? 0));
  }

  return totvsNormalizeItems($resp['data']);
}

// =========================
// BUSCA 000070
// =========================
$debug = [
  'ym' => $activeYm,
  'start' => $monthStart,
  'end' => $monthEnd,
  'consulta_000070' => CONSULTA_RANKINGS,
];

$error = null;
$items = [];

$cacheKey = 'rankings_' . CONSULTA_RANKINGS . '_' . $monthStart . '_' . $monthEnd;
$cached = cacheRead($cacheKey);

if ($cached && isset($cached['items']) && is_array($cached['items'])) {
  $items = $cached['items'];
  $debug['cache_000070'] = 'HIT';
} else {
  $debug['cache_000070'] = 'MISS';
  try {
    $items = totvsFetchItems(CONSULTA_RANKINGS, $monthStart, $monthEnd, $debug);
    cacheWrite($cacheKey, ['items' => $items, 'fetched_at' => date('c')]);
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

// =========================
// FILTRO 000070 POR MÊS
// =========================
$itemsMonth = [];
foreach ($items as $row) {
  if (!is_array($row))
    continue;
  $emi = pickString($row, ['EMISAO', 'C5_EMISSAO']);
  $ts = yyyymmdd_to_ts($emi);
  if ($ts === null)
    continue;
  if ($ts < $monthStartTs || $ts > $monthEndTs)
    continue;
  $itemsMonth[] = $row;
}


$debug['items_total_000070'] = is_array($items) ? count($items) : 0;
$debug['items_periodo_000070'] = count($itemsMonth);
// =========================
// AJUSTES MANUAIS
// =========================
$ajusteMes = 0.0;

try {
  require_once __DIR__ . '/app/db.php';

  $stmtAdj = db()->prepare('
    SELECT ref_date, valor
    FROM dashboard_faturamento_ajustes
    WHERE dash_slug = ?
      AND is_active = 1
      AND ref_date BETWEEN ? AND ?
  ');
  $stmtAdj->execute([DASH_SLUG_AJUSTES, $monthStart, $monthEnd]);

  while ($r = $stmtAdj->fetch(PDO::FETCH_ASSOC)) {
    $ajusteMes += (float) ($r['valor'] ?? 0);
  }
} catch (Throwable $e) {
  $debug['erro_ajustes'] = $e->getMessage();
}

// =========================
// AGREGAÇÕES (Ranking/Estado) + Ticket por NF
// =========================
$bySeller = [];
$byState = [];
$nfTotals = [];
$total00070Mes = 0.0;

foreach ($itemsMonth as $row) {
  if (!is_array($row))
    continue;

  $seller = pickString($row, ['VENDEDOR']) ?? 'Sem vendedor';
  $uf = pickString($row, ['ESTADO']) ?? 'N/D';
  $nf = pickString($row, ['NF', 'D2_DOC']) ?? '';
  $value = pickFloat($row, ['VALOR', 'D2_TOTAL', 'VALOR_TOTAL']);

  $total00070Mes += $value;

  if ($value <= 0)
    continue;

  $bySeller[$seller] = ($bySeller[$seller] ?? 0) + $value;
  $byState[$uf] = ($byState[$uf] ?? 0) + $value;

  if ($nf !== '')
    $nfTotals[$nf] = ($nfTotals[$nf] ?? 0) + $value;
}

arsort($bySeller);
arsort($byState);

$topSellers = array_slice($bySeller, 0, 10, true);
$topStates = array_slice($byState, 0, 10, true);

// ✅ FATURADO = TOTAL PRINCIPAL
$faturadoMes = $total00070Mes;

$totalDocs = count($nfTotals);

// ✅ ticket passa a usar total principal
$ticketMedio = ($totalDocs > 0) ? ($faturadoMes / $totalDocs) : 0.0;

// =========================
// ANÁLISE DE PREÇO (somente 000070)
// Base confiável: PRECO_TABELA, PRECO_PRATICADO, QTDE
// =========================
$priceAggVendor = []; // vendedor => ['tabela'=>, 'praticado'=>, 'desc'=>, 'itens'=>, 'qtd'=>]
$priceAggClient = []; // cliente => ...
$priceAggProd = []; // produto => ...

$totTabela = 0.0;
$totPraticado = 0.0;
$totDesc = 0.0;

$priceStats = [
  'linhas_lidas' => 0,
  'linhas_validas' => 0,
  'linhas_desc_real' => 0,
  'linhas_sem_desc' => 0,
  'linhas_acima_tabela' => 0,
  'linhas_invalidas' => 0,
];

function normKey(?string $code, ?string $label, string $fallback = 'N/D'): string
{
  $code = trim((string) $code);
  $label = trim((string) $label);

  if ($code !== '' && $label !== '')
    return $code . ' - ' . $label;
  if ($code !== '')
    return $code;
  if ($label !== '')
    return $label;
  return $fallback;
}

foreach ($itemsMonth as $row) {
  if (!is_array($row))
    continue;

  $priceStats['linhas_lidas']++;

  // chaves mais estáveis
  $sellerCode = pickString($row, ['COD_VENDEDOR', 'F2_VEND1', 'VENDEDOR_COD']);
  $sellerName = pickString($row, ['VENDEDOR', 'A3_NOME']);
  $clientCode = pickString($row, ['COD_CLIENTE', 'CLIENTE_COD', 'D2_CLIENTE', 'A1_COD']);
  $clientName = pickString($row, ['CLIENTE', 'A1_NOME']);
  $prodCode = pickString($row, ['CODIGO', 'B1_COD', 'PRODUTO_COD']);
  $prodName = pickString($row, ['PRODUTO', 'B1_DESC']);

  $sellerKey = normKey($sellerCode, $sellerName, 'Sem vendedor');
  $clientKey = normKey($clientCode, $clientName, 'Sem cliente');
  $prodKey = normKey($prodCode, $prodName, 'Sem produto');

  // ✅ QTDE decimal, sem truncar
  $qtde = pickFloat($row, ['QTDE', 'D2_QUANT']);
  $pt = pickFloat($row, ['PRECO_TABELA', 'D2_PRUNIT']);
  $pp = pickFloat($row, ['PRECO_PRATICADO', 'D2_PRCVEN']);

  // validações mínimas
  if ($qtde <= 0 || $pt <= 0 || $pp <= 0) {
    $priceStats['linhas_invalidas']++;
    continue;
  }

  $priceStats['linhas_validas']++;

  // valores ponderados pela quantidade
  $valTabela = $pt * $qtde;
  $valPrat = $pp * $qtde;
  $descR = $valTabela - $valPrat;

  // acumula total geral sempre
  $totTabela += $valTabela;
  $totPraticado += $valPrat;

  if ($descR > 0) {
    $totDesc += $descR;
    $priceStats['linhas_desc_real']++;
  } elseif (abs($descR) < 0.00001) {
    $priceStats['linhas_sem_desc']++;
  } else {
    $priceStats['linhas_acima_tabela']++;
  }

  // Se quiser analisar só erosão real, mantém apenas desc > 0 nos rankings
  if ($descR <= 0) {
    continue;
  }

  if (!isset($priceAggVendor[$sellerKey])) {
    $priceAggVendor[$sellerKey] = ['tabela' => 0.0, 'praticado' => 0.0, 'desc' => 0.0, 'itens' => 0, 'qtd' => 0.0];
  }
  $priceAggVendor[$sellerKey]['tabela'] += $valTabela;
  $priceAggVendor[$sellerKey]['praticado'] += $valPrat;
  $priceAggVendor[$sellerKey]['desc'] += $descR;
  $priceAggVendor[$sellerKey]['itens']++;
  $priceAggVendor[$sellerKey]['qtd'] += $qtde;

  if (!isset($priceAggClient[$clientKey])) {
    $priceAggClient[$clientKey] = ['tabela' => 0.0, 'praticado' => 0.0, 'desc' => 0.0, 'itens' => 0, 'qtd' => 0.0];
  }
  $priceAggClient[$clientKey]['tabela'] += $valTabela;
  $priceAggClient[$clientKey]['praticado'] += $valPrat;
  $priceAggClient[$clientKey]['desc'] += $descR;
  $priceAggClient[$clientKey]['itens']++;
  $priceAggClient[$clientKey]['qtd'] += $qtde;

  if (!isset($priceAggProd[$prodKey])) {
    $priceAggProd[$prodKey] = ['tabela' => 0.0, 'praticado' => 0.0, 'desc' => 0.0, 'itens' => 0, 'qtd' => 0.0];
  }
  $priceAggProd[$prodKey]['tabela'] += $valTabela;
  $priceAggProd[$prodKey]['praticado'] += $valPrat;
  $priceAggProd[$prodKey]['desc'] += $descR;
  $priceAggProd[$prodKey]['itens']++;
  $priceAggProd[$prodKey]['qtd'] += $qtde;
}

// filtro anti-ruído para ranking
$MIN_TABELA_R = 1500.0;

// ranking por percentual ponderado
$rankByPct = function (array $agg) use ($MIN_TABELA_R): array {
  $out = [];

  foreach ($agg as $k => $v) {
    $t = (float) ($v['tabela'] ?? 0);
    $d = (float) ($v['desc'] ?? 0);

    if ($t < $MIN_TABELA_R || $d <= 0)
      continue;

    $pct = ($t > 0) ? ($d / $t) : 0.0;

    $out[$k] = [
      'pct' => $pct,
      'tabela' => $t,
      'praticado' => (float) ($v['praticado'] ?? 0),
      'desc' => $d,
      'itens' => (int) ($v['itens'] ?? 0),
      'qtd' => (float) ($v['qtd'] ?? 0),
    ];
  }

  uasort($out, fn($a, $b) => ($b['pct'] <=> $a['pct']));
  return array_slice($out, 0, 10, true);
};

// ranking por impacto em R$
$rankByDesc = function (array $agg) use ($MIN_TABELA_R): array {
  $out = [];

  foreach ($agg as $k => $v) {
    $t = (float) ($v['tabela'] ?? 0);
    $d = (float) ($v['desc'] ?? 0);

    if ($t < $MIN_TABELA_R || $d <= 0)
      continue;

    $out[$k] = [
      'tabela' => $t,
      'praticado' => (float) ($v['praticado'] ?? 0),
      'desc' => $d,
      'itens' => (int) ($v['itens'] ?? 0),
      'qtd' => (float) ($v['qtd'] ?? 0),
    ];
  }

  uasort($out, fn($a, $b) => ($b['desc'] <=> $a['desc']));
  return array_slice($out, 0, 10, true);
};

$topVendorPct = $rankByPct($priceAggVendor);
$topVendorR = $rankByDesc($priceAggVendor);

$topClientPct = $rankByPct($priceAggClient);
$topClientR = $rankByDesc($priceAggClient);

$topProdPct = $rankByPct($priceAggProd);
$topProdR = $rankByDesc($priceAggProd);

// desconto geral ponderado da base válida
$descPctGeral = ($totTabela > 0) ? ($totDesc / $totTabela) : 0.0;

require_once __DIR__ . '/app/header.php';
?>
<link rel="stylesheet" href="/assets/css/loader.css?v=<?= filemtime(__DIR__ . '/assets/css/loader.css') ?>" />
<link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
<link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
<link rel="stylesheet"
  href="/assets/css/dashboard-executivo.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard-executivo.css') ?>" />
<link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
<link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" />
<link rel="stylesheet"
  href="/assets/css/insight_comercial.css?v=<?= filemtime(__DIR__ . '/assets/css/insight_comercial.css') ?>" />

<script>document.documentElement.classList.add('dashboard-exec');</script>

<main class="container dashboard-exec">
  <section class="dashboard-grid dashboard-grid--exec">

    <div class="card grid-col-span-3 exec-filter">
      <div class="exec-filter__row">

        <div class="exec-filter__top">
          <div class="card__header">
            <h2 class="card__title">Comercial • Rankings & Ticket Médio</h2>
            <p class="card__subtitle">
              <?= $hasCustomRange ? 'Período personalizado' : 'Período do mês' ?>:
              <?= safe($monthStart) ?> até <?= safe($monthEnd) ?>
            </p>
          </div>

          <form method="get" class="exec-filter__dates">
            <div class="exec-filter__dates-group">
              <label for="dt_ini">De</label>
              <input type="date" id="dt_ini" name="dt_ini" value="<?= safe($hasCustomRange ? $monthStart : '') ?>">
            </div>

            <div class="exec-filter__dates-group">
              <label for="dt_fim">Até</label>
              <input type="date" id="dt_fim" name="dt_fim" value="<?= safe($hasCustomRange ? $monthEnd : '') ?>">
            </div>

            <div class="exec-filter__actions">
              <button type="submit" class="chip">Aplicar</button>
              <a class="chip" href="?ym=<?= safe($activeYm) ?>">Limpar</a>
            </div>
          </form>
        </div>

        <div class="exec-filter__chips" id="chipsMeses" aria-label="Filtros por mês">
          <?php foreach ($meses as $mm => $label): ?>
            <?php
            // se for o ano atual, não mostra meses futuros
            if ($y === $nowY && $mm > $nowM) {
              continue;
            }

            $ym = sprintf('%04d-%02d', $y, $mm);
            ?>
            <a class="chip <?= $ym === $activeYm && !$hasCustomRange ? 'is-active' : '' ?>" href="?ym=<?= safe($ym) ?>">
              <?= safe($label) ?>/<?= substr((string) $y, 2, 2) ?>
            </a>
          <?php endforeach; ?>
        </div>

        <div class="rank-meta">
          <span class="chip">NFs: <b><?= (int) $totalDocs ?></b></span>
          <span class="chip">Faturado: <b><?= safe(moneyBR($faturadoMes)) ?></b></span>
          <span class="chip">Ticket: <b><?= safe(moneyBR($ticketMedio)) ?></b></span>
          <span class="chip">Itens 000070: <b><?= (int) count($itemsMonth) ?></b></span>
        </div>

      </div>
    </div>

    <?php if ($error): ?>
      <div class="card grid-col-span-3">
        <div class="errbox"><?= safe($error) ?></div>
      </div>
    <?php endif; ?>

    <div class="card grid-col-span-3">
      <div class="rankings-grid">

        <div class="data-table-card top-card mini-card">
          <div class="mini-head">
            <h3 class="mini-title">🏆 Vendedores</h3>
            <span class="mini-badge">Top 10</span>
          </div>

          <?php if (!count($topSellers)): ?>
            <div class="muted" style="padding:6px 0;">Sem dados.</div>
          <?php else: ?>
            <div class="mini-list">
              <?php $i = 1;
              foreach ($topSellers as $name => $val): ?>
                <div class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                  <div class="mini-rank"><?= $i ?></div>
                  <div class="mini-name" title="<?= safe($name) ?>"><?= safe($name) ?></div>
                  <div class="mini-amt"><?= safe(moneyBR($val)) ?></div>
                </div>
                <?php $i++; endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="data-table-card top-card mini-card">
          <div class="mini-head">
            <h3 class="mini-title">🗺️ Estados</h3>
            <span class="mini-badge">Top 10</span>
          </div>

          <?php if (!count($topStates)): ?>
            <div class="muted" style="padding:6px 0;">Sem dados.</div>
          <?php else: ?>
            <div class="mini-list">
              <?php $i = 1;
              foreach ($topStates as $uf => $val): ?>
                <div class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                  <div class="mini-rank"><?= $i ?></div>
                  <div class="mini-name" title="<?= safe($uf) ?>"><?= safe($uf) ?></div>
                  <div class="mini-amt"><?= safe(moneyBR($val)) ?></div>
                </div>
                <?php $i++; endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="kpi-hero" style="height:100%;">
          <div>
            <span class="kpi-label">Ticket médio</span>
            <strong class="kpi-value"><?= safe(moneyBR($ticketMedio)) ?></strong>

            <div class="kpi-subgrid">
              <div class="kpi-pill">
                <span>Faturado</span>
                <b><?= safe(moneyBR($faturadoMes)) ?></b>
              </div>
              <div class="kpi-pill">
                <span>NFs</span>
                <b><?= (int) $totalDocs ?></b>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="card grid-col-span-3">
      <div class="card__header" style="margin:0;">
        <h2 class="card__title">Análise de Preço</h2>
        <p class="card__subtitle">Base: PRECO_TABELA, PRECO_PRATICADO, QTDE • DESCONTO = (Tabela − Praticado) e %</p>
      </div>

      <div style="margin-top:12px;display:flex;gap:10px;flex-wrap:wrap;">
        <span class="pill">Tabela: <b><?= safe(moneyBR($totTabela)) ?></b></span>
        <span class="pill">Praticado: <b><?= safe(moneyBR($totPraticado)) ?></b></span>
        <span class="pill">Desconto: <b><?= safe(moneyBR($totDesc)) ?></b></span>
        <span class="pill">Desconto médio: <b><?= number_format($descPctGeral * 100, 2, ',', '.') ?>%</b></span>
        <span class="pill">Base mínima ranking: <b><?= safe(moneyBR($MIN_TABELA_R)) ?></b></span>
        <span class="pill">Linhas válidas: <b><?= (int) $priceStats['linhas_validas'] ?></b></span>
        <span class="pill">Com desconto: <b><?= (int) $priceStats['linhas_desc_real'] ?></b></span>
      </div>

      <div class="d5-wrap">

        <div class="d5-sector">
          <div class="d5-sector__head">
            <div>
              <h3 class="d5-sector__title">Vendedor</h3>
              <div class="d5-sector__hint">Quem está dando desconto demais (por % e por impacto em R$)</div>
            </div>
          </div>

          <div class="d5-grid">
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por % (ponderado)</h4>
                <span class="d5-badge">% alto</span>
              </div>

              <?php if (!count($topVendorPct)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i = 1;
                  foreach ($topVendorPct as $name => $v): ?>
                    <div
                      class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format((float) $v['pct'] * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float) $v['desc'])) ?></div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por R$ (impacto)</h4>
                <span class="d5-badge">R$ alto</span>
              </div>

              <?php if (!count($topVendorR)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i = 1;
                  foreach ($topVendorR as $name => $v): ?>
                    <?php $pct = ((float) $v['tabela'] > 0) ? ((float) $v['desc'] / (float) $v['tabela']) : 0.0; ?>
                    <div
                      class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format($pct * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float) $v['desc'])) ?></div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="d5-sector">
          <div class="d5-sector__head">
            <div>
              <h3 class="d5-sector__title">Cliente</h3>
              <div class="d5-sector__hint">Clientes que mais “forçam” desconto (por % e por impacto em R$)</div>
            </div>
          </div>

          <div class="d5-grid">
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por % (ponderado)</h4>
                <span class="d5-badge">% alto</span>
              </div>

              <?php if (!count($topClientPct)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i = 1;
                  foreach ($topClientPct as $name => $v): ?>
                    <div
                      class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format((float) $v['pct'] * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float) $v['desc'])) ?></div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por R$ (impacto)</h4>
                <span class="d5-badge">R$ alto</span>
              </div>

              <?php if (!count($topClientR)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i = 1;
                  foreach ($topClientR as $name => $v): ?>
                    <?php $pct = ((float) $v['tabela'] > 0) ? ((float) $v['desc'] / (float) $v['tabela']) : 0.0; ?>
                    <div
                      class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format($pct * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float) $v['desc'])) ?></div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="d5-sector">
          <div class="d5-sector__head">
            <div>
              <h3 class="d5-sector__title">Produto</h3>
              <div class="d5-sector__hint">Produtos com maior erosão de preço vs tabela (por % e por impacto em R$)
              </div>
            </div>
          </div>

          <div class="d5-grid">
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por % (ponderado)</h4>
                <span class="d5-badge">% alto</span>
              </div>

              <?php if (!count($topProdPct)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i = 1;
                  foreach ($topProdPct as $name => $v): ?>
                    <div
                      class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format((float) $v['pct'] * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float) $v['desc'])) ?></div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por R$ (impacto)</h4>
                <span class="d5-badge">R$ alto</span>
              </div>

              <?php if (!count($topProdR)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i = 1;
                  foreach ($topProdR as $name => $v): ?>
                    <?php $pct = ((float) $v['tabela'] > 0) ? ((float) $v['desc'] / (float) $v['tabela']) : 0.0; ?>
                    <div
                      class="mini-row <?= ($i === 1 ? 'is-top1' : ($i === 2 ? 'is-top2' : ($i === 3 ? 'is-top3' : ''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format($pct * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float) $v['desc'])) ?></div>
                    </div>
                    <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div>
    </div>

  </section>

  <script>
    (function () {
      const RELOAD_MS = <?= (int) (CACHE_TTL * 1000) ?>;

      setInterval(() => {
        if (window.PopperLoading) {
          window.PopperLoading.show('Atualizando…', 'Recarregando dados do dashboard');
        }
        setTimeout(() => window.location.reload(), 120);
      }, RELOAD_MS);
    })();
  </script>

  <script src="/assets/js/loader.js?v=<?= filemtime(__DIR__ . '/assets/js/loader.js') ?>"></script>
  <script>
    (function () {
      function showInitial() {
        if (window.PopperLoading) {
          window.PopperLoading.show('Carregando…', 'Montando rankings e cálculos');
        }
      }

      showInitial();

      window.addEventListener('load', () => {
        window.PopperLoading && window.PopperLoading.hide();
      }, { once: true });

      window.addEventListener('pageshow', (e) => {
        if (e.persisted && window.PopperLoading) window.PopperLoading.hide();
      });

      document.addEventListener('click', (ev) => {
        const a = ev.target && ev.target.closest ? ev.target.closest('#chipsMeses a.chip') : null;
        if (!a) return;

        ev.preventDefault();

        const mesTxt = (a.textContent || '').trim();
        if (window.PopperLoading) {
          window.PopperLoading.show('Carregando…', 'Trocando para ' + (mesTxt || 'outro mês'));
        }

        setTimeout(() => { window.location.href = a.href; }, 30);
      }, { passive: false });
    })();
  </script>
  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>