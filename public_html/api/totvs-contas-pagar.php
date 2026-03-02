<?php
require_once __DIR__ . '/../app/config-totvs.php';

header('Content-Type: application/json; charset=utf-8');

// Parâmetros de filtro
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');

$fromTs = htmlDateToTs($from) ?? strtotime(date('Y-m-01'));
$toTs = htmlDateToTs($to) ?? strtotime(date('Y-m-t'));

if ($fromTs > $toTs) {
    [$fromTs, $toTs] = [$toTs, $fromTs];
}

$titulosPorFornecedor = [];
$consulta = TOTVS_CONSULTAS['kpi_contasapagar'] ?? null;

if (!$consulta) {
  http_response_code(500);
  echo json_encode([
    'success' => false,
    'error' => 'Consulta não mapeada',
    'details' => 'Chave kpi_contasapagar não encontrada em TOTVS_CONSULTAS'
  ]);
  exit;
}

// Busca dados da API
$result = callTotvsApi($consulta);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Falha ao consultar API TOTVS',
        'details' => $result['info']
    ]);
    exit;
}

$items = $result['data']['items'] ?? [];

// Períodos para cálculos
$todayTs = strtotime('today');
$next3Ts = strtotime('+3 days', $todayTs);
$next7Ts = strtotime('+7 days', $todayTs);
$next15Ts = strtotime('+15 days', $todayTs);

// Filtrar por período
$itemsFiltered = array_values(array_filter($items, function($row) use ($fromTs, $toTs) {
    $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
    return $vencTs !== null && $vencTs >= $fromTs && $vencTs <= $toTs;
}));

// Processar dados
$totalValor = 0.0;
$totalQtd = 0;
$topCentro = [];
$topFornecedor = [];
$centroFornecedores = [];

foreach ($itemsFiltered as $row) {
    $forn = trim((string)($row['E2_FORNECE'] ?? ''));
    $valor = (float)($row['E2_VALOR'] ?? 0);
    $ccdRaw = $row['E2_CCD'] ?? '';
    
    $ccdNomeado = nomeSetorCCD($ccdRaw);
    $fornNomeado = nomeFornecedor($forn);
    if (!isset($titulosPorFornecedor[$fornNomeado])) {
    $titulosPorFornecedor[$fornNomeado] = [
        'fornecedor' => $fornNomeado,
        'total' => 0.0,
        'qtd' => 0,
        'titulos' => [],
    ];
}

$titulosPorFornecedor[$fornNomeado]['total'] += $valor;
$titulosPorFornecedor[$fornNomeado]['qtd']++;

$titulosPorFornecedor[$fornNomeado]['titulos'][] = [
    'filial'     => (string)($row['E2_FILIAL'] ?? ''),
    'emissao'    => ddmmyyyy($row['E2_EMISSAO'] ?? ''),
    'vencimento' => ddmmyyyy($row['E2_VENCREA'] ?? ''),
    'centro'     => $ccdNomeado,
    'valor'      => (float)($row['E2_VALOR'] ?? 0),

    // Campos extras (se existirem no retorno)
    'prefixo'    => (string)($row['E2_PREFIXO'] ?? ''),
    'numero'     => (string)($row['E2_NUM'] ?? ''),
    'parcela'    => (string)($row['E2_PARCELA'] ?? ''),
    'tipo'       => (string)($row['E2_TIPO'] ?? ''),
    'historico'  => (string)($row['E2_HIST'] ?? ''),
];

    $totalValor += $valor;
    $totalQtd++;

    // Top Centro de Custo
    if (!isset($topCentro[$ccdNomeado])) {
        $topCentro[$ccdNomeado] = ['key' => $ccdNomeado, 'total' => 0.0, 'qtd' => 0];
    }
    $topCentro[$ccdNomeado]['total'] += $valor;
    $topCentro[$ccdNomeado]['qtd']++;

    // Fornecedores por Centro
    if (!isset($centroFornecedores[$ccdNomeado])) {
        $centroFornecedores[$ccdNomeado] = [];
    }
    if (!isset($centroFornecedores[$ccdNomeado][$fornNomeado])) {
        $centroFornecedores[$ccdNomeado][$fornNomeado] = ['nome' => $fornNomeado, 'qtd' => 0, 'total' => 0.0];
    }
    $centroFornecedores[$ccdNomeado][$fornNomeado]['qtd']++;
    $centroFornecedores[$ccdNomeado][$fornNomeado]['total'] += $valor;

    // Top Fornecedor
    if (!isset($topFornecedor[$fornNomeado])) {
        $topFornecedor[$fornNomeado] = ['key' => $fornNomeado, 'total' => 0.0, 'qtd' => 0];
    }
    $topFornecedor[$fornNomeado]['total'] += $valor;
    $topFornecedor[$fornNomeado]['qtd']++;
}

// Ordenar rankings
$topCentroList = array_values($topCentro);
usort($topCentroList, fn($a, $b) => $b['total'] <=> $a['total']);

$topFornecedorList = array_values($topFornecedor);
usort($topFornecedorList, fn($a, $b) => $b['total'] <=> $a['total']);

// Calcular máximos para barras
$maxCentro = empty($topCentroList) ? 0 : max(array_column($topCentroList, 'total'));
$maxForn = empty($topFornecedorList) ? 0 : max(array_column($topFornecedorList, 'total'));

// Próximos vencimentos (sempre de hoje em diante)
$proximos3 = [];
$proximos7 = [];
$proximos15 = [];
$vencidos = [];

foreach ($items as $row) {
    $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
    if ($vencTs === null) continue;

    $row['fornecedor_nome'] = nomeFornecedor($row['E2_FORNECE'] ?? '');
    $row['centro_nome'] = nomeSetorCCD($row['E2_CCD'] ?? '');
    $row['vencimento_fmt'] = ddmmyyyy($row['E2_VENCREA'] ?? '');
    $row['emissao_fmt'] = ddmmyyyy($row['E2_EMISSAO'] ?? '');

    if ($vencTs >= $todayTs && $vencTs <= $next3Ts) $proximos3[] = $row;
    if ($vencTs >= $todayTs && $vencTs <= $next7Ts) $proximos7[] = $row;
    if ($vencTs >= $todayTs && $vencTs <= $next15Ts) $proximos15[] = $row;
    if ($vencTs < $todayTs) $vencidos[] = $row;
}

// Ordenar por data
usort($proximos3, fn($a, $b) => (toDateTs($a['E2_VENCREA']) ?? 0) <=> (toDateTs($b['E2_VENCREA']) ?? 0));
usort($proximos7, fn($a, $b) => (toDateTs($a['E2_VENCREA']) ?? 0) <=> (toDateTs($b['E2_VENCREA']) ?? 0));
usort($proximos15, fn($a, $b) => (toDateTs($a['E2_VENCREA']) ?? 0) <=> (toDateTs($b['E2_VENCREA']) ?? 0));

// Preparar dados do modal (fornecedores por centro)
$centroFornecedoresResponse = [];
foreach ($centroFornecedores as $centro => $fornecedores) {
    $lista = array_values($fornecedores);
    usort($lista, fn($a, $b) => $b['total'] <=> $a['total']);
    
    $totalCentro = $topCentro[$centro]['total'] ?? 0;
    foreach ($lista as &$f) {
        $f['percent'] = $totalCentro > 0 ? round(($f['total'] / $totalCentro) * 100, 1) : 0;
    }
    
    $centroFornecedoresResponse[$centro] = [
        'centro' => $centro,
        'total' => $totalCentro,
        'fornecedores' => $lista
    ];
}

// Resposta JSON
echo json_encode([
    'success' => true,
    'periodo' => ['from' => $from, 'to' => $to],
    'resumo' => [
        'total_qtd' => $totalQtd,
        'total_valor' => $totalValor,
        'proximos_3_dias' => array_sum(array_column($proximos3, 'E2_VALOR')),
        'proximos_7_dias' => array_sum(array_column($proximos7, 'E2_VALOR')),
        'proximos_15_dias' => array_sum(array_column($proximos15, 'E2_VALOR')),
    ],
    'rankings' => [
        'centro_custo' => $topCentroList,
        'fornecedor' => $topFornecedorList,
        'max_centro' => $maxCentro,
        'max_fornecedor' => $maxForn
    ],
    'centro_fornecedores' => $centroFornecedoresResponse,
    'proximos' => [
        '3_dias' => ['items' => $proximos3, 'total' => array_sum(array_column($proximos3, 'E2_VALOR'))],
        '7_dias' => ['items' => $proximos7, 'total' => array_sum(array_column($proximos7, 'E2_VALOR'))],
        '15_dias' => ['items' => $proximos15, 'total' => array_sum(array_column($proximos15, 'E2_VALOR'))]
    ]
]);