<?php
declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// ===== CACHE (recomendado) =====
$cacheSeconds = 60; // ajuste: 30/60
$cacheFile = sys_get_temp_dir() . '/totvs_000070_realizado_cache.json';

if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $cacheSeconds)) {
    readfile($cacheFile);
    exit;
}

// ===== FUNÇÕES AUXILIARES =====
function yyyymmddToTs(string $yyyymmdd): ?int {
    if (strlen($yyyymmdd) !== 8) return null;
    $y = (int)substr($yyyymmdd, 0, 4);
    $m = (int)substr($yyyymmdd, 4, 2);
    $d = (int)substr($yyyymmdd, 6, 2);
    if (!checkdate($m, $d, $y)) return null;
    return strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d));
}

function startOfMonthTs(int $ts): int {
    return strtotime(date('Y-m-01 00:00:00', $ts));
}

function startOfYearTs(int $ts): int {
    return strtotime(date('Y-01-01 00:00:00', $ts));
}

// ===== CHAMA TOTVS 000070 =====
$resp = callTotvsApi('000070');

if (!$resp['success'] || !is_array($resp['data'])) {
    $out = [
        'success' => false,
        'error' => 'Falha ao consultar TOTVS 000070',
        'totvs' => $resp,
        'values' => [
            'realizado_ate_hoje' => 0,
            'realizado_ano_acum' => 0,
        ],
        'updated_at' => date('d/m/Y, H:i:s'),
    ];
    file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// ===== IDENTIFICAR LISTA DE ITENS =====
// Dependendo do retorno da TOTVS, pode vir como:
// - $resp['data'] (lista)
// - ou $resp['data']['items']
// - ou $resp['data']['value']
// Aqui deixo robusto:
$data = $resp['data'];
$items = [];

if (array_is_list($data)) {
    $items = $data;
} elseif (isset($data['items']) && is_array($data['items'])) {
    $items = $data['items'];
} elseif (isset($data['value']) && is_array($data['value'])) {
    $items = $data['value'];
} else {
    // fallback: tenta achar o primeiro array dentro
    foreach ($data as $v) {
        if (is_array($v) && array_is_list($v)) { $items = $v; break; }
    }
}

$now = time();
$fromMonth = startOfMonthTs($now);
$fromYear  = startOfYearTs($now);
$toToday   = strtotime(date('Y-m-d 23:59:59', $now));

$sumMonth = 0.0;
$sumYear  = 0.0;

foreach ($items as $row) {
    if (!is_array($row)) continue;

    $emissao = (string)($row['EMISAO'] ?? '');
    $ts = yyyymmddToTs($emissao);
    if (!$ts) continue;

    // Valor: escolha do campo que representa "realizado"
    $valor = (float)($row['VALOR'] ?? 0);

    // Ano (01/01 -> hoje)
    if ($ts >= $fromYear && $ts <= $toToday) {
        $sumYear += $valor;
    }

    // Mês (01 -> hoje)
    if ($ts >= $fromMonth && $ts <= $toToday) {
        $sumMonth += $valor;
    }
}

$out = [
    'success' => true,
    'values' => [
        'realizado_ate_hoje' => round($sumMonth, 2),
        'realizado_ano_acum' => round($sumYear, 2),
    ],
    'updated_at' => date('d/m/Y, H:i:s'),
    'meta' => [
        'consulta' => '000070',
        'itens' => count($items),
        'range_mes' => [date('Y-m-d', $fromMonth), date('Y-m-d', $toToday)],
        'range_ano' => [date('Y-m-d', $fromYear),  date('Y-m-d', $toToday)],
    ],
];

file_put_contents($cacheFile, json_encode($out, JSON_UNESCAPED_UNICODE));
echo json_encode($out, JSON_UNESCAPED_UNICODE);