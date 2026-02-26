<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
// Compatibilidade PHP < 8.1
if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool
  {
    if ($array === [])
      return true;
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
$force = (isset($_GET['force']) && $_GET['force'] === '1'); // atualização manual

/* ======================================================
   1) CARREGA MÉTRICAS DO BANCO
   ====================================================== */
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
    $latestUpdatedAt = (string) $r['updated_at'];
  }

  $key = (string) $r['metric_key'];

  if ($r['metric_value_text'] !== null && $r['metric_value_text'] !== '') {
    $m[$key] = $r['metric_value_text'];
  } else {
    $m[$key] = (float) ($r['metric_value_num'] ?? 0);
  }
}

/* ======================================================
   2) FINANCEIRO (mantém como está)
   ====================================================== */
if ($dashboard_slug === 'financeiro') {
  echo json_encode([
    'updated_at' => $latestUpdatedAt ? date('d/m/Y, H:i', strtotime($latestUpdatedAt)) : date('d/m/Y, H:i'),
    'values' => [
      'faturado_dia' => (float) ($m['faturado_dia'] ?? 0),
      'contas_pagar_dia' => (float) ($m['contas_pagar_dia'] ?? 0),
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ======================================================
   3) TOTVS 000070 + 000071: CACHE 10 MIN + FORCE
   ====================================================== */
$cacheMinutes = 10;
$cacheSeconds = $cacheMinutes * 60;

$cacheFile = sys_get_temp_dir() . '/totvs_exec_' . preg_replace('/[^a-z0-9_\-]/i', '_', $dashboard_slug) . '.json';

$totvsPayload = null;
$useCache = (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheSeconds));

if ($useCache) {
  $cached = file_get_contents($cacheFile);
  $totvsPayload = json_decode((string) $cached, true);
} else {

  // ---------- helpers ----------
  $extractItems = function ($data): array {
    if (!is_array($data))
      return [];
    if (array_is_list($data))
      return $data;

    if (isset($data['items']) && is_array($data['items']))
      return $data['items'];
    if (isset($data['value']) && is_array($data['value']))
      return $data['value'];

    foreach ($data as $v) {
      if (is_array($v) && array_is_list($v))
        return $v;
    }
    return [];
  };

  $parseYmdToTs = function (string $ymd): ?int {
    if (strlen($ymd) !== 8)
      return null;
    $y = (int) substr($ymd, 0, 4);
    $mth = (int) substr($ymd, 4, 2);
    $d = (int) substr($ymd, 6, 2);
    if (!checkdate($mth, $d, $y))
      return null;
    $ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $mth, $d));
    return $ts ?: null;
  };

  // janela datas
  $now = time();
  $fromMonth = strtotime(date('Y-m-01 00:00:00', $now));
  $fromYear = strtotime(date('Y-01-01 00:00:00', $now));
  $toToday = strtotime(date('Y-m-d 23:59:59', $now));
  $todayStr = date('Ymd');

  // somas separadas (FATURADO x AGENDADO)
  $fatToday = 0.0;
  $fatMonth = 0.0;
  $fatYear = 0.0;
  $agdToday = 0.0;
  $agdMonth = 0.0;
  $agdYear = 0.0;

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
      if (!is_array($row))
        continue;

      $emissao = (string) ($row['EMISAO'] ?? $row['C5_EMISSAO'] ?? '');
      $ts = $parseYmdToTs($emissao);
      if ($ts === null)
        continue;

      $valor = (float) ($row['VALOR'] ?? $row['VALOR_PEDIDO'] ?? $row['VALOR_TOTAL'] ?? 0);

      if ($ts >= $fromYear && $ts <= $toToday)
        $fatYear += $valor;
      if ($ts >= $fromMonth && $ts <= $toToday)
        $fatMonth += $valor;
      if ($emissao === $todayStr)
        $fatToday += $valor;
    }

    $info70['success'] = true;
  }

  // ---------- 000071 (AGENDADO) ----------
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
      if (!is_array($row))
        continue;

      // sua amostra: C5_EMISSAO + VALOR_PEDIDO
      $emissao = (string) ($row['C5_EMISSAO'] ?? $row['EMISAO'] ?? '');
      $ts = $parseYmdToTs($emissao);
      if ($ts === null)
        continue;

      // se quiser filtrar só status AG:
      // $st = (string)($row['C5_XSTATUS'] ?? '');
      // if ($st !== 'AG') continue;

      $valor = (float) ($row['VALOR_PEDIDO'] ?? $row['VALOR'] ?? $row['VALOR_TOTAL'] ?? 0);

      if ($ts >= $fromYear && $ts <= $toToday)
        $agdYear += $valor;
      if ($ts >= $fromMonth && $ts <= $toToday)
        $agdMonth += $valor;
      if ($emissao === $todayStr)
        $agdToday += $valor;
    }

    $info71['success'] = true;
  }

  // totals (FATURADO + AGENDADO)
  $sumToday = $fatToday + $agdToday;
  $sumMonth = $fatMonth + $agdMonth;
  $sumYear = $fatYear + $agdYear;

  // "success" geral: se pelo menos uma consulta deu certo
  $totvsOk = (!empty($info70['success']) || !empty($info71['success']));

  $totvsPayload = [
    'success' => $totvsOk,
    'values' => [
      // totais somados
      'realizado_hoje' => round($sumToday, 2),
      'realizado_ate_hoje' => round($sumMonth, 2),
      'realizado_ano_acum' => round($sumYear, 2),

      // splits HOJE
      'hoje_faturado' => round($fatToday, 2),
      'hoje_agendado' => round($agdToday, 2),

      // ✅ splits MÊS (ATÉ HOJE)
      'mes_faturado' => round($fatMonth, 2),
      'mes_agendado' => round($agdMonth, 2),

      // ✅ splits ANO (ATÉ HOJE)
      'ano_faturado' => round($fatYear, 2),
      'ano_agendado' => round($agdYear, 2),
    ],
    'updated_at' => date('d/m/Y, H:i'),
    'meta' => [
      'range_mes' => [date('Y-m-d', $fromMonth), date('Y-m-d', $toToday)],
      'range_ano' => [date('Y-m-d', $fromYear), date('Y-m-d', $toToday)],
      'cache_min' => $cacheMinutes,
      'forced' => $force,
    ],
    'totvs_info' => [
      '000070' => $info70,
      '000071' => $info71,
    ],
  ];

  file_put_contents($cacheFile, json_encode($totvsPayload, JSON_UNESCAPED_UNICODE));
}

/* ======================================================
   4) APLICA os números do TOTVS por cima do banco
   ====================================================== */
if (is_array($totvsPayload) && !empty($totvsPayload['success'])) {
  $m['realizado_ate_hoje'] = (float) ($totvsPayload['values']['realizado_ate_hoje'] ?? 0);
  $m['realizado_ano_acum'] = (float) ($totvsPayload['values']['realizado_ano_acum'] ?? 0);
  $m['realizado_hoje'] = (float) ($totvsPayload['values']['realizado_hoje'] ?? 0);

  // HOJE
  $m['hoje_faturado'] = (float) ($totvsPayload['values']['hoje_faturado'] ?? 0);
  $m['hoje_agendado'] = (float) ($totvsPayload['values']['hoje_agendado'] ?? 0);

  // ✅ MÊS (ATÉ HOJE)
  $m['mes_faturado'] = (float) ($totvsPayload['values']['mes_faturado'] ?? 0);
  $m['mes_agendado'] = (float) ($totvsPayload['values']['mes_agendado'] ?? 0);

  $m['ano_faturado'] = (float) ($totvsPayload['values']['ano_faturado'] ?? 0);
  $m['ano_agendado'] = (float) ($totvsPayload['values']['ano_agendado'] ?? 0);
}

/* ======================================================
   5) LÓGICA EXECUTIVO/FATURAMENTO
   ====================================================== */
$meta_ano = (float) ($m['meta_ano'] ?? 0);
$realizado_ano = (float) ($m['realizado_ano_acum'] ?? 0);
$falta_ano = max(0, $meta_ano - $realizado_ano);

$meta_mes = (float) ($m['meta_mes'] ?? 0);
$realizado_mes = (float) ($m['realizado_ate_hoje'] ?? 0);
$falta_mes = max(0, $meta_mes - $realizado_mes);
$atingimento_mes_pct = ($meta_mes > 0) ? ($realizado_mes / $meta_mes) : 0;

[$dias_passados, $dias_totais] = dias_uteis_mes_ate_hoje();

$m['dias_uteis_trabalhados'] = $dias_passados;
$m['dias_uteis_trabalhar'] = $dias_totais;

$dias_totais = (int) ($m['dias_uteis_trabalhar'] ?? 1);
$dias_passados = (int) ($m['dias_uteis_trabalhados'] ?? 0);

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
  ? (string) $totvsPayload['updated_at']
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

    // HOJE
    'hoje_total' => (float) ($m['realizado_hoje'] ?? 0),
    'hoje_faturado' => (float) ($m['hoje_faturado'] ?? 0),
    'hoje_agendado' => (float) ($m['hoje_agendado'] ?? 0),

    // ✅ MÊS (ATÉ HOJE) separado
    'mes_total' => (float) ($m['realizado_ate_hoje'] ?? 0),
    'mes_faturado' => (float) ($m['mes_faturado'] ?? 0),
    'mes_agendado' => (float) ($m['mes_agendado'] ?? 0),

    'ano_total' => (float) ($m['realizado_ano_acum'] ?? 0),
    'ano_faturado' => (float) ($m['ano_faturado'] ?? 0),
    'ano_agendado' => (float) ($m['ano_agendado'] ?? 0),
  ],

  // debug do TOTVS (remova depois se quiser)
  'totvs_exec' => $totvsPayload,
];

echo json_encode($data, JSON_UNESCAPED_UNICODE);