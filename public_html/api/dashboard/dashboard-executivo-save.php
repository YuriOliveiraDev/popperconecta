<?php
declare(strict_types=1);

// Compatibilidade PHP < 8.1
if (!function_exists('array_is_list')) {
  function array_is_list(array $array): bool {
    if ($array === []) return true;
    return array_keys($array) === range(0, count($array) - 1);
  }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_once APP_ROOT . '/app/config/config.php';
require_once APP_ROOT . '/app/config/config-totvs.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

/**
 * ym esperado: YYYY-MM (ex: 2026-02)
 * default: mês atual
 */
$ym = (string)($_GET['ym'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
  $ym = date('Y-m');
}

$ymNoDash = str_replace('-', '', $ym); // YYYYMM
$anoY = substr($ymNoDash, 0, 4);
$mesYm = $ymNoDash; // YYYYMM

$hojeYmd = date('Ymd');
$hojeIso = date('Y-m-d');

// limites do mês selecionado
$monthStartIso = $ym . '-01';
$monthEndIso   = date('Y-m-t', strtotime($monthStartIso));

// limites do ano do ym
$yearStartIso = $anoY . '-01-01';
$yearEndIso   = $anoY . '-12-31';

// ✅ TOTVS
$resp = callTotvsApi('kpi_pedidos'); // ou '000070' conforme seu config

if (!$resp['success'] || !is_array($resp['data'])) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Falha ao consultar TOTVS',
    'debug'   => $resp['info'] ?? null,
    'error'   => $resp['json_error'] ?? null,
    'raw'     => mb_substr((string)($resp['raw'] ?? ''), 0, 500),
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

$data = $resp['data'];

// tenta achar lista de itens
$items = null;
foreach (['items','Itens','itens','data','DATA','result','results','Resultado','retorno','value'] as $k) {
  if (isset($data[$k]) && is_array($data[$k])) { $items = $data[$k]; break; }
}
if ($items === null && array_is_list($data)) $items = $data;
if (!is_array($items)) $items = [];

// ✅ Se seus campos do 000070 forem diferentes, ajuste estes nomes:
$KEY_EMISSAO  = 'EMISAO';       // YYYYMMDD
$KEY_VALOR    = 'VALOR';        // float
$KEY_NF       = 'NF';
$KEY_CLIENTE  = 'COD_CLIENTE';
$KEY_LOJA     = 'LOJA_CLIENTE';
$KEY_PRODUTO  = 'PRODUTO';
$KEY_VENDEDOR = 'VENDEDOR';

$kpi = [
  'success' => true,
  'ym' => $ym,
  'updated_at' => date('Y-m-d H:i:s'),

  // KPIs
  'hoje' => 0.0,
  'mes'  => 0.0,
  'ano'  => 0.0,

  // ✅ ajustes (expostos p/ debug)
  'ajuste_hoje' => 0.0,
  'ajuste_mes'  => 0.0,
  'ajuste_ano'  => 0.0,

  'qtd_nf_hoje' => 0,
  'qtd_nf_mes'  => 0,
  'clientes_mes' => 0,

  // outputs
  'diario_mes' => [],
  'top_produtos' => [],
  'top_vendedores' => [],

  // debug
  'debug_count' => count($items),
];

$nfsHoje = [];
$nfsMes  = [];
$clientesMes = [];
$prod = [];
$vend = [];
$diario = [];

foreach ($items as $r) {
  if (!is_array($r)) continue;

  $emissao = (string)($r[$KEY_EMISSAO] ?? '');
  if ($emissao === '' || strlen($emissao) < 8) continue;

  $valor   = (float)($r[$KEY_VALOR] ?? 0);

  $nf      = (string)($r[$KEY_NF] ?? '');
  $cliente = (string)($r[$KEY_CLIENTE] ?? '') . '-' . (string)($r[$KEY_LOJA] ?? '');
  $produto = (string)($r[$KEY_PRODUTO] ?? 'N/A');
  $vendedor= (string)($r[$KEY_VENDEDOR] ?? 'N/A');

  // KPI HOJE (sempre hoje real)
  if ($emissao === $hojeYmd) {
    $kpi['hoje'] += $valor;
    if ($nf !== '') $nfsHoje[$nf] = true;
  }

  // KPI ANO (ano do ym selecionado)
  if (substr($emissao, 0, 4) === $anoY) {
    $kpi['ano'] += $valor;
  }

  // KPI MÊS (ym)
  if (substr($emissao, 0, 6) === $mesYm) {
    $kpi['mes'] += $valor;

    $dia = substr($emissao, 6, 2);
    if ($dia !== '') $diario[$dia] = ($diario[$dia] ?? 0) + $valor;

    $prod[$produto] = ($prod[$produto] ?? 0) + $valor;
    $vend[$vendedor]= ($vend[$vendedor] ?? 0) + $valor;

    if ($nf !== '') $nfsMes[$nf] = true;
    if ($cliente !== '-') $clientesMes[$cliente] = true;
  }
}

// ======================================================
// ✅ AJUSTE MANUAL (dashboard_faturamento_ajustes)
// - soma em hoje/mes/ano conforme data do ajuste
// - ✅ inclui também no diário do mês selecionado (diario_mes)
// - ❌ NÃO entra em tops
// ======================================================
try {
  // você pode trocar o slug se você estiver salvando ajustes em outro dash_slug
  $dashSlugAjuste = 'executivo';

  $stmtAdj = db()->prepare('
    SELECT ref_date, valor
    FROM dashboard_faturamento_ajustes
    WHERE dash_slug = ?
      AND is_active = 1
      AND ref_date BETWEEN ? AND ?
  ');
  $stmtAdj->execute([$dashSlugAjuste, $yearStartIso, $yearEndIso]);

  while ($row = $stmtAdj->fetch(PDO::FETCH_ASSOC)) {
    $d = (string)($row['ref_date'] ?? ''); // YYYY-MM-DD
    $v = (float)($row['valor'] ?? 0);

    if ($d === '' || strlen($d) < 10) continue;

    // ano do ym (sempre dentro do BETWEEN)
    $kpi['ano'] += $v;
    $kpi['ajuste_ano'] += $v;

    // mês do ym
    if ($d >= $monthStartIso && $d <= $monthEndIso) {
      $kpi['mes'] += $v;
      $kpi['ajuste_mes'] += $v;

      // ✅ inclui no diário (dia = "DD")
      $dd = substr($d, 8, 2);
      if ($dd !== '' && ctype_digit($dd)) {
        $diario[$dd] = ($diario[$dd] ?? 0) + $v;
      }
    }

    // hoje real
    if ($d === $hojeIso) {
      $kpi['hoje'] += $v;
      $kpi['ajuste_hoje'] += $v;
    }
  }
} catch (Throwable $e) {
  // silencioso pra não derrubar o endpoint
}

ksort($diario);
arsort($prod);
arsort($vend);

$kpi['qtd_nf_hoje'] = count($nfsHoje);
$kpi['qtd_nf_mes']  = count($nfsMes);
$kpi['clientes_mes']= count($clientesMes);

$kpi['diario_mes'] = $diario;

// ✅ TOP 10 + o JS mostra o resto no scroll (não corte no PHP!)
$kpi['top_produtos']   = $prod;
$kpi['top_vendedores'] = $vend;

echo json_encode($kpi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);