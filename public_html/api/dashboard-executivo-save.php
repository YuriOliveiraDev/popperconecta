<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/config-totvs.php';

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

// ✅ Se seu TOTVS agora é o relatório 000070, ajuste AQUI
$resp = callTotvsApi('kpi_pedidos'); // ou 'kpi_pedidos_000070' conforme seu config

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

$kpi = [
  'success' => true,
  'ym' => $ym, // 👈 importante pra debug
  'updated_at' => date('Y-m-d H:i:s'),

  // KPIs
  'hoje' => 0.0,
  'mes'  => 0.0,
  'ano'  => 0.0,

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

// ✅ Se seus campos do 000070 forem diferentes, ajuste estes nomes:
$KEY_EMISSAO  = 'EMISAO';       // YYYYMMDD
$KEY_VALOR    = 'VALOR';        // float
$KEY_NF       = 'NF';
$KEY_CLIENTE  = 'COD_CLIENTE';
$KEY_LOJA     = 'LOJA_CLIENTE';
$KEY_PRODUTO  = 'PRODUTO';
$KEY_VENDEDOR = 'VENDEDOR';

foreach ($items as $r) {
  if (!is_array($r)) continue;

  $emissao = (string)($r[$KEY_EMISSAO] ?? '');
  if ($emissao === '' || strlen($emissao) < 8) continue;

  $valor   = (float)($r[$KEY_VALOR] ?? 0);

  $nf      = (string)($r[$KEY_NF] ?? '');
  $cliente = (string)($r[$KEY_CLIENTE] ?? '') . '-' . (string)($r[$KEY_LOJA] ?? '');
  $produto = (string)($r[$KEY_PRODUTO] ?? 'N/A');
  $vendedor= (string)($r[$KEY_VENDEDOR] ?? 'N/A');

  // KPI HOJE (sempre hoje real, independente do filtro)
  if ($emissao === $hojeYmd) {
    $kpi['hoje'] += $valor;
    if ($nf !== '') $nfsHoje[$nf] = true;
  }

  // KPI ANO (ano do ym selecionado)
  if (substr($emissao, 0, 4) === $anoY) {
    $kpi['ano'] += $valor;
  }

  // ✅ FILTRO DO MÊS SELECIONADO (ym)
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

ksort($diario);
arsort($prod);
arsort($vend);

$kpi['qtd_nf_hoje'] = count($nfsHoje);
$kpi['qtd_nf_mes']  = count($nfsMes);
$kpi['clientes_mes']= count($clientesMes);

$kpi['diario_mes'] = $diario;

// ✅ TOP 10 + o JS mostra o resto no scroll (não corte no PHP!)
$kpi['top_produtos']   = $prod; // NÃO usar array_slice aqui
$kpi['top_vendedores'] = $vend; // NÃO usar array_slice aqui

echo json_encode($kpi, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);