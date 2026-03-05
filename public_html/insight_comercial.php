<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_login();

require_once __DIR__ . '/app/config-totvs.php';

const CACHE_DIR = __DIR__ . '/cache';
const CACHE_TTL = 600;              
const CONSULTA_RANKINGS = '000070'; 

$nowY = (int)date('Y');
$nowM = (int)date('m');

$activeYm = ($nowY === 2026)
  ? sprintf('2026-%02d', $nowM)
  : '2026-02';

if (isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', (string)$_GET['ym'])) {
  $activeYm = (string)$_GET['ym'];
}

$meses = [
  1 => 'Jan', 2 => 'Fev', 3 => 'Mar', 4 => 'Abr', 5 => 'Mai', 6 => 'Jun',
  7 => 'Jul', 8 => 'Ago', 9 => 'Set', 10 => 'Out', 11 => 'Nov', 12 => 'Dez'
];

$y = (int)substr($activeYm, 0, 4);
$m = (int)substr($activeYm, 5, 2);
$monthStart = sprintf('%04d-%02d-01', $y, $m);
$monthEnd   = date('Y-m-t', strtotime($monthStart));

$monthStartTs = strtotime($monthStart . ' 00:00:00');
$monthEndTs   = strtotime($monthEnd . ' 23:59:59');

// =========================
// CACHE
// =========================
function ensureCacheDir(): void {
  if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0775, true);
}
function cacheFile(string $key): string {
  $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
  return CACHE_DIR . '/dash_' . $safeKey . '.json';
}
function cacheRead(string $key): ?array {
  $p = cacheFile($key);
  if (!is_file($p)) return null;
  if ((time() - (int)filemtime($p)) > CACHE_TTL) return null;
  $raw = (string)@file_get_contents($p);
  $j = json_decode($raw, true);
  return is_array($j) ? $j : null;
}
function cacheWrite(string $key, array $data): void {
  ensureCacheDir();
  @file_put_contents(cacheFile($key), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// =========================
// HELPERS
// =========================
function pickString(array $row, array $keys): ?string {
  foreach ($keys as $k) {
    if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return (string)$row[$k];
  }
  $lower = [];
  foreach ($row as $k => $v) $lower[strtolower((string)$k)] = $v;
  foreach ($keys as $k) {
    $lk = strtolower($k);
    if (array_key_exists($lk, $lower) && $lower[$lk] !== null && $lower[$lk] !== '') return (string)$lower[$lk];
  }
  return null;
}
function pickFloat(array $row, array $keys): float {
  $v = pickString($row, $keys);
  if ($v === null) return 0.0;
  $v = str_replace([' ', "\u{00A0}"], '', $v);
  if (str_contains($v, ',') && str_contains($v, '.')) {
    $v = str_replace('.', '', $v);
    $v = str_replace(',', '.', $v);
  } else {
    $v = str_replace(',', '.', $v);
  }
  return (float)$v;
}
function yyyymmdd_to_ts(?string $yyyymmdd): ?int {
  if (!$yyyymmdd || !preg_match('/^\d{8}$/', $yyyymmdd)) return null;
  $y = (int)substr($yyyymmdd, 0, 4);
  $m = (int)substr($yyyymmdd, 4, 2);
  $d = (int)substr($yyyymmdd, 6, 2);
  if (!checkdate($m, $d, $y)) return null;
  return strtotime(sprintf('%04d-%02d-%02d 12:00:00', $y, $m, $d));
}

function totvsNormalizeItems(array $data): array {
  if (isset($data['items']) && is_array($data['items'])) return $data['items'];
  if (isset($data['Itms']) && is_array($data['Itms'])) return $data['Itms'];
  if (isset($data['result']) && is_array($data['result'])) return $data['result'];
  if (isset($data['value']) && is_array($data['value'])) return $data['value'];
  if (isset($data[0]) && is_array($data[0])) return $data;
  return [];
}

/**
 * Tenta com parâmetros; se não permitir, tenta sem.
 */
function totvsFetchItems(string $consulta, string $monthStart, string $monthEnd, array &$debug): array {
  $baseUrl = totvsConsultaUrl($consulta);

  $urlWith = $baseUrl . '?dt_ini=' . urlencode($monthStart) . '&dt_fim=' . urlencode($monthEnd);
  $resp1 = callTotvsApi($urlWith);

  $debug['try1'] = [
    'url' => $urlWith,
    'http' => (int)($resp1['info']['http_code'] ?? 0),
    'success' => (bool)$resp1['success'],
  ];

  $msg1 = '';
  if (is_array($resp1['data']) && isset($resp1['data']['errorMessage'])) $msg1 = (string)$resp1['data']['errorMessage'];
  if ($msg1 === '' && (string)($resp1['raw'] ?? '') !== '') {
    $j = json_decode((string)$resp1['raw'], true);
    if (is_array($j) && isset($j['errorMessage'])) $msg1 = (string)$j['errorMessage'];
  }

  if ((int)($resp1['info']['http_code'] ?? 0) === 400 && stripos($msg1, 'não permite') !== false) {
    $resp2 = callTotvsApi($baseUrl);

    $debug['try2'] = [
      'url' => $baseUrl,
      'http' => (int)($resp2['info']['http_code'] ?? 0),
      'success' => (bool)$resp2['success'],
    ];

    if (!$resp2['success'] || !is_array($resp2['data'])) {
      $debug['totvs_info'] = $resp2['info'] ?? [];
      $debug['raw_preview'] = substr((string)($resp2['raw'] ?? ''), 0, 700);
      throw new Exception('Falha TOTVS. HTTP ' . (int)($resp2['info']['http_code'] ?? 0));
    }
    return totvsNormalizeItems($resp2['data']);
  }

  if (!$resp1['success'] || !is_array($resp1['data'])) {
    $debug['totvs_info'] = $resp1['info'] ?? [];
    $debug['raw_preview'] = substr((string)($resp1['raw'] ?? ''), 0, 700);
    throw new Exception('Falha TOTVS. HTTP ' . (int)($resp1['info']['http_code'] ?? 0) . ($msg1 ? (' - ' . $msg1) : ''));
  }

  return totvsNormalizeItems($resp1['data']);
}

// =========================
// BUSCA (CACHE)
// =========================
$debug = ['ym'=>$activeYm,'start'=>$monthStart,'end'=>$monthEnd,'consulta'=>CONSULTA_RANKINGS];
$cacheKey = 'rankings_' . CONSULTA_RANKINGS . '_' . $activeYm;

$error = null;
$items = [];

$cached = cacheRead($cacheKey);
if ($cached && isset($cached['items']) && is_array($cached['items'])) {
  $items = $cached['items'];
  $debug['cache'] = 'HIT';
} else {
  $debug['cache'] = 'MISS';
  try {
    $items = totvsFetchItems(CONSULTA_RANKINGS, $monthStart, $monthEnd, $debug);
    cacheWrite($cacheKey, ['items' => $items, 'fetched_at' => date('c')]);
  } catch (Throwable $e) {
    $error = $e->getMessage();
  }
}

// =========================
// FILTRO POR MÊS (EMISAO = YYYYMMDD)
// =========================
$itemsMonth = [];
foreach ($items as $row) {
  if (!is_array($row)) continue;
  $emi = pickString($row, ['EMISAO']);
  $ts = yyyymmdd_to_ts($emi);
  if ($ts === null) continue;
  if ($ts < $monthStartTs || $ts > $monthEndTs) continue;
  $itemsMonth[] = $row;
}
$debug['items_total'] = is_array($items) ? count($items) : 0;
$debug['items_periodo'] = count($itemsMonth);

// =========================
// AGREGAÇÕES (Ranking/Estado) + Ticket por NF
// =========================
$bySeller = [];
$byState  = [];
$nfTotals = []; // NF => total (para ticket médio)

foreach ($itemsMonth as $row) {
  if (!is_array($row)) continue;

  $seller = pickString($row, ['VENDEDOR']) ?? 'Sem vendedor';
  $uf     = pickString($row, ['ESTADO']) ?? 'N/D';
  $nf     = pickString($row, ['NF']) ?? '';
  $value  = pickFloat($row, ['VALOR']);

  if ($value <= 0) continue;

  $bySeller[$seller] = ($bySeller[$seller] ?? 0) + $value;
  $byState[$uf]      = ($byState[$uf] ?? 0) + $value;

  if ($nf !== '') $nfTotals[$nf] = ($nfTotals[$nf] ?? 0) + $value;
}

arsort($bySeller);
arsort($byState);

$topSellers = array_slice($bySeller, 0, 10, true);
$topStates  = array_slice($byState, 0, 10, true);

$totalValue = array_sum($nfTotals);
$totalDocs  = count($nfTotals);
$ticketMedio = ($totalDocs > 0) ? ($totalValue / $totalDocs) : 0.0;

// =========================
// 🟡 DASHBOARD 5 — ANÁLISE DE PREÇO (FORTE)
// PRECO_TABELA, PRECO_PRATICADO, QTDE
// =========================
$priceAggVendor = [];  // vendedor => ['tabela'=>, 'praticado'=>, 'desc'=>, 'itens'=>]
$priceAggClient = [];  // cliente  => ...
$priceAggProd   = [];  // produto  => ...
$totTabela = 0.0;
$totPraticado = 0.0;
$totDesc = 0.0;

foreach ($itemsMonth as $row) {
  if (!is_array($row)) continue;

  $seller = pickString($row, ['VENDEDOR']) ?? 'Sem vendedor';
  $client = pickString($row, ['CLIENTE']) ?? 'Sem cliente';
  $prod   = pickString($row, ['PRODUTO']) ?? 'Sem produto';

  $qtde = (int)pickFloat($row, ['QTDE']);
  if ($qtde <= 0) continue;

  $pt = pickFloat($row, ['PRECO_TABELA']);
  $pp = pickFloat($row, ['PRECO_PRATICADO']);

  if ($pt <= 0 || $pp <= 0) continue;

  $valTabela = $pt * $qtde;
  $valPrat   = $pp * $qtde;
  $descR = $valTabela - $valPrat;

  // ignora casos com preço praticado maior que tabela
  if ($descR <= 0) continue;

  $totTabela += $valTabela;
  $totPraticado += $valPrat;
  $totDesc += $descR;

  if (!isset($priceAggVendor[$seller])) $priceAggVendor[$seller] = ['tabela'=>0.0,'praticado'=>0.0,'desc'=>0.0,'itens'=>0];
  $priceAggVendor[$seller]['tabela'] += $valTabela;
  $priceAggVendor[$seller]['praticado'] += $valPrat;
  $priceAggVendor[$seller]['desc'] += $descR;
  $priceAggVendor[$seller]['itens']++;

  if (!isset($priceAggClient[$client])) $priceAggClient[$client] = ['tabela'=>0.0,'praticado'=>0.0,'desc'=>0.0,'itens'=>0];
  $priceAggClient[$client]['tabela'] += $valTabela;
  $priceAggClient[$client]['praticado'] += $valPrat;
  $priceAggClient[$client]['desc'] += $descR;
  $priceAggClient[$client]['itens']++;

  if (!isset($priceAggProd[$prod])) $priceAggProd[$prod] = ['tabela'=>0.0,'praticado'=>0.0,'desc'=>0.0,'itens'=>0];
  $priceAggProd[$prod]['tabela'] += $valTabela;
  $priceAggProd[$prod]['praticado'] += $valPrat;
  $priceAggProd[$prod]['desc'] += $descR;
  $priceAggProd[$prod]['itens']++;
}

// helpers de ranking
$MIN_TABELA_R = 1500.0; // evita “noise”

$rankByPct = function(array $agg) use ($MIN_TABELA_R): array {
  $out = [];
  foreach ($agg as $k => $v) {
    $t = (float)$v['tabela'];
    if ($t < $MIN_TABELA_R) continue;
    $pct = ($t > 0) ? ((float)$v['desc'] / $t) : 0.0;
    $out[$k] = ['pct'=>$pct] + $v;
  }
  uasort($out, fn($a,$b) => ($b['pct'] <=> $a['pct']));
  return array_slice($out, 0, 10, true);
};

$rankByDesc = function(array $agg) use ($MIN_TABELA_R): array {
  $out = [];
  foreach ($agg as $k => $v) {
    if ((float)$v['tabela'] < $MIN_TABELA_R) continue;
    $out[$k] = $v;
  }
  uasort($out, fn($a,$b) => ((float)$b['desc'] <=> (float)$a['desc']));
  return array_slice($out, 0, 10, true);
};

$topVendorPct = $rankByPct($priceAggVendor);
$topVendorR  = $rankByDesc($priceAggVendor);

$topClientPct = $rankByPct($priceAggClient);
$topClientR  = $rankByDesc($priceAggClient);

$topProdPct = $rankByPct($priceAggProd);
$topProdR   = $rankByDesc($priceAggProd);

$descPctGeral = ($totTabela > 0) ? ($totDesc / $totTabela) : 0.0;

require_once __DIR__ . '/app/header.php';
?>
<link rel="stylesheet" href="/assets/css/loader.css?v=<?= filemtime(__DIR__ . '/assets/css/loader.css') ?>" />
<link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
<link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
<link rel="stylesheet" href="/assets/css/dashboard-executivo.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard-executivo.css') ?>" />
<link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
<link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" />
<link rel="stylesheet" href="/assets/css/insight_comercial.css?v=<?= filemtime(__DIR__ . '/assets/css/insight_comercial.css') ?>" />



<script>document.documentElement.classList.add('dashboard-exec');</script>
<main class="container dashboard-exec">
  <section class="dashboard-grid dashboard-grid--exec">

    <div class="card grid-col-span-3 exec-filter">
      <div class="exec-filter__row">
        <div class="card__header" style="margin:0;">
          <h2 class="card__title">Comercial • Rankings & Ticket Médio</h2>
          <p class="card__subtitle">
            Período: <?= safe($monthStart) ?> até <?= safe($monthEnd) ?>
          </p>
        </div>

        <div class="exec-filter__chips" id="chipsMeses" aria-label="Filtros por mês">
          <?php foreach ($meses as $mm => $label): ?>
            <?php $ym = sprintf('2026-%02d', $mm); ?>
            <a class="chip <?= $ym === $activeYm ? 'is-active' : '' ?>"
               href="?ym=<?= safe($ym) ?>">
              <?= safe($label) ?>/26
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="rank-meta">
        <span class="chip">NFs: <b><?= (int)$totalDocs ?></b></span>
        <span class="chip">Total: <b><?= safe(moneyBR($totalValue)) ?></b></span>
        <span class="chip">Ticket: <b><?= safe(moneyBR($ticketMedio)) ?></b></span>
        <span class="chip">Itens no período: <b><?= (int)count($itemsMonth) ?></b></span>
      </div>
    </div>

    <?php if ($error): ?>
      <div class="card grid-col-span-3">
        <div class="errbox"><?= safe($error) ?></div>
      </div>
    <?php endif; ?>

    <!-- Rankings + Ticket -->
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
              <?php $i=1; foreach ($topSellers as $name => $val): ?>
                <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
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
              <?php $i=1; foreach ($topStates as $uf => $val): ?>
                <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
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
            <span class="kpi-label"> Ticket médio</span>
            <strong class="kpi-value"><?= safe(moneyBR($ticketMedio)) ?></strong>

            <div class="kpi-subgrid">
              <div class="kpi-pill">
                <span>Total</span>
                <b><?= safe(moneyBR($totalValue)) ?></b>
              </div>
              <div class="kpi-pill">
                <span>NFs</span>
                <b><?= (int)$totalDocs ?></b>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <!-- 🟡 Dashboard 5 — Análise de Preço -->
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
        <span class="pill">Filtro anti-ruído: <b><?= safe(moneyBR($MIN_TABELA_R)) ?></b></span>
      </div>

      <div class="d5-wrap">

        <!-- ===== SETOR: VENDEDOR ===== -->
        <div class="d5-sector">
          <div class="d5-sector__head">
            <div>
              <h3 class="d5-sector__title">Vendedor</h3>
              <div class="d5-sector__hint">Quem está dando desconto demais (por % e por impacto em R$)</div>
            </div>
          </div>

          <div class="d5-grid">
            <!-- Por % -->
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por % (ponderado)</h4>
                <span class="d5-badge">% alto</span>
              </div>

              <?php if (!count($topVendorPct)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i=1; foreach ($topVendorPct as $name => $v): ?>
                    <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format((float)$v['pct'] * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float)$v['desc'])) ?></div>
                    </div>
                  <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Por R$ -->
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por R$ (impacto)</h4>
                <span class="d5-badge">R$ alto</span>
              </div>

              <?php if (!count($topVendorR)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i=1; foreach ($topVendorR as $name => $v): ?>
                    <?php $pct = ((float)$v['tabela'] > 0) ? ((float)$v['desc']/(float)$v['tabela']) : 0.0; ?>
                    <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format($pct*100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float)$v['desc'])) ?></div>
                    </div>
                  <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ===== SETOR: CLIENTE ===== -->
        <div class="d5-sector">
          <div class="d5-sector__head">
            <div>
              <h3 class="d5-sector__title">Cliente</h3>
              <div class="d5-sector__hint">Clientes que mais “forçam” desconto (por % e por impacto em R$)</div>
            </div>
          </div>

          <div class="d5-grid">
            <!-- Por % -->
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por % (ponderado)</h4>
                <span class="d5-badge">% alto</span>
              </div>

              <?php if (!count($topClientPct)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i=1; foreach ($topClientPct as $name => $v): ?>
                    <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format((float)$v['pct'] * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float)$v['desc'])) ?></div>
                    </div>
                  <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Por R$ -->
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por R$ (impacto)</h4>
                <span class="d5-badge">R$ alto</span>
              </div>

              <?php if (!count($topClientR)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i=1; foreach ($topClientR as $name => $v): ?>
                    <?php $pct = ((float)$v['tabela'] > 0) ? ((float)$v['desc']/(float)$v['tabela']) : 0.0; ?>
                    <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format($pct*100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float)$v['desc'])) ?></div>
                    </div>
                  <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- ===== SETOR: PRODUTO ===== -->
        <div class="d5-sector">
          <div class="d5-sector__head">
            <div>
              <h3 class="d5-sector__title">Produto</h3>
              <div class="d5-sector__hint">Produtos com maior erosão de preço vs tabela (por % e por impacto em R$)</div>
            </div>
          </div>

          <div class="d5-grid">
            <!-- Por % -->
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por % (ponderado)</h4>
                <span class="d5-badge">% alto</span>
              </div>

              <?php if (!count($topProdPct)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i=1; foreach ($topProdPct as $name => $v): ?>
                    <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format((float)$v['pct'] * 100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float)$v['desc'])) ?></div>
                    </div>
                  <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Por R$ -->
            <div class="d5-subcard">
              <div class="d5-subhead">
                <h4 class="d5-subtitle">Top 10 por R$ (impacto)</h4>
                <span class="d5-badge">R$ alto</span>
              </div>

              <?php if (!count($topProdR)): ?>
                <div class="muted" style="padding:8px 0;">Sem dados suficientes (ou abaixo do mínimo de tabela).</div>
              <?php else: ?>
                <div class="mini-list">
                  <?php $i=1; foreach ($topProdR as $name => $v): ?>
                    <?php $pct = ((float)$v['tabela'] > 0) ? ((float)$v['desc']/(float)$v['tabela']) : 0.0; ?>
                    <div class="mini-row <?= ($i===1?'is-top1':($i===2?'is-top2':($i===3?'is-top3':''))) ?>">
                      <div class="mini-rank"><?= $i ?></div>
                      <div class="mini-name" title="<?= safe($name) ?>">
                        <?= safe($name) ?> • <?= number_format($pct*100, 2, ',', '.') ?>%
                      </div>
                      <div class="mini-amt"><?= safe(moneyBR((float)$v['desc'])) ?></div>
                    </div>
                  <?php $i++; endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

      </div><!-- /d5-wrap -->
    </div><!-- /card d5 -->
  </section>

  <script>
  (function(){
    const RELOAD_MS = <?= (int)(CACHE_TTL * 1000) ?>;

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
    // 1) Carregamento inicial (mostra o loader o mais cedo possível no client)
    function showInitial() {
      if (window.PopperLoading) {
        window.PopperLoading.show('Carregando…', 'Montando rankings e cálculos');
      }
    }

    // mostra imediatamente
    showInitial();

    // esconde quando tudo carregou (imagens, CSS, etc.)
    window.addEventListener('load', () => {
      window.PopperLoading && window.PopperLoading.hide();
    }, { once: true });

    // se voltar pelo histórico (bfcache), garante que não fica travado aberto
    window.addEventListener('pageshow', (e) => {
      if (e.persisted && window.PopperLoading) window.PopperLoading.hide();
    });

    // 2) Troca de mês (chips)
    document.addEventListener('click', (ev) => {
      const a = ev.target && ev.target.closest ? ev.target.closest('#chipsMeses a.chip') : null;
      if (!a) return;

      // evita navegar instantâneo pra dar tempo de renderizar o loader
      ev.preventDefault();

      const mesTxt = (a.textContent || '').trim(); // ex: "Fev/26"
      if (window.PopperLoading) {
        window.PopperLoading.show('Carregando…', 'Trocando para ' + (mesTxt || 'outro mês'));
      }

      // navega logo em seguida
      setTimeout(() => { window.location.href = a.href; }, 30);
    }, { passive: false });
  })();
</script>
  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>