<?php

declare(strict_types=1);


// Compatibilidade PHP < 8.1
if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool {
    if ($array === []) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }
}
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config-totvs.php'; // <-- ajuste o caminho se necessário
require_once __DIR__ . '/../app/calendario.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$dashboard_slug = $_GET['dash'] ?? 'executivo';
$force = (isset($_GET['force']) && $_GET['force'] === '1'); // atualização manual

// ======================================================
// 1) CARREGA MÉTRICAS DO BANCO (igual você já tinha)
// ======================================================
$stmt = db()->prepare('SELECT metric_key, metric_value_num, metric_value_text, updated_at FROM metrics WHERE dashboard_slug = ?');
$stmt->execute([$dashboard_slug]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$m = [];
$latestUpdatedAt = null;

foreach ($rows as $r) {
  if ($latestUpdatedAt === null && isset($r['updated_at'])) {
    $latestUpdatedAt = (string)$r['updated_at'];
  }

  $key = (string)$r['metric_key'];

  if ($r['metric_value_text'] !== null && $r['metric_value_text'] !== '') {
    $m[$key] = $r['metric_value_text'];
  } else {
    $m[$key] = (float)($r['metric_value_num'] ?? 0);
  }
}

// ======================================================
// 2) SE FINANCEIRO: mantém comportamento atual
// ======================================================
if ($dashboard_slug === 'financeiro') {
  echo json_encode([
    'updated_at' => $latestUpdatedAt ? date('d/m/Y, H:i', strtotime($latestUpdatedAt)) : date('d/m/Y, H:i'),
    'values' => [
      'faturado_dia' => (float)($m['faturado_dia'] ?? 0),
      'contas_pagar_dia' => (float)($m['contas_pagar_dia'] ?? 0),
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

// ======================================================
// 3) TOTVS 000070: CACHE 30 MIN + FORCE
// ======================================================
$cacheMinutes = 10;
$cacheSeconds = $cacheMinutes * 60;

// cache por dashboard (pra não misturar caso existam vários)
$cacheFile = sys_get_temp_dir() . '/totvs_000070_' . preg_replace('/[^a-z0-9_\-]/i', '_', $dashboard_slug) . '.json';

$totvsPayload = null;
$useCache = (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheSeconds));

if ($useCache) {
  $cached = file_get_contents($cacheFile);
  $totvsPayload = json_decode((string)$cached, true);
} else {
  // chama TOTVS 000070 (usa seu callTotvsApi atualizado)
  $resp = callTotvsApi('000070');

  $sumMonth = 0.0;
  $sumYear  = 0.0;
  $totvsOk = false;
  $totvsInfo = [
    'success' => false,
    'http_code' => $resp['info']['http_code'] ?? 0,
    'error' => $resp['info']['error'] ?? null,
    'errno' => $resp['info']['errno'] ?? null,
  ];

  // Helpers locais de data
  $now = time();
  $fromMonth = strtotime(date('Y-m-01 00:00:00', $now));
  $fromYear  = strtotime(date('Y-01-01 00:00:00', $now));
  $toToday   = strtotime(date('Y-m-d 23:59:59', $now));
$sumToday = 0.0;
$todayStr = date('Ymd'); // hoje no formato do campo EMISAO
  $items = [];

  if ($resp['success'] && is_array($resp['data'])) {
    $data = $resp['data'];

    // detecta lista
    if (array_is_list($data)) {
      $items = $data;
    } elseif (isset($data['items']) && is_array($data['items'])) {
      $items = $data['items'];
    } elseif (isset($data['value']) && is_array($data['value'])) {
      $items = $data['value'];
    } else {
      foreach ($data as $v) {
        if (is_array($v) && array_is_list($v)) { $items = $v; break; }
      }
    }

    foreach ($items as $row) {
      if (!is_array($row)) continue;

      $emissao = (string)($row['EMISAO'] ?? '');
      if (strlen($emissao) !== 8) continue;

      $y = (int)substr($emissao, 0, 4);
      $mth = (int)substr($emissao, 4, 2);
      $d = (int)substr($emissao, 6, 2);
      if (!checkdate($mth, $d, $y)) continue;

      $ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $mth, $d));
      if (!$ts) continue;

      // CAMPO SOMADO: VALOR (troque aqui se quiser VALOR_BRUTO)
      $valor = (float)($row['VALOR'] ?? 0);

      if ($ts >= $fromYear && $ts <= $toToday)  $sumYear  += $valor;
      if ($ts >= $fromMonth && $ts <= $toToday) $sumMonth += $valor;
      // NOVO KPI — HOJE (EMISAO == hoje)
if ($emissao === $todayStr) {
  $sumToday += $valor;
}
    }

    $totvsOk = true;
    $totvsInfo['success'] = true;
    $totvsInfo['itens'] = count($items);
  }

  $totvsPayload = [
  'success' => $totvsOk,
  'values' => [
    'realizado_ate_hoje' => round($sumMonth, 2),
    'realizado_ano_acum' => round($sumYear, 2),

    // 👇 NOVO CAMPO (HOJE)
    'realizado_hoje' => round($sumToday, 2),
  ],
    'updated_at' => date('d/m/Y, H:i'),
    'meta' => [
      'consulta' => '000070',
      'range_mes' => [date('Y-m-d', $fromMonth), date('Y-m-d', $toToday)],
      'range_ano' => [date('Y-m-d', $fromYear), date('Y-m-d', $toToday)],
      'cache_min' => $cacheMinutes,
      'forced' => $force,
    ],
    'totvs_info' => $totvsInfo,
  ];

  file_put_contents($cacheFile, json_encode($totvsPayload, JSON_UNESCAPED_UNICODE));
}

// ======================================================
// 4) APLICA os 2 números do TOTVS por cima do que vem do banco
// ======================================================
if (is_array($totvsPayload) && !empty($totvsPayload['success'])) {
  $m['realizado_ate_hoje'] = (float)($totvsPayload['values']['realizado_ate_hoje'] ?? 0);
  $m['realizado_ano_acum'] = (float)($totvsPayload['values']['realizado_ano_acum'] ?? 0);
  $m['realizado_hoje'] =
    (float)($totvsPayload['values']['realizado_hoje'] ?? 0);
  }

// ======================================================
// 5) LÓGICA EXECUTIVO/FATURAMENTO (igual você já tinha)
// ======================================================
$meta_ano = (float)($m['meta_ano'] ?? 0);
$realizado_ano = (float)($m['realizado_ano_acum'] ?? 0);
$falta_ano = max(0, $meta_ano - $realizado_ano);

$meta_mes = (float)($m['meta_mes'] ?? 0);
$realizado_mes = (float)($m['realizado_ate_hoje'] ?? 0);
$falta_mes = max(0, $meta_mes - $realizado_mes);
$atingimento_mes_pct = ($meta_mes > 0) ? ($realizado_mes / $meta_mes) : 0;


[$dias_passados, $dias_totais] = dias_uteis_mes_ate_hoje();

// sobrescreve os valores que vinham do metrics:
$m['dias_uteis_trabalhados'] = $dias_passados;
$m['dias_uteis_trabalhar'] = $dias_totais;
$dias_totais = (int)($m['dias_uteis_trabalhar'] ?? 1);
$dias_passados = (int)($m['dias_uteis_trabalhados'] ?? 0);

$deveria_ter_hoje = ($meta_mes / max(1, $dias_totais)) * $dias_passados;

$realizado_dia_util = ($dias_passados > 0) ? ($realizado_mes / $dias_passados) : 0;
$meta_dia_util = ($dias_totais > 0) ? ($meta_mes / $dias_totais) : 0;

$produtividade_pct = ($meta_dia_util > 0) ? ($realizado_dia_util / $meta_dia_util) : 0;

$dias_restantes = max(1, $dias_totais - $dias_passados);
$a_faturar_por_dia = $falta_mes / $dias_restantes;

$projecao_fechamento = $realizado_dia_util * $dias_totais;
$equivale_pct = ($meta_mes > 0) ? ($projecao_fechamento / $meta_mes) : 0;
$vai_bater = ($projecao_fechamento >= $meta_mes) ? "SIM" : "NÃO";

// updated_at: se TOTVS ok, usa o do TOTVS; senão mantém do banco
$updatedAtFinal =
  (!empty($totvsPayload['success']) && !empty($totvsPayload['updated_at']))
  ? (string)$totvsPayload['updated_at']
  : ($latestUpdatedAt ? date('d/m/Y, H:i', strtotime($latestUpdatedAt)) : date('d/m/Y, H:i'));

$data = [
  'updated_at' => $updatedAtFinal,
  'values' => [
    'meta_ano' => $meta_ano,
    'realizado_ano_acum' => $realizado_ano,
    'falta_meta_ano' => $falta_ano,

    'meta_mes' => $meta_mes,
    'realizado_ate_hoje' => $realizado_mes,
    'falta_meta_mes' => $falta_mes,
    'atingimento_mes_pct' => $atingimento_mes_pct,
    'deveria_ate_hoje' => $deveria_ter_hoje,

    'meta_dia_util' => $meta_dia_util,
    'realizado_dia_util' => $realizado_dia_util,
    'realizado_dia_util_pct' => $produtividade_pct,
    'a_faturar_dia_util' => $a_faturar_por_dia,

    'dias_uteis_trabalhar' => $dias_totais,
    'dias_uteis_trabalhados' => $dias_passados,

    'vai_bater_meta' => $vai_bater,
    'fechar_em' => $projecao_fechamento,
    'equivale_pct' => $equivale_pct,
    'hoje_total' => (float)($m['realizado_hoje'] ?? 0),
  ],

  // opcional: debug do TOTVS (se quiser remover depois, pode)
  'totvs_000070' => $totvsPayload,
];

echo json_encode($data, JSON_UNESCAPED_UNICODE);