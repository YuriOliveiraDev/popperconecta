<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/config-totvs.php';

require_login();
header('Content-Type: application/json; charset=utf-8');

// ym=2026-02
$ym = (string)($_GET['ym'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) $ym = date('Y-m');

$force = (isset($_GET['force']) && $_GET['force'] === '1');

$cacheMinutes = 10;
$cacheSeconds = $cacheMinutes * 60;
$cacheFile = sys_get_temp_dir() . '/totvs_exec_save_' . preg_replace('/[^a-z0-9_\-]/i', '_', $ym) . '.json';

$useCache = (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheSeconds));
if ($useCache) {
  $cached = json_decode((string)file_get_contents($cacheFile), true);
  if (is_array($cached)) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// ---------- helpers ----------
if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool {
    if ($array === []) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }
}

$extractItems = function($data): array {
  if (!is_array($data)) return [];
  if (array_is_list($data)) return $data;
  if (isset($data['items']) && is_array($data['items'])) return $data['items'];
  if (isset($data['value']) && is_array($data['value'])) return $data['value'];
  foreach ($data as $v) {
    if (is_array($v) && array_is_list($v)) return $v;
  }
  return [];
};

$parseYmdToTs = function(string $ymd): ?int {
  if (strlen($ymd) !== 8) return null;
  $y = (int)substr($ymd, 0, 4);
  $m = (int)substr($ymd, 4, 2);
  $d = (int)substr($ymd, 6, 2);
  if (!checkdate($m, $d, $y)) return null;
  $ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d));
  return $ts ?: null;
};

$pickFirstKey = function(array $row, array $candidates): ?string {
  foreach ($candidates as $k) {
    if (array_key_exists($k, $row) && trim((string)$row[$k]) !== '') return $k;
  }
  return null;
};

$normLabel = function(?string $s): string {
  $s = trim((string)$s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s ?: '';
};

// ranges
$y = (int)substr($ym, 0, 4);
$mth = (int)substr($ym, 5, 2);
$fromMonth = strtotime(sprintf('%04d-%02d-01 00:00:00', $y, $mth));
$toMonth   = strtotime(date('Y-m-t 23:59:59', $fromMonth));

$now = time();
$fromYear = strtotime(date('Y-01-01 00:00:00', $now));
$toToday  = strtotime(date('Y-m-d 23:59:59', $now));
$todayStr = date('Ymd');

// ====== TOTVS 000070 (FATURADO) ======
$fatToday = 0.0; $fatMonth = 0.0; $fatYear = 0.0;
$diario_mes = []; // dia => valor (apenas faturado)
$qtd_nf_hoje = 0;
$qtd_nf_mes  = 0;

// ✅ TOPS (faturado, mês selecionado)
$top_produtos = [];   // "Produto" => valor
$top_vendedores = []; // "Vendedor" => valor

// vamos detectar chaves reais da sua consulta 000070
$detected = [
  'produto_key' => null,
  'vendedor_key' => null,
];

// candidatos comuns (ajustável)
$produtoCandidates = [
  'PRODUTO', 'PROD_DESC', 'DESCRICAO', 'DESCRI', 'DESC', 'B1_DESC',
  'ITEM', 'ITEM_DESC', 'PROD', 'PROD_NOME', 'PROD_DESCR', 'D1_DESCRI',
  'C6_PRODUTO', 'C6_DESCRI',
];

$vendedorCandidates = [
  'VENDEDOR', 'VENDEDOR_NOME', 'REPRESENTANTE', 'REPRESENTANTE_NOME',
  'A3_NOME', 'A3_NREDUZ', 'NOME_VEND', 'VENDED', 'VEND', 'VEND_NOME',
  // caso seu “top vendedores” seja na verdade “cliente”:
  'CLIENTE', 'A1_NOME', 'A1_NREDUZ', 'RAZAO', 'NOMECLIENTE',
];

$resp70 = callTotvsApi('000070');
if (!empty($resp70['success']) && is_array($resp70['data'])) {
  $items70 = $extractItems($resp70['data']);

  foreach ($items70 as $row) {
    if (!is_array($row)) continue;

    $emissao = (string)($row['EMISAO'] ?? '');
    $ts = $parseYmdToTs($emissao);
    if ($ts === null) continue;

    $valor = (float)($row['VALOR'] ?? 0);

    // ano até hoje
    if ($ts >= $fromYear && $ts <= $toToday) $fatYear += $valor;

    // mês selecionado (jan–dez)
    if ($ts >= $fromMonth && $ts <= $toMonth) {
      $fatMonth += $valor;

      // diário do faturado (mês)
      $dia = substr($emissao, 6, 2);
      $diario_mes[$dia] = (float)(($diario_mes[$dia] ?? 0) + $valor);
      $qtd_nf_mes++;

      // detectar chaves (uma vez)
      if ($detected['produto_key'] === null) {
        $detected['produto_key'] = $pickFirstKey($row, $produtoCandidates);
      }
      if ($detected['vendedor_key'] === null) {
        $detected['vendedor_key'] = $pickFirstKey($row, $vendedorCandidates);
      }

      // top produtos
      if ($detected['produto_key']) {
        $p = $normLabel((string)($row[$detected['produto_key']] ?? ''));
        if ($p !== '') $top_produtos[$p] = (float)(($top_produtos[$p] ?? 0) + $valor);
      }

      // top vendedores (ou cliente, conforme chave detectada)
      if ($detected['vendedor_key']) {
        $v = $normLabel((string)($row[$detected['vendedor_key']] ?? ''));
        if ($v !== '') $top_vendedores[$v] = (float)(($top_vendedores[$v] ?? 0) + $valor);
      }
    }

    // hoje
    if ($emissao === $todayStr) {
      $fatToday += $valor;
      $qtd_nf_hoje++;
    }
  }
}

// ordena desc e limita
arsort($top_produtos);
arsort($top_vendedores);
$top_produtos = array_slice($top_produtos, 0, 100, true);
$top_vendedores = array_slice($top_vendedores, 0, 50, true);

// ====== TOTVS 000071 (AGENDADO) ======
$agdToday = 0.0; $agdMonth = 0.0; $agdYear = 0.0;

$resp71 = callTotvsApi('000071');
if (!empty($resp71['success']) && is_array($resp71['data'])) {
  $items71 = $extractItems($resp71['data']);
  foreach ($items71 as $row) {
    if (!is_array($row)) continue;

    $emissao = (string)($row['C5_EMISSAO'] ?? '');
    $ts = $parseYmdToTs($emissao);
    if ($ts === null) continue;

    $valor = (float)($row['VALOR_PEDIDO'] ?? 0);

    if ($ts >= $fromYear && $ts <= $toToday) $agdYear += $valor;
    if ($ts >= $fromMonth && $ts <= $toMonth) $agdMonth += $valor;
    if ($emissao === $todayStr) $agdToday += $valor;
  }
}

// clientes_mes: mantém 0 se não tem fonte
$clientes_mes = 0;

// payload final
$out = [
  'success' => true,
  'ym' => $ym,
  'updated_at' => date('Y-m-d H:i:s'),
  'values' => [
    'hoje_faturado' => round($fatToday, 2),
    'hoje_agendado' => round($agdToday, 2),
    'hoje_total'    => round($fatToday + $agdToday, 2),

    'mes_faturado'  => round($fatMonth, 2),
    'mes_agendado'  => round($agdMonth, 2),
    'mes_total'     => round($fatMonth + $agdMonth, 2),

    'ano_faturado'  => round($fatYear, 2),
    'ano_agendado'  => round($agdYear, 2),
    'ano_total'     => round($fatYear + $agdYear, 2),
  ],
  'qtd_nf_hoje' => $qtd_nf_hoje,
  'qtd_nf_mes'  => $qtd_nf_mes,
  'clientes_mes' => $clientes_mes,
  'diario_mes' => $diario_mes,

  // ✅ agora vem preenchido
  'top_produtos' => $top_produtos,
  'top_vendedores' => $top_vendedores,

  // debug leve (pra você ajustar se necessário)
  'debug' => [
    'cache_min' => $cacheMinutes,
    'forced' => $force,
    'detected_produto_key' => $detected['produto_key'],
    'detected_vendedor_key' => $detected['vendedor_key'],
    'top_produtos_count' => is_array($top_produtos) ? count($top_produtos) : 0,
    'top_vendedores_count' => is_array($top_vendedores) ? count($top_vendedores) : 0,
  ],
];

file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
echo json_encode($out, JSON_UNESCAPED_UNICODE);