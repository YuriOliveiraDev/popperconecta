<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

// Compatibilidade PHP < 8.1
if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool {
    if ($array === []) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }
}

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config-totvs.php';
require_once __DIR__ . '/../app/calendario.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

$dashboard_slug = $_GET['dash'] ?? 'executivo';
$force = (isset($_GET['force']) && $_GET['force'] === '1');

// ======================================================
// 1) CARREGA MÉTRICAS DO BANCO
// ======================================================
$stmt = db()->prepare('
  SELECT metric_key, metric_value_num, metric_value_text, updated_at
  FROM metrics
  WHERE dashboard_slug = ?
');
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
// 2) FINANCEIRO (mantém como está)
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
// 3) TOTVS 000070 + 000071: CACHE 10 MIN + FORCE
// ======================================================
$cacheMinutes = 10;
$cacheSeconds = $cacheMinutes * 60;
$cacheFile = sys_get_temp_dir() . '/totvs_exec_' . preg_replace('/[^a-z0-9_\-]/i', '_', $dashboard_slug) . '.json';

$totvsPayload = null;

// LOCALHOST: por padrão NÃO usa cache
$host = (string)($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
$isLocal = (stripos($host, 'localhost') !== false) || (stripos($host, '127.0.0.1') !== false);
$allowCacheLocal = (isset($_GET['cache']) && $_GET['cache'] === '1');

$useCache =
  (!$force)
  && (!$isLocal || $allowCacheLocal)
  && is_file($cacheFile)
  && (time() - filemtime($cacheFile) < $cacheSeconds);

if ($useCache) {
  $cached = file_get_contents($cacheFile);
  $totvsPayload = json_decode((string)$cached, true);

  $hasNewKeys =
    is_array($totvsPayload)
    && isset($totvsPayload['values'])
    && is_array($totvsPayload['values'])
    && array_key_exists('mes_im', $totvsPayload['values'])
    && array_key_exists('mes_ag', $totvsPayload['values'])
    && array_key_exists('hoje_im', $totvsPayload['values'])
    && array_key_exists('hoje_ag', $totvsPayload['values'])
    && array_key_exists('hoje_faturado', $totvsPayload['values'])
    && array_key_exists('mes_faturado', $totvsPayload['values']);

  if (!$hasNewKeys) {
    $totvsPayload = null;
    $useCache = false;
  }
}

if (!$useCache) {

  // ---------- helpers ----------
  $extractItems = function ($data): array {
    if (!is_array($data)) return [];
    if (array_is_list($data)) return $data;

    if (isset($data['items']) && is_array($data['items'])) return $data['items'];
    if (isset($data['value']) && is_array($data['value'])) return $data['value'];

    foreach ($data as $v) {
      if (is_array($v) && array_is_list($v)) return $v;
    }
    return [];
  };

  $parseYmdToTs = function (string $ymd): ?int {
    $ymd = trim($ymd);
    if (strlen($ymd) !== 8) return null;

    $y = (int)substr($ymd, 0, 4);
    $mth = (int)substr($ymd, 4, 2);
    $d = (int)substr($ymd, 6, 2);

    if (!checkdate($mth, $d, $y)) return null;

    $ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $mth, $d));
    return $ts ?: null;
  };

  $inRange = function (?int $ts, int $start, int $end): bool {
    if ($ts === null) return false;
    return ($ts >= $start && $ts <= $end);
  };

  // janela datas
  $now = time();
  $fromMonth  = strtotime(date('Y-m-01 00:00:00', $now));
  $fromYear   = strtotime(date('Y-01-01 00:00:00', $now));

  // faturado = até hoje
  $toToday    = strtotime(date('Y-m-d 23:59:59', $now));

  // carteira = mês/ano inteiros
  $toMonthEnd = strtotime(date('Y-m-t 23:59:59', $now));
  $toYearEnd  = strtotime(date('Y-12-31 23:59:59', $now));

  $todayStr = date('Ymd');

  // 000070 (FATURADO)
  $fatToday = 0.0;
  $fatMonth = 0.0;
  $fatYear  = 0.0;

  // 000071 (CARTEIRA)
  $imToday = 0.0; $imMonth = 0.0; $imYear = 0.0; // IM
  $agToday = 0.0; $agMonth = 0.0; $agYear = 0.0; // AG

  // ---------- 000070 (FATURADO) ----------
  $resp70 = callTotvsApi('000070');
  $info70 = [
    'success' => false,
    'http_code' => $resp70['info']['http_code'] ?? 0,
    'error' => $resp70['info']['error'] ?? null,
    'errno' => $resp70['info']['errno'] ?? null,
    'itens' => 0,
  ];

  if (!empty($resp70['success']) && is_array($resp70['data'])) {
    $items70 = $extractItems($resp70['data']);
    $info70['itens'] = count($items70);

    foreach ($items70 as $row) {
      if (!is_array($row)) continue;

      $emissao = trim((string)($row['EMISAO'] ?? $row['C5_EMISSAO'] ?? ''));
      $ts = ($emissao !== '') ? $parseYmdToTs($emissao) : null;
      if ($ts === null) continue;

      $valor = (float)($row['VALOR'] ?? $row['VALOR_PEDIDO'] ?? $row['VALOR_TOTAL'] ?? 0);

      if ($inRange($ts, $fromYear, $toToday))  $fatYear  += $valor;
      if ($inRange($ts, $fromMonth, $toToday)) $fatMonth += $valor;
      if ($emissao === $todayStr)              $fatToday += $valor;
    }

    $info70['success'] = true;
  }

  // ---------- 000071 (CARTEIRA: IM / AG) ----------
  $resp71 = callTotvsApi('000071');
  $info71 = [
    'success' => false,
    'http_code' => $resp71['info']['http_code'] ?? 0,
    'error' => $resp71['info']['error'] ?? null,
    'errno' => $resp71['info']['errno'] ?? null,
    'itens' => 0,
  ];

  if (!empty($resp71['success']) && is_array($resp71['data'])) {
    $items71 = $extractItems($resp71['data']);
    $info71['itens'] = count($items71);

    foreach ($items71 as $row) {
      if (!is_array($row)) continue;

      $st = strtoupper(trim((string)($row['C5_XSTATUS'] ?? '')));
      if ($st === '') continue;

      $isIM = ($st === 'IM');
      $isAG = ($st === 'AG');
      if (!$isIM && !$isAG) continue;

      $emissao = trim((string)($row['C5_EMISSAO'] ?? $row['EMISAO'] ?? ''));
      $fecent  = trim((string)($row['C5_FECENT'] ?? ''));

      $tsEmissao = ($emissao !== '') ? $parseYmdToTs($emissao) : null;
      $tsFecent  = ($fecent  !== '') ? $parseYmdToTs($fecent)  : null;

      if ($tsEmissao === null && $tsFecent === null) continue;

      $valor = (float)($row['VALOR_PEDIDO'] ?? $row['VALOR'] ?? $row['VALOR_TOTAL'] ?? 0);

      if ($isIM) {
        if ($tsFecent !== null) {
          if ($inRange($tsFecent, $fromMonth, $toMonthEnd)) $imMonth += $valor;
          if ($fecent === $todayStr) $imToday += $valor;
          if ($inRange($tsFecent, $fromYear, $toYearEnd)) $imYear += $valor;
        } else {
          if ($inRange($tsEmissao, $fromMonth, $toMonthEnd)) $imMonth += $valor;
          if ($emissao === $todayStr) $imToday += $valor;
          if ($inRange($tsEmissao, $fromYear, $toYearEnd)) $imYear += $valor;
        }
      }

      if ($isAG) {
        if ($tsFecent !== null) {
          if ($inRange($tsFecent, $fromMonth, $toMonthEnd)) $agMonth += $valor;
          if ($fecent === $todayStr) $agToday += $valor;
          if ($inRange($tsFecent, $fromYear, $toYearEnd)) $agYear += $valor;
        } else {
          if ($inRange($tsEmissao, $fromMonth, $toMonthEnd)) $agMonth += $valor;
          if ($emissao === $todayStr) $agToday += $valor;
          if ($inRange($tsEmissao, $fromYear, $toYearEnd)) $agYear += $valor;
        }
      }
    }

    $info71['success'] = true;
  }

  // ✅ TOTAL principal = FATURADO + IM
  $sumToday = $fatToday + $imToday;
  $sumMonth = $fatMonth + $imMonth;
  $sumYear  = $fatYear  + $imYear;

  $totvsOk = (!empty($info70['success']) || !empty($info71['success']));

  $totvsPayload = [
    'success' => $totvsOk,
    'values' => [
      'realizado_hoje'     => round($sumToday, 2),
      'realizado_ate_hoje' => round($sumMonth, 2),
      'realizado_ano_acum' => round($sumYear, 2),

      'hoje_faturado' => round($fatToday, 2),
      'mes_faturado'  => round($fatMonth, 2),
      'ano_faturado'  => round($fatYear, 2),

      'hoje_im' => round($imToday, 2),
      'mes_im'  => round($imMonth, 2),
      'ano_im'  => round($imYear, 2),

      'hoje_ag' => round($agToday, 2),
      'mes_ag'  => round($agMonth, 2),
      'ano_ag'  => round($agYear, 2),

      // compat
      'hoje_agendado' => round($imToday, 2),
      'mes_agendado'  => round($imMonth, 2),
      'ano_agendado'  => round($imYear, 2),
    ],
    'updated_at' => date('d/m/Y, H:i'),
    'meta' => [
      'cache_min' => $cacheMinutes,
      'forced' => $force,
      'is_local' => $isLocal,
      'use_cache' => $useCache,
      'range_faturado_mes' => [date('Y-m-d', $fromMonth), date('Y-m-d', $toToday)],
      'range_faturado_ano' => [date('Y-m-d', $fromYear),  date('Y-m-d', $toToday)],
      'range_carteira_mes' => [date('Y-m-d', $fromMonth), date('Y-m-d', $toMonthEnd)],
      'range_carteira_ano' => [date('Y-m-d', $fromYear),  date('Y-m-d', $toYearEnd)],
    ],
    'totvs_info' => [
      '000070' => $info70,
      '000071' => $info71,
    ],
  ];

  @file_put_contents($cacheFile, json_encode($totvsPayload, JSON_UNESCAPED_UNICODE));
}

// ======================================================
// 4) APLICA TOTVS POR CIMA DO BANCO
// ======================================================
if (is_array($totvsPayload) && isset($totvsPayload['values']) && is_array($totvsPayload['values'])) {
  $tv = $totvsPayload['values'];

  foreach ([
    'realizado_ate_hoje','realizado_ano_acum','realizado_hoje',
    'hoje_faturado','mes_faturado','ano_faturado',
    'hoje_im','mes_im','ano_im',
    'hoje_ag','mes_ag','ano_ag',
    'hoje_agendado','mes_agendado','ano_agendado'
  ] as $k) {
    if (array_key_exists($k, $tv)) $m[$k] = (float)$tv[$k];
  }
}

/* ======================================================
   4.1) AJUSTES MANUAIS (sempre aplica aqui no final)
   - evita cache "comer" ajuste recente
====================================================== */
$adjToday = 0.0;
$adjMonth = 0.0;
$adjYear  = 0.0;

try {
  // datas base
  $now = time();
  $todayYmd = date('Y-m-d', $now);
  $monthStart = date('Y-m-01', $now);
  $monthEnd   = date('Y-m-t', $now);
  $yearStart  = date('Y-01-01', $now);
  $yearEnd    = date('Y-12-31', $now);

  $stmtAdj = db()->prepare('
    SELECT ref_date, valor
    FROM dashboard_faturamento_ajustes
    WHERE dash_slug = ?
      AND is_active = 1
      AND ref_date BETWEEN ? AND ?
  ');
  $stmtAdj->execute([$dashboard_slug, $yearStart, $yearEnd]);

  while ($r = $stmtAdj->fetch(PDO::FETCH_ASSOC)) {
    $d = (string)($r['ref_date'] ?? '');
    $v = (float)($r['valor'] ?? 0);

    $adjYear += $v;
    if ($d >= $monthStart && $d <= $monthEnd) $adjMonth += $v;
    if ($d === $todayYmd) $adjToday += $v;
  }

  // aplica nos faturados (do mapa $m)
  $m['hoje_faturado'] = (float)($m['hoje_faturado'] ?? 0) + $adjToday;
  $m['mes_faturado']  = (float)($m['mes_faturado'] ?? 0)  + $adjMonth;
  $m['ano_faturado']  = (float)($m['ano_faturado'] ?? 0)  + $adjYear;

  // recalc totais principais (faturado + IM)
  $m['realizado_hoje']     = (float)($m['hoje_faturado'] ?? 0) + (float)($m['hoje_im'] ?? 0);
  $m['realizado_ate_hoje'] = (float)($m['mes_faturado'] ?? 0)  + (float)($m['mes_im'] ?? 0);
  $m['realizado_ano_acum'] = (float)($m['ano_faturado'] ?? 0)  + (float)($m['ano_im'] ?? 0);

} catch (Throwable $e) {
  // silencioso
}

// ======================================================
// 5) LÓGICA EXECUTIVO/FATURAMENTO
// ======================================================
$meta_ano = (float)($m['meta_ano'] ?? 0);
$realizado_ano = (float)($m['realizado_ano_acum'] ?? 0);
$falta_ano = max(0, $meta_ano - $realizado_ano);

$meta_mes = (float)($m['meta_mes'] ?? 0);
$realizado_mes = (float)($m['realizado_ate_hoje'] ?? 0);
$falta_mes = max(0, $meta_mes - $realizado_mes);
$atingimento_mes_pct = ($meta_mes > 0) ? ($realizado_mes / $meta_mes) : 0;

[$dias_passados, $dias_totais] = dias_uteis_mes_ate_hoje();

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

$updatedAtFinal =
  (is_array($totvsPayload) && !empty($totvsPayload['updated_at']))
  ? (string)$totvsPayload['updated_at']
  : ($latestUpdatedAt ? date('d/m/Y, H:i', strtotime($latestUpdatedAt)) : date('d/m/Y, H:i'));

// ✅ totais já calculados
$hoje_total = (float)($m['realizado_hoje'] ?? 0);
$mes_total  = (float)($m['realizado_ate_hoje'] ?? 0);

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

    // AJUSTES (exposto)
    'ajuste_hoje' => round($adjToday, 2),
    'ajuste_mes'  => round($adjMonth, 2),
    'ajuste_ano'  => round($adjYear, 2),

    // HOJE
    'hoje_total' => $hoje_total,
    'hoje_faturado' => (float)($m['hoje_faturado'] ?? 0),
    'hoje_im' => (float)($m['hoje_im'] ?? 0),
    'hoje_ag' => (float)($m['hoje_ag'] ?? 0),

    // MÊS
    'mes_total' => $mes_total,
    'mes_faturado' => (float)($m['mes_faturado'] ?? 0),
    'mes_im' => (float)($m['mes_im'] ?? 0),
    'mes_ag' => (float)($m['mes_ag'] ?? 0),

    // compat
    'mes_agendado' => (float)($m['mes_im'] ?? 0),
    'hoje_agendado' => (float)($m['hoje_im'] ?? 0),
  ],
  'totvs_exec' => $totvsPayload,
];

echo json_encode($data, JSON_UNESCAPED_UNICODE);