<?php

declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

// =========================
// CONFIGURAÇÕES API TOTVS
// =========================

// Base da API (sem travar consulta)
define('TOTVS_API_BASE', 'https://tuffloglogistica122016.protheus.cloudtotvs.com.br:4050/api/wscmrelaut/v1/Consulta/');

// Consulta padrão (caso você chame callTotvsApi() sem parâmetros)
define('TOTVS_DEFAULT_CONSULTA', '000072');

// Mapa de métricas -> consulta (adicione quantas quiser)
define('TOTVS_CONSULTAS', [
    'kpi_contasapagar' => '000072',
    'kpi_pedidos'     => '000070',
    // 'kpi_outra'     => '000040',
]);

// Credenciais (recomendado: variáveis de ambiente no servidor)
// Windows (PowerShell):
//   setx TOTVS_API_USER "YURI.YANG"
//   setx TOTVS_API_PASS "SUA_SENHA"
// Linux:
//   export TOTVS_API_USER="YURI.YANG"
//   export TOTVS_API_PASS="SUA_SENHA"
define('TOTVS_API_USER', getenv('TOTVS_API_USER') ?: 'YURI.YANG');
define('TOTVS_API_PASS', getenv('TOTVS_API_PASS') ?: 'Tufflog@2026');
define('TOTVS_DISABLE_SSL', true);

// =========================
// DICIONÁRIO: CENTRO DE CUSTO → SETOR
// =========================
$CCD_SETORES = [
    "1.1.1"  => "CONTABILIDADE",
    "1.1.2"  => "FISCAL",
    "1.1.3"  => "TI",
    "1.1.4"  => "FINANCEIRO",
    "1.1.5"  => "RH",
    "1.1.6"  => "ADMINISTRATIVO",
    "1.1.7"  => "FATURAMENTO",
    "1.1.8"  => "LOGÍSTICA",
    "1.1.9"  => "COMPRAS",
    "1.1.10" => "COMEX",

    "1.2.1"  => "VENDAS",
    "1.2.2"  => "MARKETING",
    "1.2.3"  => "E-COMMERCE",
    "1.2.4"  => "REPRESENTANTES",
    "1.2.5"  => "SAC",
    "1.2.6"  => "FACILITIES",
    "1.2.7"  => "RATEIO",
    "1.2.8"  => "TRADE MARKETING",
    "1.2.9"  => "COMUNICAÇÃO",
    "1.2.10" => "PRODUTO",
    "1.2.11" => "APOIO A VENDAS",
    "1.2.12" => "VENDAS DISTRIBUIDOR",

    "1.3.1"  => "DIRETORIA COMERCIAL",
    "1.3.2"  => "DIRETORIA",

    "1.9.1"  => "BEAUTY FAIR",
    "1.9.2"  => "NOVOS NEGÓCIOS",
    "1.9.3"  => "PROJETO N",
    "1.9.4"  => "CELEBRA SHOW 2025",
];

// =========================
// DICIONÁRIO: FORNECEDOR → NOME
// =========================
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/services/fornecedores.php';
$FORNECEDORES = carregarFornecedores();



// =========================
// FUNÇÕES HELPERS (datas, formato, segurança)
// =========================
function ddmmyyyy($yyyymmdd) {
    if (!is_string($yyyymmdd) || strlen($yyyymmdd) !== 8) return '';
    $y = substr($yyyymmdd, 0, 4);
    $m = substr($yyyymmdd, 4, 2);
    $d = substr($yyyymmdd, 6, 2);
    return $d . '/' . $m . '/' . $y;
}

function toDateTs($yyyymmdd) {
    if (!is_string($yyyymmdd) || strlen($yyyymmdd) !== 8) return null;
    $y = (int)substr($yyyymmdd, 0, 4);
    $m = (int)substr($yyyymmdd, 4, 2);
    $d = (int)substr($yyyymmdd, 6, 2);
    if (!checkdate($m, $d, $y)) return null;
    return strtotime(sprintf('%04d-%02d-%02d', $y, $m, $d));
}

function htmlDateToTs($yyyy_mm_dd) {
    if (!is_string($yyyy_mm_dd) || strlen($yyyy_mm_dd) !== 10) return null;
    $ts = strtotime($yyyy_mm_dd);
    return $ts ?: null;
}

function tsToHtmlDate($ts) {
    if (!$ts) return '';
    return date('Y-m-d', (int)$ts);
}

function moneyBR($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function safe($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function normalizarCCD($ccd) {
    $ccd = preg_replace('/\s+/', '', (string)$ccd);
    return trim((string)$ccd);
}

function nomeSetorCCD($ccd, $mapa = null) {
    global $CCD_SETORES;
    $mapa = $mapa ?? $CCD_SETORES;
    $ccd = normalizarCCD((string)$ccd);
    if ($ccd === '') return 'N/A';
    $nome = $mapa[$ccd] ?? null;
    return $nome ? ($ccd . ' - ' . $nome) : $ccd;
}

function nomeFornecedor($forn, $mapa = null) {
    global $FORNECEDORES;
    $mapa = $mapa ?? $FORNECEDORES;
    $forn = trim((string)$forn);
    if ($forn === '') return 'N/A';
    $nome = $mapa[$forn] ?? null;
    return $nome ? ($forn . ' - ' . $nome) : $forn;
}

function rangeMonthTs($year, $month) {
    $from = strtotime(sprintf('%04d-%02d-01', (int)$year, (int)$month));
    $to = strtotime(date('Y-m-t', $from));
    return [$from, $to];
}

// =========================
// TOTVS: montagem de URL por consulta/métrica
// =========================
function totvsConsultaUrl(string $consulta): string {
    $consulta = trim($consulta);
    return rtrim(TOTVS_API_BASE, '/') . '/' . $consulta;
}

function totvsMetricaUrl(string $metrica): string {
    $mapa = TOTVS_CONSULTAS;
    if (!isset($mapa[$metrica])) {
        throw new Exception("Métrica TOTVS não encontrada: {$metrica}");
    }
    return totvsConsultaUrl($mapa[$metrica]);
}

// =========================
// CHAMADA API TOTVS
// =========================
function callTotvsApi($urlOrConsultaOrMetrica = null, $user = null, $pass = null, $disableSSL = null) {

    // Define URL final com base no parâmetro
    if ($urlOrConsultaOrMetrica === null) {
        // default
        $url = totvsConsultaUrl(TOTVS_DEFAULT_CONSULTA);

    } elseif (is_string($urlOrConsultaOrMetrica) && preg_match('/^\d{6}$/', $urlOrConsultaOrMetrica)) {
        // ex: "000037"
        $url = totvsConsultaUrl($urlOrConsultaOrMetrica);

    } elseif (is_string($urlOrConsultaOrMetrica) && isset(TOTVS_CONSULTAS[$urlOrConsultaOrMetrica])) {
        // ex: "kpi_pedidos"
        $url = totvsMetricaUrl($urlOrConsultaOrMetrica);

    } else {
        // URL completa ou qualquer string
        $url = (string)$urlOrConsultaOrMetrica;
    }

    $user = $user ?? TOTVS_API_USER;
    $pass = $pass ?? TOTVS_API_PASS;
    $disableSSL = $disableSSL ?? TOTVS_DISABLE_SSL;

    if (!$user || !$pass) {
        return [
            'success' => false,
            'raw' => '',
            'data' => null,
            'json_error' => 'Credenciais TOTVS não configuradas. Defina TOTVS_API_USER e TOTVS_API_PASS no ambiente.',
            'info' => [
                'http_code' => 0,
                'content_type' => null,
                'total_time' => 0,
                'error' => 'missing_credentials',
                'errno' => 0,
                'url' => $url,
            ],
        ];
    }

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $user . ':' . $pass);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if ($disableSSL) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    }

    $response = curl_exec($ch);

    $info = [
        'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        'content_type' => curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
        'total_time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME),
        'error' => curl_error($ch),
        'errno' => curl_errno($ch),
        'url' => $url,
    ];

    curl_close($ch);

    if ($response === false || $response === null) $response = '';
    $response = preg_replace('/^\xEF\xBB\xBF/', '', $response);

    $enc = mb_detect_encoding($response, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $response = mb_convert_encoding($response, 'UTF-8', $enc);
    }
    $response = iconv('UTF-8', 'UTF-8//IGNORE', $response);
    $response = trim($response);

    $data = json_decode($response, true);
    $jsonOk = (json_last_error() === JSON_ERROR_NONE);

    return [
        'success' => ($info['http_code'] === 200 && $jsonOk),
        'raw' => $response,
        'data' => $jsonOk ? $data : null,
        'json_error' => $jsonOk ? null : json_last_error_msg(),
        'info' => $info,
    ];
}