<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/header.php';
require_once __DIR__ . '/app/config-totvs.php';

// =========================
// CONFIG
// =========================
const CACHE_DIR = __DIR__ . '/cache';
const CACHE_TTL = 600;              // 10 min
const CONSULTA_RANKINGS = '000070'; // consulta item a item

// mês ativo (default)
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
?>

<link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>" />
<link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>" />
<link rel="stylesheet" href="/assets/css/dashboard-executivo.css?v=<?= filemtime(__DIR__ . '/assets/css/dashboard-executivo.css') ?>" />
<link rel="stylesheet" href="/assets/css/dropdowns.css?v=<?= filemtime(__DIR__ . '/assets/css/dropdowns.css') ?>" />
<link rel="stylesheet" href="/assets/css/index.css?v=<?= filemtime(__DIR__ . '/assets/css/index.css') ?>" />

<script>document.documentElement.classList.add('dashboard-exec');</script>

<style>
  :root{
    --p1: var(--primary1, #5c2c8c);
    --p2: var(--primary2, #39388F);
    --text: var(--text, rgba(15,23,42,.92));
    --muted: var(--muted, rgba(15,23,42,.62));
    --b1: rgba(15,23,42,.08);
    --b2: rgba(15,23,42,.06);
    --soft: rgba(15,23,42,.03);
    --radius: 18px;
    --gap: 12px;
  }

  /* ===== Cabeçalho / filtros ===== */
  .exec-filter{
    padding: 16px !important;
    border-radius: var(--radius) !important;
    border: 1px solid var(--b1) !important;
    background: linear-gradient(180deg, rgba(92,44,140,.07), rgba(255,255,255,0));
  }
  .exec-filter__row{
    display:flex;align-items:flex-start;justify-content:space-between;
    gap: 12px;flex-wrap:wrap;
  }
  .exec-filter__row .card__header{min-width:260px;}
  .exec-filter__chips{
    display:flex;gap:8px;flex-wrap:wrap;
    justify-content:flex-end;align-items:center;
    max-width: 780px;
  }
  .exec-filter__chips .chip{
    border-radius: 999px;
    padding: 7px 11px;
    font-weight: 1000;
    font-size: 12px;
    border: 1px solid rgba(15,23,42,.10);
    background: rgba(255,255,255,.70);
    backdrop-filter: blur(6px);
    transition: transform .08s ease, border-color .08s ease, background .08s ease;
    text-decoration:none;
  }
  .exec-filter__chips .chip:hover{transform: translateY(-1px);border-color: rgba(92,44,140,.22);}
  .exec-filter__chips .chip.is-active{
    border-color: rgba(92,44,140,.28);
    background: rgba(92,44,140,.10);
    color: rgba(15,23,42,.88);
  }

  /* ===== Chips resumo ===== */
  .rank-meta{
    margin-top: 12px;
    display:grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: var(--gap);
  }
  @media (max-width:980px){ .rank-meta{grid-template-columns: repeat(2, minmax(0,1fr));} }
  @media (max-width:520px){ .rank-meta{grid-template-columns: 1fr;} }

  .rank-meta .chip{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    padding: 10px 12px;
    border-radius: 14px;
    border: 1px solid var(--b1);
    background: rgba(255,255,255,.70);
  }
  .rank-meta .chip b{font-weight: 1100;color: var(--text);}

  /* ===== Grid do topo ===== */
  .rankings-grid{
    display:grid;
    grid-template-columns: 1.15fr 1.15fr .9fr;
    gap: var(--gap);
    align-items: stretch;
  }
  @media (max-width:1100px){ .rankings-grid{grid-template-columns: 1fr;} }
  .rankings-grid > *{min-width:0;}
  .rankings-grid .data-table-card{grid-column:auto !important;width:auto !important;}

  /* ===== Cards mini ===== */
  .mini-card{
    padding: 12px !important;
    border-radius: var(--radius) !important;
    border: 1px solid var(--b1) !important;
    background: linear-gradient(180deg, rgba(255,255,255,.82), rgba(255,255,255,.64));
  }
  .mini-head{
    display:flex;align-items:center;justify-content:space-between;gap:10px;
    margin-bottom: 10px;
  }
  .mini-title{
    margin:0;
    font-size: 12px;
    font-weight: 1100;
    letter-spacing: .3px;
    text-transform: uppercase;
    color: rgba(15,23,42,.76);
  }
  .mini-badge{
    font-size: 11px;
    font-weight: 1100;
    padding: 5px 10px;
    border-radius: 999px;
    background: rgba(15,23,42,.05);
    border: 1px solid rgba(15,23,42,.10);
    color: rgba(15,23,42,.70);
  }

  /* ===== Lista ===== */
  .mini-list{
    display:flex;flex-direction:column;gap: 8px;
    max-height: 340px;
    overflow:auto;
    padding-right: 6px;
  }
  .mini-row{
    display:grid;
    grid-template-columns: 24px 1fr auto;
    gap: 10px;
    align-items:center;
    padding: 9px 10px;
    border-radius: 14px;
    background: var(--soft);
    border: 1px solid var(--b2);
    transition: transform .08s ease, background .08s ease, border-color .08s ease;
  }
  .mini-row:hover{
    transform: translateY(-1px);
    background: rgba(92,44,140,.06);
    border-color: rgba(92,44,140,.16);
  }
  .mini-rank{
    width:24px;height:24px;border-radius: 10px;
    display:flex;align-items:center;justify-content:center;
    font-size: 11px;
    font-weight: 1100;
    background: rgba(92,44,140,.12);
    color: var(--p1);
  }
  .mini-name{
    font-size: 12px;
    font-weight: 950;
    color: rgba(15,23,42,.82);
    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  }
  .mini-amt{
    font-size: 12px;
    font-weight: 1100;
    color: rgba(15,23,42,.88);
    white-space:nowrap;
  }

  /* Medalhas Top 3 */
  .mini-row.is-top1{background: linear-gradient(90deg, rgba(255,215,0,.16), rgba(255,255,255,0)); border-color: rgba(255,215,0,.20);}
  .mini-row.is-top2{background: linear-gradient(90deg, rgba(192,192,192,.18), rgba(255,255,255,0)); border-color: rgba(160,160,160,.18);}
  .mini-row.is-top3{background: linear-gradient(90deg, rgba(205,127,50,.18), rgba(255,255,255,0)); border-color: rgba(205,127,50,.20);}
  .mini-row.is-top1 .mini-rank{background: rgba(255,215,0,.22); color: rgba(120,80,0,.95);}
  .mini-row.is-top2 .mini-rank{background: rgba(192,192,192,.22); color: rgba(70,70,70,.95);}
  .mini-row.is-top3 .mini-rank{background: rgba(205,127,50,.22); color: rgba(120,60,10,.95);}

  /* Scrollbar */
  .mini-list::-webkit-scrollbar{width:10px;}
  .mini-list::-webkit-scrollbar-thumb{
    background: rgba(15,23,42,.14);
    border-radius: 999px;
    border: 2px solid transparent;
    background-clip: padding-box;
  }
  .mini-list::-webkit-scrollbar-track{background: transparent;}

  /* ===== KPI HERO (Ticket) ===== */
  .kpi-hero{
    border-radius: var(--radius) !important;
    border: 1px solid rgba(92,44,140,.16) !important;
    background: radial-gradient(900px 260px at 20% 10%, rgba(92,44,140,.14), transparent 55%),
                linear-gradient(180deg, rgba(255,255,255,.78), rgba(255,255,255,.58));
    padding: 14px !important;
    display:flex;
    flex-direction:column;
    justify-content:space-between;
    min-height: 220px;
  }
  .kpi-hero .kpi-label{
    font-weight: 1100;
    letter-spacing: .35px;
    text-transform: uppercase;
    color: rgba(15,23,42,.60);
    display:flex;
    align-items:center;
    gap: 8px;
  }
  .kpi-hero .kpi-value{
    margin-top: 8px;
    font-size: 38px;
    line-height: 1.05;
    font-weight: 1200;
    color: rgba(15,23,42,.93);
    letter-spacing: -.3px;
  }
  .kpi-hero .kpi-subgrid{
    margin-top: 12px;
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
  }
  .kpi-hero .kpi-pill{
    background: rgba(255,255,255,.62);
    border: 1px solid var(--b2);
    border-radius: 16px;
    padding: 10px 12px;
  }
  .kpi-hero .kpi-pill span{
    display:block;
    font-size: 11px;
    font-weight: 1000;
    color: rgba(15,23,42,.55);
    text-transform: uppercase;
    letter-spacing:.25px;
  }
  .kpi-hero .kpi-pill b{
    display:block;
    margin-top: 4px;
    font-size: 14px;
    font-weight: 1200;
    color: rgba(15,23,42,.90);
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
  }

  /* Debug (discreto) */
  details.dash-debug{
    margin-top: 12px;
    border-top: 1px dashed rgba(15,23,42,.14);
    padding-top: 10px;
  }
  details.dash-debug summary{
    cursor:pointer;
    font-weight: 1100;
    color: rgba(15,23,42,.62);
  }
  details.dash-debug pre{
    background: rgba(15,23,42,.03);
    border: 1px solid rgba(15,23,42,.06);
    border-radius: 14px;
    padding: 10px;
    margin-top: 10px;
    max-height: 260px;
    overflow:auto;
    white-space: pre-wrap;
  }

  /* ===== Dashboard 5 ===== */
  .pill{
    display:inline-flex;align-items:center;gap:8px;
    padding: 7px 11px;border-radius:999px;
    background: rgba(92,44,140,.08);
    border: 1px solid rgba(92,44,140,.16);
    color: rgba(15,23,42,.78);
    font-weight: 1100;
    font-size: 12px;
  }
  .pill b{color: rgba(15,23,42,.92);}
  .submuted{color: rgba(15,23,42,.55);font-weight: 950;font-size: 12px;margin-top:-2px;}

  /* Dashboard 5: setores separados */
  .d5-wrap{margin-top:14px;display:flex;flex-direction:column;gap:12px;}
  .d5-sector{
    border:1px solid rgba(15,23,42,.08);
    border-radius: var(--radius);
    padding:12px;
    background: linear-gradient(180deg, rgba(255,255,255,.80), rgba(255,255,255,.62));
  }
  .d5-sector__head{
    display:flex;align-items:flex-start;justify-content:space-between;
    gap:10px;margin-bottom:10px;
  }
  .d5-sector__title{
    margin:0;font-size:12px;font-weight:1100;letter-spacing:.25px;
    text-transform:uppercase;color:rgba(15,23,42,.78);
  }
  .d5-sector__hint{
    margin-top:2px;color:rgba(15,23,42,.55);font-weight:950;font-size:12px;
  }
  .d5-grid{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:12px;
    align-items:start;
  }
  @media (max-width:1100px){ .d5-grid{grid-template-columns:1fr;} }
  .d5-subcard{
    border:1px solid rgba(15,23,42,.08);
    border-radius: 16px;
    padding:12px;
    background: rgba(15,23,42,.02);
  }
  .d5-subhead{
    display:flex;align-items:center;justify-content:space-between;
    gap:10px;margin-bottom:8px;
  }
  .d5-subtitle{margin:0;font-size:12px;font-weight:1100;color:rgba(15,23,42,.78);}
  .d5-badge{
    font-size:11px;font-weight:1100;padding:4px 10px;border-radius:999px;
    background:rgba(15,23,42,.05);
    border:1px solid rgba(15,23,42,.10);
    color:rgba(15,23,42,.70);
  }
  .d5-subcard .mini-list{max-height:300px;}
</style>

<main class="container dashboard-exec">
  <section class="dashboard-grid dashboard-grid--exec">

    <div class="card grid-col-span-3 exec-filter">
      <div class="exec-filter__row">
        <div class="card__header" style="margin:0;">
          <h2 class="card__title">Comercial • Rankings & Ticket Médio</h2>
          <p class="card__subtitle">
            Período: <?= safe($monthStart) ?> até <?= safe($monthEnd) ?>
            • Cache: <?= safe((string)($debug['cache'] ?? 'N/A')) ?> (<?= (int)(CACHE_TTL/60) ?> min)
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
    setInterval(() => window.location.reload(), <?= (int)(CACHE_TTL * 1000) ?>);
  </script>

  <script src="/assets/js/header.js?v=<?= filemtime(__DIR__ . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/dropdowns.js?v=<?= filemtime(__DIR__ . '/assets/js/dropdowns.js') ?>"></script>
  <script src="/assets/js/index-carousel.js?v=<?= filemtime(__DIR__ . '/assets/js/index-carousel.js') ?>"></script>
</main>

<?php require_once __DIR__ . '/app/footer.php'; ?>