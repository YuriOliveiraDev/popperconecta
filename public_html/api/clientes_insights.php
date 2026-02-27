<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool {
    if ($array === []) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }
}

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/config-totvs.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$force = (isset($_GET['force']) && $_GET['force'] === '1');

$ym = (string)($_GET['ym'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

$fromMonth  = strtotime($ym . '-01 00:00:00');
$toMonthEnd = strtotime(date('Y-m-t 23:59:59', $fromMonth));

$now = time();
$isCurrentMonth = (date('Y-m', $now) === $ym);
$toToday = $isCurrentMonth ? strtotime(date('Y-m-d 23:59:59', $now)) : $toMonthEnd;

// cache
$cacheMinutes = 10;
$cacheSeconds = $cacheMinutes * 60;
$cacheKey  = 'clientes_exec_' . $ym;
$cacheFile = sys_get_temp_dir() . '/totvs_' . preg_replace('/[^a-z0-9_\-]/i', '_', $cacheKey) . '.json';

if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheSeconds)) {
  $cached = file_get_contents($cacheFile);
  $data = json_decode((string)$cached, true);
  if (is_array($data)) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// helpers
$extractItems = function ($data): array {
  if (!is_array($data)) return [];
  if (array_is_list($data)) return $data;

  if (isset($data['items']) && is_array($data['items'])) return $data['items'];
  if (isset($data['value']) && is_array($data['value'])) return $data['value'];

  foreach ($data as $v) {
    if (is_array($v) && array_is_list($v)) return $v;
    if (is_array($v) && isset($v['items']) && is_array($v['items'])) return $v['items'];
  }
  return [];
};

$parseYmdToTs = function (?string $ymd): ?int {
  $ymd = preg_replace('/\D+/', '', (string)$ymd);
  if (strlen($ymd) !== 8) return null;
  $y = (int)substr($ymd, 0, 4);
  $m = (int)substr($ymd, 4, 2);
  $d = (int)substr($ymd, 6, 2);
  if (!checkdate($m, $d, $y)) return null;
  $ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d));
  return $ts ?: null;
};

$toFloat = function ($v): float {
  if (is_float($v) || is_int($v)) return (float)$v;
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' '], '', $s);
  if (str_contains($s, ',') && str_contains($s, '.')) {
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  } else {
    $s = str_replace(',', '.', $s);
  }
  return (float)$s;
};

$clamp01 = fn($x) => max(0.0, min(1.0, (float)$x));

// TOTVS 000070
$resp = callTotvsApi('000070');
$info = [
  'success'  => false,
  'consulta' => '000070',
  'http_code' => $resp['info']['http_code'] ?? 0,
  'error'     => $resp['info']['error'] ?? null,
  'errno'     => $resp['info']['errno'] ?? null,
  'itens'     => 0,
];

$items = [];
if (!empty($resp['success']) && is_array($resp['data'])) {
  $items = $extractItems($resp['data']);
  $info['itens'] = count($items);
  $info['success'] = true;
}

// Agregações
$clients = [];          // key => aggregates
$nfByClient = [];       // key => set NF
$daysByClient = [];     // key => [ymd=>valor]
$totalPedidosSet = [];  // NF global set
$totalValorMes = 0.0;
$totalCustoMes = 0.0;
$descAll = [];

foreach ($items as $row) {
  if (!is_array($row)) continue;

  $ts = $parseYmdToTs((string)($row['EMISAO'] ?? ''));
  if ($ts === null) continue;
  if ($ts < $fromMonth || $ts > $toToday) continue;

  $cod  = trim((string)($row['COD_CLIENTE'] ?? ''));
  $loja = trim((string)($row['LOJA_CLIENTE'] ?? ''));
  $nome = (string)($row['CLIENTE'] ?? 'Cliente');
  if ($cod === '') continue;

  $key = $cod . '|' . $loja;

  $nf    = trim((string)($row['NF'] ?? ''));
  $valor = $toFloat($row['VALOR'] ?? 0);
  $custo = $toFloat($row['CUSTO'] ?? 0);

  $tabela = $toFloat($row['PRECO_TABELA'] ?? 0);
  $prat   = $toFloat($row['PRECO_PRATICADO'] ?? 0);
  $desc = 0.0;
  if ($tabela > 0) {
    $desc = ($tabela - $prat) / $tabela;
    $desc = max(0.0, min(1.0, $desc));
    $descAll[] = $desc;
  }

  if (!isset($clients[$key])) {
    $clients[$key] = [
      'key' => $key,
      'cliente' => $nome,
      'valor' => 0.0,
      'custo' => 0.0,
      'desc_sum' => 0.0,
      'desc_cnt' => 0,
      'first_ts' => $ts,
      'last_ts' => $ts,
    ];
    $nfByClient[$key] = [];
  }

  $clients[$key]['valor'] += $valor;
  $clients[$key]['custo'] += $custo;

  if ($tabela > 0) {
    $clients[$key]['desc_sum'] += $desc;
    $clients[$key]['desc_cnt']++;
  }

  if ($ts < (int)$clients[$key]['first_ts']) $clients[$key]['first_ts'] = $ts;
  if ($ts > (int)$clients[$key]['last_ts'])  $clients[$key]['last_ts']  = $ts;

  if ($nf !== '') {
    $nfByClient[$key][$nf] = true;
    $totalPedidosSet[$nf] = true;
  }

  $ymd = date('Y-m-d', $ts);
  if (!isset($daysByClient[$key])) $daysByClient[$key] = [];
  if (!isset($daysByClient[$key][$ymd])) $daysByClient[$key][$ymd] = 0.0;
  $daysByClient[$key][$ymd] += $valor;

  $totalValorMes += $valor;
  $totalCustoMes += $custo;
}

// lista final clientes com métricas
$list = array_values($clients);
foreach ($list as &$c) {
  $v   = (float)$c['valor'];
  $cst = (float)$c['custo'];
  $marg = $v - $cst;
  $margPct = ($v > 0) ? ($marg / $v) : 0.0;

  $ped = isset($nfByClient[$c['key']]) ? count($nfByClient[$c['key']]) : 0;
  $ticket = ($ped > 0) ? ($v / $ped) : $v;

  $dc = (int)$c['desc_cnt'];
  $descMed = ($dc > 0) ? ((float)$c['desc_sum'] / $dc) : 0.0;

  $c['margem'] = $marg;
  $c['margem_pct'] = $margPct;
  $c['pedidos'] = $ped;
  $c['ticket_medio'] = $ticket;
  $c['desconto_medio'] = $descMed;
}
unset($c);

// KPIs (Linha 1)
$clientesAtivos = count($list);
$pedidosMes = count($totalPedidosSet);
$ticketMedio = ($pedidosMes > 0) ? ($totalValorMes / $pedidosMes) : 0.0;

$margMes = $totalValorMes - $totalCustoMes;
$margPctMes = ($totalValorMes > 0) ? ($margMes / $totalValorMes) : 0.0;

$margMediaCliente = ($clientesAtivos > 0) ? ($margMes / $clientesAtivos) : 0.0;

// média simples de desconto (geral)
$descMedGeral = (count($descAll) > 0) ? (array_sum($descAll) / count($descAll)) : 0.0;

// =========================
// Ranking: Top 50 e Top 10 (FIX)
// =========================
usort($list, fn($a,$b)=> ((float)$b['valor'] <=> (float)$a['valor']));
$top50 = array_slice($list, 0, 50);
$top10 = array_slice($list, 0, 10);

// Top3% (baseado no Top10)
$top3Sum = 0.0;
for ($i=0; $i<min(3, count($top10)); $i++) $top3Sum += (float)$top10[$i]['valor'];
$top3Pct = ($totalValorMes > 0) ? ($top3Sum / $totalValorMes) : 0.0;

// Curva ABC (Pareto) com base em valor (usa lista já ordenada desc)
$cum=0.0;
$abcItems=[];
foreach ($list as $c) {
  $cum += (float)$c['valor'];
  $cumPct = ($totalValorMes > 0) ? ($cum / $totalValorMes) : 0.0;
  $abcItems[] = [
    'key' => (string)$c['key'],
    'cliente' => (string)$c['cliente'],
    'valor' => round((float)$c['valor'], 2),
    'cum_pct' => round($cumPct, 6),
  ];
}

// Evolução (cliente selecionado): gera para top10
$monthDays = (int)date('t', $fromMonth);
$labels = [];
for ($d=1; $d<=$monthDays; $d++) $labels[] = str_pad((string)$d, 2, '0', STR_PAD_LEFT);

$evolucao = [];
foreach ($top10 as $c) {
  $key = (string)$c['key'];
  $map = $daysByClient[$key] ?? [];
  $vals = [];
  for ($d=1; $d<=$monthDays; $d++) {
    $ymd = sprintf('%s-%02d', $ym, $d);
    $vals[] = round((float)($map[$ymd] ?? 0.0), 2);
  }
  $evolucao[$key] = ['labels'=>$labels,'valores'=>$vals];
}

// Margem por cliente (top10 por valor) -> mostra margem%
$margTop10 = array_map(fn($c)=>[
  'key' => (string)$c['key'],
  'cliente' => (string)$c['cliente'],
  'margem_pct' => round((float)$c['margem_pct'], 6),
], $top10);

// Frequência (histograma) aproximada: usa first/last e pedidos
$bins = [
  ['label' => '0-7',   'count' => 0],
  ['label' => '8-15',  'count' => 0],
  ['label' => '16-30', 'count' => 0],
  ['label' => '31+',   'count' => 0],
];

foreach ($list as $c) {
  $ped = (int)$c['pedidos'];
  if ($ped <= 1) continue;

  $spanDays = (int)max(1, floor((((int)$c['last_ts']) - ((int)$c['first_ts'])) / 86400));
  $avgGap = (float)$spanDays / max(1, ($ped - 1));

  if ($avgGap <= 7) $bins[0]['count']++;
  else if ($avgGap <= 15) $bins[1]['count']++;
  else if ($avgGap <= 30) $bins[2]['count']++;
  else $bins[3]['count']++;
}

// Score (all -> top50/top10) — 40% fat, 30% margem, 20% freq, 10% desconto baixo
$maxFat = 0.0; $maxMargPct = 0.0; $maxPed = 0;
foreach ($list as $c){
  $maxFat = max($maxFat, (float)$c['valor']);
  $maxMargPct = max($maxMargPct, (float)$c['margem_pct']);
  $maxPed = max($maxPed, (int)$c['pedidos']);
}
$maxFat = $maxFat ?: 1.0;
$maxMargPct = $maxMargPct ?: 1.0;
$maxPed = $maxPed ?: 1;

$scoreAll = [];
foreach ($list as $c){
  $fatN  = (float)$c['valor'] / $maxFat;
  $margN = ((float)$c['margem_pct'] > 0) ? ((float)$c['margem_pct'] / $maxMargPct) : 0.0;
  $freqN = (int)$c['pedidos'] / $maxPed;
  $descN = 1.0 - (float)$c['desconto_medio'];

  $score = 0.40*$clamp01($fatN) + 0.30*$clamp01($margN) + 0.20*$clamp01($freqN) + 0.10*$clamp01($descN);

  $scoreAll[] = [
    'key' => (string)$c['key'],
    'cliente' => (string)$c['cliente'],
    'score' => round($score, 6),
  ];
}
usort($scoreAll, fn($a,$b)=> ((float)$b['score'] <=> (float)$a['score']));
$scoreTop50 = array_slice($scoreAll, 0, 50);
$scoreTop10 = array_slice($scoreAll, 0, 10);

// =========================
// Insight Cliente
// =========================
$insPorCliente = [];

// média margem% e desconto dos top10 (referência)
$avgMargTop10 = 0.0;
$avgDescTop10 = 0.0;
$cntTop = max(1, count($top10));
foreach ($top10 as $c){
  $avgMargTop10 += (float)$c['margem_pct'];
  $avgDescTop10 += (float)$c['desconto_medio'];
}
$avgMargTop10 /= $cntTop;
$avgDescTop10 /= $cntTop;

// crescimento aproximado: compara primeira metade do mês vs segunda metade (no mês)
$midDay = (int)floor($monthDays/2);

foreach ($top10 as $c) {
  $key = (string)$c['key'];
  $map = $daysByClient[$key] ?? [];

  $v1 = 0.0; $v2 = 0.0;
  for ($d=1; $d<=$monthDays; $d++){
    $ymd2 = sprintf('%s-%02d', $ym, $d);
    $val = (float)($map[$ymd2] ?? 0.0);
    if ($d <= $midDay) $v1 += $val; else $v2 += $val;
  }

  $growth = 0.0;
  if ($v1 > 0) $growth = ($v2 - $v1) / $v1;

  $daysNoBuy = (int)floor((($toToday - (int)$c['last_ts']) / 86400));

  $tag = '📊 Insight';
  $text = '';

  if ($daysNoBuy >= 15) {
    $tag = '⚠️ Alerta';
    $text = 'Cliente Top 10 está há ' . $daysNoBuy . ' dia(s) sem compra.';
  } else {
    $text = 'Cliente aumentou compras em ' . round($growth*100) . '% (2ª metade vs 1ª metade do mês).';

    $margDiff = (float)$c['margem_pct'] - $avgMargTop10;
    if ($margDiff < -0.03) {
      $tag = '📉 Margem';
      $text .= ' Porém com margem ' . round(abs($margDiff)*100) . '% abaixo da média do Top 10.';
    } else if ($margDiff > 0.03) {
      $tag = '📈 Margem';
      $text .= ' E com margem ' . round($margDiff*100) . '% acima da média do Top 10.';
    }

    $descDiff = (float)$c['desconto_medio'] - $avgDescTop10;
    if ($descDiff > 0.05) {
      $tag = '💸 Desconto';
      $text .= ' Atenção: desconto ' . round($descDiff*100) . '% acima da média do Top 10.';
    }
  }

  $insPorCliente[$key] = [
    'tag' => $tag,
    'text' => $c['cliente'] . ': ' . $text
  ];
}

// alerta global (top1)
$alerta = 'Sem alerta';
if (count($top10) > 0) {
  $daysNoBuy = (int)floor((($toToday - (int)$top10[0]['last_ts']) / 86400));
  if ($daysNoBuy >= 10) $alerta = 'Alerta: Top 1 sem compra há ' . $daysNoBuy . ' dia(s).';
}

// =========================
// Output
// =========================
$data = [
  'updated_at' => date('d/m/Y, H:i'),
  'meta' => [
    'ym' => $ym,
    'range_mes' => [date('Y-m-d', $fromMonth), date('Y-m-d', $toToday)],
    'cache_min' => $cacheMinutes,
    'forced' => $force,
  ],

  // Linha 1 (KPIs)
  'kpis' => [
    'clientes_ativos' => $clientesAtivos,
    'ticket_medio' => round($ticketMedio, 2),
    'margem_media_cliente' => round($margMediaCliente, 2),
    'margem_pct_media' => round($margPctMes, 6),
    'desconto_medio' => round($descMedGeral, 6),
    'pedidos_mes' => $pedidosMes,
    'top3_pct' => round($top3Pct, 6),
    'faturamento_mes' => round($totalValorMes, 2),
  ],

  // Linha 2
  'ranking' => [
    'top50' => array_map(fn($c)=>[
      'key' => (string)$c['key'],
      'cliente' => (string)$c['cliente'],
      'valor' => round((float)$c['valor'], 2),
    ], $top50),

    'top10' => array_map(fn($c)=>[
      'key' => (string)$c['key'],
      'cliente' => (string)$c['cliente'],
      'valor' => round((float)$c['valor'], 2),
    ], $top10),
  ],
  'abc' => [
    'items' => $abcItems,
  ],
  'evolucao' => $evolucao,
  'evolucao_default' => ['labels'=>[],'valores'=>[]],

  // Linha 3
  'margem' => [
    'top10' => $margTop10,
  ],
  'frequencia' => [
    'bins' => $bins,
  ],
  'score' => [
    'top50' => $scoreTop50,
    'top10' => $scoreTop10,
  ],

  // Insight
  'insight' => [
    'alerta' => $alerta,
    'por_cliente' => $insPorCliente,
  ],

  // debug
  'totvs' => [
    'info' => $info,
    'clientes_filtrados' => $clientesAtivos,
  ],
];

file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
echo json_encode($data, JSON_UNESCAPED_UNICODE);