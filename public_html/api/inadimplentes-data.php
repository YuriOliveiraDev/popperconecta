<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        if ($array === []) {
            return true;
        }
        return array_keys($array) === range(0, count($array) - 1);
    }
}

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config-totvs.php';

header('Content-Type: application/json; charset=utf-8');

/* =========================================================
   DICIONÁRIOS
========================================================= */
function nomeVendedor(string $codigo, string $fallbackNome = ''): string
{
    $mapa = [
        '000001' => '000001 - DEMETRIO CHAIM',
        '000002' => '000002 - POPPER',
        '000003' => '000003 - POPPER STORE',
        '000004' => '000004 - ELO7',
        '000005' => '000005 - MERCADO LIVRE',
        '000006' => '000006 - RTS REPRESENTACOES LTDA',
        '000007' => '000007 - KLF FESTAS IND E COM DE ART FESTAS LTDA',
        '000008' => '000008 - L & R REPRESENTACOES LTDA',
        '000009' => '000009 - MANGA VERDE COM DE ART FESTAS E REP LTDA',
        '000010' => '000010 - A. J REPRESENTACOES COMERCIAIS EIRELI',
        '000011' => '000011 - AFREITAS REPRESENTACOES LTDA',
        '000012' => '000012 - O V DA CUNHA REPRESENTACOES E SERVICOS',
        '000013' => '000013 - JR ASSESORIA HABITACIONAL E REP. LTDA',
        '000014' => '000014 - P. A PANILHA LIMA',
        '000015' => '000015 - ELIGRE REPRESENTACAO DE PRESENTES LTDA',
        '000016' => '000016 - DAPHENE COM. E REPRESENTACOES LTDA',
        '000017' => '000017 - LUIZ FLAVIO REP COMERCIAIS EIRELI',
        '000018' => '000018 - JMD REPRESENTACOES COMERCIAIS LTDA',
        '000019' => '000019 - IMPERIUM REPRESENTACOES LTDA',
        '000020' => '000020 - CARLOS ALBERTO P JUNIOR',
        '000021' => '000021 - ELLO REPRESENTACAO COMERCIAL LTDA',
        '000022' => '000022 - CALEGARI & BORGES CALEGARI LTDA',
        '000023' => '000023 - EDUARDO POLAK - NORTE PARANA',
        '000024' => '000024 - MARCOS AURELIO EVANGELISTA ROCHA EIRELI',
        '000025' => '000025 - KARINE REPRESENTACOES LTDA',
        '000026' => '000026 - FERREIRA & KURPIEL REP COMERCIAIS LTDA',
        '000027' => '000027 - JOEL DO NASCIMENTO OLIVIERA',
        '000028' => '000028 - BACK REPRESENTACOES COMERCIAIS LTDA',
        '000029' => '000029 - REPMARK REPRESENTACOES LTDA',
        '000030' => '000030 - AURIVANDA LIMA ALVES ME',
        '000031' => '000031 - JAYME LUIZ PIRES D\' AMORIM',
        '000032' => '000032 - MANOEL DE SOUZA GOMES',
        '000033' => '000033 - ALMEIDA REP DE PROD ALIMENTICIOS LTDA ME',
        '000034' => '000034 - M GALDINO COMERCIO E REP EIRELI',
        '000035' => '000035 - CARDIN ARTIGOS DE FESTAS',
        '000036' => '000036 - ROBSON ALAN SILVA',
        '000037' => '000037 - P. ZINKE REPRESENTACOES',
        '000038' => '000038 - JEFFERSON ADONIS DE SOUZA OLIVEIRA',
        '000081' => '000081 - JOAO CARLOS NOGUEIRA DE MELO',
        '000082' => '000082 - ITALO CARDOSO DE ALBUQUERQUE',
        '000083' => '000083 - GIULIANA PAULINO',
        '000084' => '000084 - EDUARDA FERREIRA',
        '000085' => '000085 - M & S REPRESENTACOES COMERCIAIS LTDA - E',
        '000086' => '000086 - REPRESENTHA REPRESENTACOES COMERCIAIS LT',
        '000087' => '000087 - FLOWERS BRASIL COSMETICOS EIRELI - ME',
        '000088' => '000088 - MARKETING POPPER',
        '000089' => '000089 - LUAR COMERCIAL LTDA',
        '000090' => '000090 - TIAGO OLIVEIRA REPRESENTACOES LTDA',
        '000091' => '000091 - MAILA MORAES',
        '000092' => '000092 - DCA REPRESENTACOES DE ARTIGOS PARA FESTA',
        '000093' => '000093 - KARINE OLIVEIRA',
        '000094' => '000094 - MAIKO DESPLANCHES',
        '000095' => '000095 - PLASTBRINKS REPRESENTACOES',
        '000096' => '000096 - AZO REPRESENTACOES LTDA',
        '000097' => '000097 - EDUARDO JOSE POLAK',
        '000098' => '000098 - MOISES ALVES FERNANDES',
        '000099' => '000099 - J & A REPRESENTACOES LTDA',
        '000100' => '000100 - CLEIDE T DE ALBUQUERQUE FERNANDES',
        '000101' => '000101 - EDWARDO CORREIRA DE LIMA FILHO',
        '000102' => '000102 - VENTO FORTE COMERCIO REPRESENTACAO',
        '000103' => '000103 - MARIA GABRIELLE OLIVEIRA SANTOS',
        '000104' => '000104 - V K P RIBEIRO REPRESENTACOES',
        '000105' => '000105 - EFATA REPRESENTACOES',
        '000107' => '000107 - MARILENE REPRESENTACOES',
        '000108' => '000108 - FABIO RODRIGO ZANUTTO',
        '000109' => '000109 - J. F. ZANUTTO & CIA',
        '000110' => '000110 - ALINE DE CARVALHO PIENTA',
        '000111' => '000111 - NATHAN MATTOS DE LIMA',
        '000112' => '000112 - HELLEN CRISTINA BECCARI SERVELO',
        '000113' => '000113 - PAULO SERGIO DE SOUZA',
        '000114' => '000114 - NUTRIBENNI REPRESENTACOES',
        '000115' => '000115 - LUIZA CAROLINA SILVEIRA BECHTLOFF',
        '000116' => '000116 - BELATRIZ REPRESENTACOES',
        '000117' => '000117 - JADYR JUNIOR REPRESENTACOES LTDA',
        '000118' => '000118 - GRAICY OLIVEIRA LAGO',
        '000119' => '000119 - DULVANO BARCELOS',
        '000120' => '000120 - SG REPRESENTACAO COMERCIAL LTDA',
        '000121' => '000121 - METTA REPRESENTACOES',
        '000122' => '000122 - SG REPRESENTACAO COMERCIAL LTDA',
        '000123' => '000123 - VISCARDI & VIGHI',
        '000124' => '000124 - WEL REPRESENTACAO',
    ];

    $codigo = trim($codigo);
    $fallbackNome = trim($fallbackNome);

    if ($codigo === '') {
        return $fallbackNome;
    }

    if (isset($mapa[$codigo])) {
        return $mapa[$codigo];
    }

    if ($fallbackNome !== '') {
        return $codigo . ' - ' . $fallbackNome;
    }

    return $codigo;
}

function nomeSupervisor(string $codigo): string
{
    $mapa = [
        '000119' => '000119 - DULVANO BARCELOS',
        '000111' => '000111 - NATHAN MATTOS DE LIMA',
        '000115' => '000115 - LUIZA CAROLINA SILVEIRA BECHTLOFF',
        '000001' => '000001 - DEMETRIO CHAIM',
    ];

    $codigo = trim($codigo);
    return $mapa[$codigo] ?? $codigo;
}

try {
    require_login();

    /* =========================================================
       HELPERS
    ========================================================= */
    function extractItems($data): array
    {
        if (!is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        if (isset($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (isset($data['value']) && is_array($data['value'])) {
            return $data['value'];
        }

        foreach ($data as $v) {
            if (is_array($v) && array_is_list($v)) {
                return $v;
            }
        }

        return [];
    }

    function parseYmd(?string $ymd): ?int
    {
        $ymd = trim((string) $ymd);
        if (strlen($ymd) !== 8) {
            return null;
        }

        $y = (int) substr($ymd, 0, 4);
        $m = (int) substr($ymd, 4, 2);
        $d = (int) substr($ymd, 6, 2);

        if (!checkdate($m, $d, $y)) {
            return null;
        }

        $ts = strtotime(sprintf('%04d-%02d-%02d 00:00:00', $y, $m, $d));
        return $ts ?: null;
    }

    function fmtDate(?string $ymd): string
    {
        $ymd = trim((string) $ymd);
        if (strlen($ymd) !== 8) {
            return '';
        }

        return substr($ymd, 6, 2) . '/' . substr($ymd, 4, 2) . '/' . substr($ymd, 0, 4);
    }

    function clienteKey(string $codigo, string $loja, string $vendedor = ''): string
    {
        return trim($codigo) . '|' . trim($loja);
    }

    function clienteKeyComVendedor(string $codigo, string $loja, string $vendedor = ''): string
    {
        $vend = trim($vendedor) !== '' ? trim($vendedor) : 'SEM_VENDEDOR';
        return trim($codigo) . '|' . trim($loja) . '|' . $vend;
    }

    function onlyDigits(string $v): string
    {
        return preg_replace('/\D+/', '', $v);
    }

    function faixaAtraso(int $dias): string
    {
        if ($dias <= 0) {
            return '0';
        }
        if ($dias <= 30) {
            return '1-30';
        }
        if ($dias <= 60) {
            return '31-60';
        }
        if ($dias <= 90) {
            return '61-90';
        }
        if ($dias <= 180) {
            return '91-180';
        }
        return '180+';
    }

    function inRange(?int $ts, ?int $from, ?int $to): bool
    {
        if ($ts === null) {
            return false;
        }
        if ($from !== null && $ts < $from) {
            return false;
        }
        if ($to !== null && $ts > $to) {
            return false;
        }
        return true;
    }

    function pickFirst(array $row, array $keys, $default = '')
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                return $row[$k];
            }
        }
        return $default;
    }

    function toFloatBr($value): float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        $v = trim((string) $value);
        if ($v === '') {
            return 0.0;
        }

        $v = str_replace(['R$', ' '], '', $v);

        if (strpos($v, ',') !== false && strpos($v, '.') !== false) {
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } elseif (strpos($v, ',') !== false) {
            $v = str_replace(',', '.', $v);
        }

        return (float) $v;
    }

    function criarClienteBase(
        string $codCliente,
        string $loja,
        string $nome = 'CLIENTE NÃO IDENTIFICADO',
        string $cnpj = '',
        string $vend1 = '',
        string $vendedor = '',
        string $super = ''
    ): array {
        return [
            'cliente' => $codCliente,
            'loja' => $loja,
            'cliente_key' => clienteKey($codCliente, $loja),
            'nome' => $nome,
            'cnpj' => $cnpj,
            'vendedor_codigo' => $vend1,
            'vendedor_nome' => nomeVendedor($vend1, $vendedor),
            'supervisor_codigo' => $super,
            'supervisor_nome' => nomeSupervisor($super),

            'inad_total' => 0.0,
            'inad_qtd_titulos' => 0,
            'inad_total_periodo_pct' => 0.0,
            'inad_qtd_titulos_periodo_pct' => 0,
            'maior_atraso_dias' => 0,
            'media_atraso_dias' => 0,
            'indice_inadimplencia_pct' => 0.0,

            'faturado_periodo' => 0.0,
            'faturado_total' => 0.0,
            'qtd_pedidos' => 0,

            'titulos' => [],
        ];
    }

    function monthLabelPt(string $ym): string
    {
        $meses = [
            '01' => 'Jan',
            '02' => 'Fev',
            '03' => 'Mar',
            '04' => 'Abr',
            '05' => 'Mai',
            '06' => 'Jun',
            '07' => 'Jul',
            '08' => 'Ago',
            '09' => 'Set',
            '10' => 'Out',
            '11' => 'Nov',
            '12' => 'Dez',
        ];

        $ano = substr($ym, 0, 4);
        $mes = substr($ym, 5, 2);

        return ($meses[$mes] ?? $mes) . '/' . $ano;
    }

    function getPresetRange(string $preset): array
    {
        $today = strtotime(date('Y-m-d 00:00:00'));
        $toTs = strtotime(date('Y-m-d 23:59:59', $today));

        switch ($preset) {
            case '30d':
                $fromTs = strtotime('-29 days', $today);
                break;

            case '90d':
                $fromTs = strtotime('-89 days', $today);
                break;

            case '12m':
                $fromTs = strtotime(date('Y-m-01 00:00:00', strtotime('-11 months', $today)));
                break;

            case '6m':
            default:
                $fromTs = strtotime(date('Y-m-01 00:00:00', strtotime('-5 months', $today)));
                break;
        }

        return [$fromTs, $toTs];
    }

    function detectGroupBy(?int $fromTs, ?int $toTs, string $groupBy): string
    {
        if ($groupBy !== 'auto') {
            return in_array($groupBy, ['day', 'week', 'month'], true) ? $groupBy : 'month';
        }

        if ($fromTs === null || $toTs === null) {
            return 'month';
        }

        $days = (int) floor(($toTs - $fromTs) / 86400) + 1;

        if ($days <= 45) {
            return 'day';
        }
        if ($days <= 120) {
            return 'week';
        }
        return 'month';
    }

    function startOfWeekTs(int $ts): int
    {
        $day = (int) date('N', $ts);
        return strtotime(date('Y-m-d 00:00:00', strtotime('-' . ($day - 1) . ' days', $ts)));
    }

    function buildPeriodoKey(int $ts, string $groupBy): string
    {
        if ($groupBy === 'day') {
            return date('Y-m-d', $ts);
        }

        if ($groupBy === 'week') {
            return date('Y-m-d', startOfWeekTs($ts));
        }

        return date('Y-m', $ts);
    }

    function buildPeriodoLabel(string $key, string $groupBy): string
    {
        if ($groupBy === 'day') {
            return date('d/m', strtotime($key));
        }

        if ($groupBy === 'week') {
            $ini = strtotime($key . ' 00:00:00');
            $fim = strtotime('+6 days', $ini);
            return date('d/m', $ini) . ' a ' . date('d/m', $fim);
        }

        return monthLabelPt($key);
    }

    function initHistoricoMap(?int $fromTs, ?int $toTs, string $groupBy): array
    {
        if ($fromTs === null || $toTs === null) {
            return [];
        }

        $map = [];

        if ($groupBy === 'day') {
            $cursor = strtotime(date('Y-m-d 00:00:00', $fromTs));
            $limit = strtotime(date('Y-m-d 00:00:00', $toTs));

            while ($cursor <= $limit) {
                $key = date('Y-m-d', $cursor);
                $map[$key] = [
                    'periodo' => $key,
                    'label' => buildPeriodoLabel($key, $groupBy),
                    'inad_total' => 0.0,
                    'titulos' => 0,
                    'clientes' => [],
                ];
                $cursor = strtotime('+1 day', $cursor);
            }

            return $map;
        }

        if ($groupBy === 'week') {
            $cursor = startOfWeekTs($fromTs);
            $limit = startOfWeekTs($toTs);

            while ($cursor <= $limit) {
                $key = date('Y-m-d', $cursor);
                $map[$key] = [
                    'periodo' => $key,
                    'label' => buildPeriodoLabel($key, $groupBy),
                    'inad_total' => 0.0,
                    'titulos' => 0,
                    'clientes' => [],
                ];
                $cursor = strtotime('+7 days', $cursor);
            }

            return $map;
        }

        $cursor = strtotime(date('Y-m-01 00:00:00', $fromTs));
        $limit = strtotime(date('Y-m-01 00:00:00', $toTs));

        while ($cursor <= $limit) {
            $key = date('Y-m', $cursor);
            $map[$key] = [
                'periodo' => $key,
                'label' => buildPeriodoLabel($key, $groupBy),
                'inad_total' => 0.0,
                'titulos' => 0,
                'clientes' => [],
            ];
            $cursor = strtotime('+1 month', $cursor);
        }

        return $map;
    }

    /* =========================================================
       PARAMS
    ========================================================= */
    $force = (isset($_GET['force']) && $_GET['force'] === '1');

    $diasMinimosInadimplencia = max(0, min(30, (int) ($_GET['dias_min_atraso'] ?? 3)));
    if ($diasMinimosInadimplencia < 0) {
        $diasMinimosInadimplencia = 0;
    }

    $todayRefTs = strtotime(date('Y-m-d 00:00:00', strtotime("-{$diasMinimosInadimplencia} days")));
    $todayTs = strtotime(date('Y-m-d 00:00:00'));

    $dateFrom = trim((string) ($_GET['date_from'] ?? ''));
    $dateTo = trim((string) ($_GET['date_to'] ?? ''));
    $preset = trim((string) ($_GET['preset'] ?? '6m'));
    $groupByReq = trim((string) ($_GET['group_by'] ?? 'auto'));

    if (!in_array($preset, ['30d', '90d', '6m', '12m'], true)) {
        $preset = '6m';
    }

    $usarFiltroData = ($dateFrom !== '' || $dateTo !== '');

    $dateFromTs = null;
    $dateToTs = null;

    if ($usarFiltroData) {
        if ($dateFrom !== '') {
            $dateFromTs = htmlDateToTs($dateFrom);
        }

        if ($dateTo !== '') {
            $dateToBase = htmlDateToTs($dateTo);
            if ($dateToBase !== null) {
                $dateToTs = strtotime(date('Y-m-d 23:59:59', $dateToBase));
            }
        }
    }

    if ($dateFromTs !== null && $dateToTs !== null && $dateFromTs > $dateToTs) {
        [$dateFromTs, $dateToTs] = [$dateToTs, $dateFromTs];
        [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
    }

    $groupBy = detectGroupBy($dateFromTs, $dateToTs, $groupByReq);

    $search = trim((string) ($_GET['search'] ?? ''));
    $filterVend = trim((string) ($_GET['vendedor'] ?? ''));
    $filterSuper = trim((string) ($_GET['supervisor'] ?? ''));
    $filterFaixa = trim((string) ($_GET['faixa_atraso'] ?? ''));
    $filterMinVal = (float) ($_GET['valor_min'] ?? 0);

    /* =========================================================
       CACHE
    ========================================================= */
    $cacheMinutes = 2;
    $cacheSeconds = $cacheMinutes * 60;

    $cacheKey = md5(json_encode([
        'usar_filtro_data' => $usarFiltroData,
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'preset' => $preset,
        'group_by_req' => $groupByReq,
        'group_by_final' => $groupBy,
        'dias_min_atraso' => $diasMinimosInadimplencia,
        'search' => $search,
        'vendedor' => $filterVend,
        'supervisor' => $filterSuper,
        'faixa_atraso' => $filterFaixa,
        'valor_min' => $filterMinVal,
    ], JSON_UNESCAPED_UNICODE));

    $cacheFile = sys_get_temp_dir() . '/totvs_inadimplentes_' . $cacheKey . '.json';

    $host = (string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '');
    $isLocal = (stripos($host, 'localhost') !== false) || (stripos($host, '127.0.0.1') !== false);
    $allowCacheLocal = (isset($_GET['cache']) && $_GET['cache'] === '1');

    $useCache =
        (!$force)
        && (!$isLocal || $allowCacheLocal)
        && is_file($cacheFile)
        && (time() - filemtime($cacheFile) < $cacheSeconds);

    if ($useCache) {
        $cached = file_get_contents($cacheFile);
        $payload = json_decode((string) $cached, true);
        if (is_array($payload)) {
            echo json_encode($payload, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /* =========================================================
       CONSULTAS TOTVS
    ========================================================= */
    $resp76 = callTotvsApi('000076');
    $info76 = [
        'success' => false,
        'http_code' => $resp76['info']['http_code'] ?? 0,
        'error' => $resp76['info']['error'] ?? null,
        'errno' => $resp76['info']['errno'] ?? null,
        'itens' => 0,
    ];

    $resp70 = callTotvsApi('000070');
    $info70 = [
        'success' => false,
        'http_code' => $resp70['info']['http_code'] ?? 0,
        'error' => $resp70['info']['error'] ?? null,
        'errno' => $resp70['info']['errno'] ?? null,
        'itens' => 0,
    ];

    $inadRows = [];
    $fatRows = [];

    if (!empty($resp76['success']) && is_array($resp76['data'])) {
        $inadRows = extractItems($resp76['data']);
        $info76['success'] = true;
        $info76['itens'] = count($inadRows);
    }

    if (!empty($resp70['success']) && is_array($resp70['data'])) {
        $fatRows = extractItems($resp70['data']);
        $info70['success'] = true;
        $info70['itens'] = count($fatRows);
    }

    /* =========================================================
       AGREGAÇÃO
    ========================================================= */
    $clientes = [];
    $fatPorCliente = [];
    $historicoMap = initHistoricoMap($dateFromTs, $dateToTs, $groupBy);

    $aging = [
        '1-30' => 0.0,
        '31-60' => 0.0,
        '61-90' => 0.0,
        '91-180' => 0.0,
        '180+' => 0.0,
    ];

    // -------- INADIMPLÊNCIA 000076 --------
    foreach ($inadRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $codCliente = trim((string) ($row['E1_CLIENTE'] ?? ''));
        $loja = trim((string) ($row['E1_LOJA'] ?? ''));
        $nome = trim((string) ($row['A1_NOME'] ?? 'CLIENTE NÃO IDENTIFICADO'));
        $cnpj = onlyDigits((string) ($row['A1_CGC'] ?? ''));
        $vend1 = trim((string) ($row['E1_VEND1'] ?? ''));
        $vendedor = trim((string) ($row['A3_NOME'] ?? ''));
        $super = trim((string) ($row['A3_SUPER'] ?? ''));
        $saldo = toFloatBr($row['E1_SALDO'] ?? 0);
        $valor = toFloatBr($row['E1_VALOR'] ?? 0);
        $vencto = onlyDigits((string) ($row['E1_VENCTO'] ?? ''));
        $emissao = onlyDigits((string) ($row['E1_EMISSAO'] ?? ''));
        $baixa = onlyDigits((string) ($row['E1_BAIXA'] ?? ''));
        $forma = trim((string) ($row['E1_XFRMPAG'] ?? ''));
        $titulo = trim((string) ($row['E1_PREFIXO'] ?? '')) . '-' . trim((string) ($row['E1_NUM'] ?? '')) . '-' . trim((string) ($row['E1_PARCELA'] ?? ''));

        if ($codCliente === '' || $loja === '') {
            continue;
        }

        if ($saldo <= 0) {
            continue;
        }

        $tsVencto = parseYmd($vencto);
        if ($tsVencto === null) {
            continue;
        }

        if ($tsVencto > $todayRefTs) {
            continue;
        }

        $entraNoPeriodoComparativo = !$usarFiltroData || inRange($tsVencto, $dateFromTs, $dateToTs);

        $diasAtraso = (int) floor(($todayTs - $tsVencto) / 86400);
        if ($diasAtraso < 0) {
            $diasAtraso = 0;
        }

        $key = clienteKey($codCliente, $loja);

        if (!isset($clientes[$key])) {
            $clientes[$key] = criarClienteBase(
                $codCliente,
                $loja,
                $nome,
                $cnpj,
                $vend1,
                $vendedor,
                $super
            );
        } else {
            if (($clientes[$key]['nome'] ?? '') === '' || $clientes[$key]['nome'] === 'CLIENTE NÃO IDENTIFICADO') {
                $clientes[$key]['nome'] = $nome;
            }
            if (($clientes[$key]['cnpj'] ?? '') === '') {
                $clientes[$key]['cnpj'] = $cnpj;
            }
            if (($clientes[$key]['vendedor_codigo'] ?? '') === '' && $vend1 !== '') {
                $clientes[$key]['vendedor_codigo'] = $vend1;
            }
            if (($clientes[$key]['vendedor_nome'] ?? '') === '' && ($vend1 !== '' || $vendedor !== '')) {
                $clientes[$key]['vendedor_nome'] = nomeVendedor($vend1, $vendedor);
            }
            if (($clientes[$key]['supervisor_codigo'] ?? '') === '' && $super !== '') {
                $clientes[$key]['supervisor_codigo'] = $super;
                $clientes[$key]['supervisor_nome'] = nomeSupervisor($super);
            }
        }

        if ($entraNoPeriodoComparativo) {
            $clientes[$key]['inad_total'] += $saldo;
            $clientes[$key]['inad_qtd_titulos']++;
            $clientes[$key]['maior_atraso_dias'] = max($clientes[$key]['maior_atraso_dias'], $diasAtraso);
            $clientes[$key]['inad_total_periodo_pct'] += $saldo;
            $clientes[$key]['inad_qtd_titulos_periodo_pct']++;

            $clientes[$key]['titulos'][] = [
                'filial' => (string) ($row['E1_FILIAL'] ?? ''),
                'prefixo' => (string) ($row['E1_PREFIXO'] ?? ''),
                'numero' => (string) ($row['E1_NUM'] ?? ''),
                'parcela' => (string) ($row['E1_PARCELA'] ?? ''),
                'tipo' => (string) ($row['E1_TIPO'] ?? ''),
                'natureza' => (string) ($row['E1_NATUREZ'] ?? ''),
                'emissao' => $emissao,
                'emissao_fmt' => fmtDate($emissao),
                'vencto' => $vencto,
                'vencto_fmt' => fmtDate($vencto),
                'baixa' => $baixa,
                'baixa_fmt' => fmtDate($baixa),
                'valor' => $valor,
                'saldo' => $saldo,
                'dias_atraso' => $diasAtraso,
                'faixa_atraso' => faixaAtraso($diasAtraso),
                'forma_pagamento' => $forma,
                'portador' => (string) ($row['E1_PORTADO'] ?? ''),
                'nosso_numero' => (string) ($row['E1_NUMBOR'] ?? ''),
                'titulo_composto' => $titulo,
            ];

            if ($diasAtraso > 0) {
                $bucket = faixaAtraso($diasAtraso);
                if (isset($aging[$bucket])) {
                    $aging[$bucket] += $saldo;
                }
            }

            $periodoKey = buildPeriodoKey($tsVencto, $groupBy);

            if (!isset($historicoMap[$periodoKey])) {
                $historicoMap[$periodoKey] = [
                    'periodo' => $periodoKey,
                    'label' => buildPeriodoLabel($periodoKey, $groupBy),
                    'inad_total' => 0.0,
                    'titulos' => 0,
                    'clientes' => [],
                ];
            }

            $historicoMap[$periodoKey]['inad_total'] += $saldo;
            $historicoMap[$periodoKey]['titulos']++;
            $historicoMap[$periodoKey]['clientes'][$key] = true;
        }
    }

    foreach ($clientes as $key => $cli) {
        $diasSum = 0;
        $diasQtd = 0;

        foreach (($cli['titulos'] ?? []) as $t) {
            $dias = (int) ($t['dias_atraso'] ?? 0);
            if ($dias > 0) {
                $diasSum += $dias;
                $diasQtd++;
            }
        }

        $clientes[$key]['media_atraso_dias'] = $diasQtd > 0 ? round($diasSum / $diasQtd, 1) : 0.0;
    }

    // -------- FATURAMENTO 000070 --------
    foreach ($fatRows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $codCliente = trim((string) pickFirst($row, ['C5_CLIENTE', 'COD_CLIENTE'], ''));
        $loja = trim((string) pickFirst($row, ['C5_LOJACLI', 'C5_LOJA', 'LOJA', 'LOJACLI'], ''));
        $nome = trim((string) pickFirst($row, ['A1_NOME', 'CLIENTE', 'NOME', 'CLIENTE_NOME'], 'CLIENTE NÃO IDENTIFICADO'));
        $cnpj = onlyDigits((string) pickFirst($row, ['A1_CGC', 'CGC', 'CNPJ'], ''));
        $emissao = onlyDigits((string) pickFirst($row, ['C5_EMISSAO', 'EMISAO', 'DATA_EMISSAO'], ''));
        $valor = toFloatBr(pickFirst($row, ['VALOR_PEDIDO', 'VALOR', 'VALOR_TOTAL'], 0));

        $vend1 = trim((string) pickFirst($row, ['C5_VEND1', 'E1_VEND1', 'VEND1'], ''));
        $vendedor = trim((string) pickFirst($row, ['A3_NOME', 'VENDEDOR', 'VENDEDOR_NOME'], ''));
        $super = trim((string) pickFirst($row, ['A3_SUPER', 'SUPER', 'SUPERVISOR'], ''));

        if ($codCliente === '') {
            continue;
        }

        if ($loja === '') {
            $loja = '0001';
        }

        $tsEmissao = parseYmd($emissao);
        if ($usarFiltroData && !inRange($tsEmissao, $dateFromTs, $dateToTs)) {
            continue;
        }

        if ($valor <= 0) {
            continue;
        }

        $key = clienteKey($codCliente, $loja);

        if (!isset($fatPorCliente[$key])) {
            $fatPorCliente[$key] = criarClienteBase(
                $codCliente,
                $loja,
                $nome,
                $cnpj,
                $vend1,
                $vendedor,
                $super
            );

            $fatPorCliente[$key]['inad_total'] = 0.0;
            $fatPorCliente[$key]['inad_qtd_titulos'] = 0;
            $fatPorCliente[$key]['inad_total_periodo_pct'] = 0.0;
            $fatPorCliente[$key]['inad_qtd_titulos_periodo_pct'] = 0;
            $fatPorCliente[$key]['maior_atraso_dias'] = 0;
            $fatPorCliente[$key]['media_atraso_dias'] = 0.0;
            $fatPorCliente[$key]['indice_inadimplencia_pct'] = 0.0;
            $fatPorCliente[$key]['titulos'] = [];
        } else {
            if (($fatPorCliente[$key]['nome'] ?? '') === '' || $fatPorCliente[$key]['nome'] === 'CLIENTE NÃO IDENTIFICADO') {
                $fatPorCliente[$key]['nome'] = $nome;
            }
            if (($fatPorCliente[$key]['cnpj'] ?? '') === '' && $cnpj !== '') {
                $fatPorCliente[$key]['cnpj'] = $cnpj;
            }
            if (($fatPorCliente[$key]['vendedor_codigo'] ?? '') === '' && $vend1 !== '') {
                $fatPorCliente[$key]['vendedor_codigo'] = $vend1;
            }
            if (($fatPorCliente[$key]['vendedor_nome'] ?? '') === '' && ($vend1 !== '' || $vendedor !== '')) {
                $fatPorCliente[$key]['vendedor_nome'] = nomeVendedor($vend1, $vendedor);
            }
            if (($fatPorCliente[$key]['supervisor_codigo'] ?? '') === '' && $super !== '') {
                $fatPorCliente[$key]['supervisor_codigo'] = $super;
                $fatPorCliente[$key]['supervisor_nome'] = nomeSupervisor($super);
            }
        }

        $fatPorCliente[$key]['faturado_periodo'] += $valor;
        $fatPorCliente[$key]['faturado_total'] += $valor;
        $fatPorCliente[$key]['qtd_pedidos']++;

        if (!isset($clientes[$key])) {
            $clientes[$key] = criarClienteBase(
                $codCliente,
                $loja,
                $nome,
                $cnpj,
                $vend1,
                $vendedor,
                $super
            );
        } else {
            if (($clientes[$key]['nome'] ?? '') === '' || $clientes[$key]['nome'] === 'CLIENTE NÃO IDENTIFICADO') {
                $clientes[$key]['nome'] = $nome;
            }
            if (($clientes[$key]['cnpj'] ?? '') === '' && $cnpj !== '') {
                $clientes[$key]['cnpj'] = $cnpj;
            }
            if (($clientes[$key]['vendedor_codigo'] ?? '') === '' && $vend1 !== '') {
                $clientes[$key]['vendedor_codigo'] = $vend1;
            }
            if (($clientes[$key]['vendedor_nome'] ?? '') === '' && ($vend1 !== '' || $vendedor !== '')) {
                $clientes[$key]['vendedor_nome'] = nomeVendedor($vend1, $vendedor);
            }
            if (($clientes[$key]['supervisor_codigo'] ?? '') === '' && $super !== '') {
                $clientes[$key]['supervisor_codigo'] = $super;
                $clientes[$key]['supervisor_nome'] = nomeSupervisor($super);
            }
        }

        $clientes[$key]['faturado_periodo'] += $valor;
        $clientes[$key]['faturado_total'] += $valor;
        $clientes[$key]['qtd_pedidos']++;
    }

    // calcula índice e ordena títulos no array principal
    foreach ($clientes as $key => $cli) {
        $clientes[$key]['indice_inadimplencia_pct'] =
            $cli['faturado_periodo'] > 0
            ? round(($cli['inad_total_periodo_pct'] / $cli['faturado_periodo']) * 100, 2)
            : 0.0;

        usort($clientes[$key]['titulos'], function ($a, $b) {
            return ($b['dias_atraso'] <=> $a['dias_atraso']) ?: ($b['saldo'] <=> $a['saldo']);
        });
    }

    // cruza faturamento com inadimplência para o ranking top faturados
    $topFatBase = [];

    foreach ($fatPorCliente as $key => $fatCli) {
        $inadCli = $clientes[$key] ?? null;

        $inadTotal = $inadCli ? (float) ($inadCli['inad_total'] ?? 0) : 0.0;
        $inadQtdTitulos = $inadCli ? (int) ($inadCli['inad_qtd_titulos'] ?? 0) : 0;
        $inadTotalPeriodoPct = $inadCli ? (float) ($inadCli['inad_total_periodo_pct'] ?? 0) : 0.0;
        $inadQtdTitulosPeriodoPct = $inadCli ? (int) ($inadCli['inad_qtd_titulos_periodo_pct'] ?? 0) : 0;
        $maiorAtraso = $inadCli ? (int) ($inadCli['maior_atraso_dias'] ?? 0) : 0;
        $mediaAtraso = $inadCli ? (float) ($inadCli['media_atraso_dias'] ?? 0) : 0.0;
        $titulos = $inadCli ? (array) ($inadCli['titulos'] ?? []) : [];

        $fatCli['inad_total'] = $inadTotal;
        $fatCli['inad_qtd_titulos'] = $inadQtdTitulos;
        $fatCli['inad_total_periodo_pct'] = $inadTotalPeriodoPct;
        $fatCli['inad_qtd_titulos_periodo_pct'] = $inadQtdTitulosPeriodoPct;
        $fatCli['maior_atraso_dias'] = $maiorAtraso;
        $fatCli['media_atraso_dias'] = $mediaAtraso;
        $fatCli['titulos'] = $titulos;
        $fatCli['indice_inadimplencia_pct'] =
            $fatCli['faturado_periodo'] > 0
            ? round(($inadTotalPeriodoPct / $fatCli['faturado_periodo']) * 100, 2)
            : 0.0;

        $topFatBase[] = $fatCli;
    }

    /* =========================================================
       FILTROS NA TABELA
    ========================================================= */
    $clientesLista = array_values($clientes);
    $searchNorm = mb_strtolower($search, 'UTF-8');

    $clientesFiltrados = array_values(array_filter($clientesLista, function ($cli) use ($searchNorm, $filterVend, $filterSuper, $filterFaixa, $filterMinVal) {
        if ((float) ($cli['inad_total'] ?? 0) <= 0) {
            return false;
        }

        if ($filterMinVal > 0 && (float) ($cli['inad_total'] ?? 0) < $filterMinVal) {
            return false;
        }

        if ($filterVend !== '') {
            $vendNome = mb_strtolower((string) ($cli['vendedor_nome'] ?? ''), 'UTF-8');
            $vendCod = mb_strtolower((string) ($cli['vendedor_codigo'] ?? ''), 'UTF-8');
            $term = mb_strtolower($filterVend, 'UTF-8');

            if (strpos($vendNome, $term) === false && strpos($vendCod, $term) === false) {
                return false;
            }
        }

        if ($filterSuper !== '') {
            $supCod = mb_strtolower((string) ($cli['supervisor_codigo'] ?? ''), 'UTF-8');
            $supNome = mb_strtolower((string) ($cli['supervisor_nome'] ?? ''), 'UTF-8');
            $term = mb_strtolower($filterSuper, 'UTF-8');

            if (strpos($supCod, $term) === false && strpos($supNome, $term) === false) {
                return false;
            }
        }

        if ($filterFaixa !== '') {
            $okFaixa = false;
            foreach (($cli['titulos'] ?? []) as $t) {
                if ((string) ($t['faixa_atraso'] ?? '') === $filterFaixa) {
                    $okFaixa = true;
                    break;
                }
            }
            if (!$okFaixa) {
                return false;
            }
        }

        if ($searchNorm !== '') {
            $haystack = mb_strtolower(
                implode(' ', [
                    (string) ($cli['nome'] ?? ''),
                    (string) ($cli['cliente'] ?? ''),
                    (string) ($cli['cnpj'] ?? ''),
                    (string) ($cli['vendedor_nome'] ?? ''),
                    (string) ($cli['vendedor_codigo'] ?? ''),
                    (string) ($cli['supervisor_codigo'] ?? ''),
                    (string) ($cli['supervisor_nome'] ?? ''),
                ]),
                'UTF-8'
            );

            if (strpos($haystack, $searchNorm) === false) {
                return false;
            }
        }

        return true;
    }));

    /* =========================================================
       KPIS
    ========================================================= */
    $totalInad = 0.0;
    $totalInadPeriodoPct = 0.0;
    $totalFaturadoPeriodo = 0.0;
    $totalTitulos = 0;
    $totalClientesInad = 0;
    $somaDiasAtraso = 0.0;
    $qtdDias = 0;

    foreach ($clientesFiltrados as $cli) {
        $totalInad += (float) ($cli['inad_total'] ?? 0);
        $totalInadPeriodoPct += (float) ($cli['inad_total_periodo_pct'] ?? 0);
        $totalFaturadoPeriodo += (float) ($cli['faturado_periodo'] ?? 0);
        $totalTitulos += (int) ($cli['inad_qtd_titulos'] ?? 0);

        if ((float) ($cli['inad_total'] ?? 0) > 0) {
            $totalClientesInad++;
        }

        foreach (($cli['titulos'] ?? []) as $t) {
            $dias = (int) ($t['dias_atraso'] ?? 0);
            if ($dias > 0) {
                $somaDiasAtraso += $dias;
                $qtdDias++;
            }
        }
    }

    $ticketMedioInad = $totalClientesInad > 0 ? round($totalInad / $totalClientesInad, 2) : 0.0;
    $mediaDiasAtraso = $qtdDias > 0 ? round($somaDiasAtraso / $qtdDias, 1) : 0.0;
    $indiceInadSobreFaturadoPct = $totalFaturadoPeriodo > 0
        ? round(($totalInadPeriodoPct / $totalFaturadoPeriodo) * 100, 2)
        : 0.0;

    /* =========================================================
       HISTÓRICO
    ========================================================= */
    ksort($historicoMap);

    $historicoInadimplencia = [];
    $valorAnterior = null;

    foreach ($historicoMap as $item) {
        $valorAtual = round((float) ($item['inad_total'] ?? 0), 2);
        $variacao = null;
        $variacaoPct = null;

        if ($valorAnterior !== null) {
            $variacao = round($valorAtual - $valorAnterior, 2);

            if ((float) $valorAnterior > 0) {
                $variacaoPct = round((($valorAtual - $valorAnterior) / $valorAnterior) * 100, 2);
            }
        }

        $historicoInadimplencia[] = [
            'periodo' => (string) ($item['periodo'] ?? ''),
            'label' => (string) ($item['label'] ?? ''),
            'inad_total' => $valorAtual,
            'titulos' => (int) ($item['titulos'] ?? 0),
            'clientes' => count((array) ($item['clientes'] ?? [])),
            'variacao' => $variacao,
            'variacao_pct' => $variacaoPct,
        ];

        $valorAnterior = $valorAtual;
    }

    /* =========================================================
       RANKINGS
    ========================================================= */
    $topInad = $clientesFiltrados;
    usort($topInad, fn($a, $b) => $b['inad_total'] <=> $a['inad_total']);
    $topInad = array_slice($topInad, 0, 10);

    $topFat = $topFatBase;
    usort($topFat, function ($a, $b) {
        return ($b['faturado_periodo'] <=> $a['faturado_periodo']);
    });

    $topFat = array_map(function ($cli) {
        return [
            'cliente' => $cli['cliente'],
            'loja' => $cli['loja'],
            'cliente_key' => $cli['cliente_key'],
            'nome' => $cli['nome'],
            'cnpj' => $cli['cnpj'],
            'vendedor_codigo' => $cli['vendedor_codigo'],
            'vendedor_nome' => $cli['vendedor_nome'],
            'supervisor_codigo' => $cli['supervisor_codigo'],
            'supervisor_nome' => $cli['supervisor_nome'],

            'faturado_periodo' => round((float) $cli['faturado_periodo'], 2),
            'faturado_total' => round((float) $cli['faturado_total'], 2),
            'qtd_pedidos' => (int) $cli['qtd_pedidos'],

            'inad_total' => round((float) $cli['inad_total'], 2),
            'inad_qtd_titulos' => (int) $cli['inad_qtd_titulos'],
            'inad_total_periodo_pct' => round((float) $cli['inad_total_periodo_pct'], 2),
            'inad_qtd_titulos_periodo_pct' => (int) $cli['inad_qtd_titulos_periodo_pct'],
            'maior_atraso_dias' => (int) $cli['maior_atraso_dias'],
            'media_atraso_dias' => (float) $cli['media_atraso_dias'],
            'indice_inadimplencia_pct' => round((float) $cli['indice_inadimplencia_pct'], 2),
        ];
    }, $topFat);

    $topFat = array_slice($topFat, 0, 50);

    /* =========================================================
       OPÇÕES DE FILTRO
    ========================================================= */
    $vendedores = [];
    $supervisores = [];

    foreach ($clientesLista as $cli) {
        if (!empty($cli['vendedor_nome']) || !empty($cli['vendedor_codigo'])) {
            $label = trim((string) ($cli['vendedor_nome'] ?? ''));
            if ($label === '') {
                $label = trim((string) $cli['vendedor_codigo']);
            }
            $vendedores[$label] = [
                'value' => (string) $label,
                'label' => (string) $label,
            ];
        }

        if (!empty($cli['supervisor_codigo'])) {
            $supervisores[(string) $cli['supervisor_codigo']] = [
                'value' => (string) $cli['supervisor_codigo'],
                'label' => (string) ($cli['supervisor_nome'] ?? $cli['supervisor_codigo']),
            ];
        }
    }

    $vendedores = array_values($vendedores);
    $supervisores = array_values($supervisores);

    usort($vendedores, fn($a, $b) => strcmp($a['label'], $b['label']));
    usort($supervisores, fn($a, $b) => strcmp($a['label'], $b['label']));

    /* =========================================================
       RESPOSTA
    ========================================================= */
    $payload = [
        'success' => (!empty($info76['success']) || !empty($info70['success'])),
        'updated_at' => date('d/m/Y, H:i'),
        'filtros_aplicados' => [
            'usar_filtro_data' => $usarFiltroData,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'preset' => $preset,
            'group_by' => $groupBy,
            'dias_min_atraso' => $diasMinimosInadimplencia,
            'search' => $search,
            'vendedor' => $filterVend,
            'supervisor' => $filterSuper,
            'faixa_atraso' => $filterFaixa,
            'valor_min' => $filterMinVal,
            'regra_inadimplencia_real' => '000076 vem do SQL/TOTVS; período da tela usado apenas para % inad e histórico',
        ],
        'kpis' => [
            'total_inadimplente' => round($totalInad, 2),
            'total_inadimplente_periodo_pct' => round($totalInadPeriodoPct, 2),
            'total_faturado_periodo' => round($totalFaturadoPeriodo, 2),
            'indice_inadimplencia_pct' => $indiceInadSobreFaturadoPct,
            'total_titulos' => (int) $totalTitulos,
            'clientes_inadimplentes' => (int) $totalClientesInad,
            'ticket_medio_inadimplencia' => round($ticketMedioInad, 2),
            'media_dias_atraso' => round($mediaDiasAtraso, 1),
        ],
        'aging' => [
            ['faixa' => '1-30', 'valor' => round($aging['1-30'], 2)],
            ['faixa' => '31-60', 'valor' => round($aging['31-60'], 2)],
            ['faixa' => '61-90', 'valor' => round($aging['61-90'], 2)],
            ['faixa' => '91-180', 'valor' => round($aging['91-180'], 2)],
            ['faixa' => '180+', 'valor' => round($aging['180+'], 2)],
        ],
        'historico_inadimplencia' => $historicoInadimplencia,
        'top_inadimplentes' => array_values($topInad),
        'top_faturados' => array_values($topFat),
        'clientes' => array_values($clientesFiltrados),
        'options' => [
            'vendedores' => $vendedores,
            'supervisores' => $supervisores,
            'faixas_atraso' => ['1-30', '31-60', '61-90', '91-180', '180+'],
            'presets_historico' => [
                ['value' => '30d', 'label' => '30 dias'],
                ['value' => '90d', 'label' => '90 dias'],
                ['value' => '6m', 'label' => '6 meses'],
                ['value' => '12m', 'label' => '12 meses'],
            ],
            'group_by' => ['day', 'week', 'month'],
        ],
        'totvs_info' => [
            '000076' => $info76,
            '000070' => $info70,
        ],
        'debug' => [
            'fat_rows_total' => count($fatRows),
            'inad_rows_total' => count($inadRows),
            'fat_clientes_total' => count($fatPorCliente),
            'clientes_total' => count($clientes),
            'clientes_filtrados_total' => count($clientesFiltrados),
            'top_faturados_total' => count($topFat),
            'historico_total' => count($historicoInadimplencia),
            'preset' => $preset,
            'group_by_req' => $groupByReq,
            'group_by_final' => $groupBy,
            'total_inad_periodo_pct' => round($totalInadPeriodoPct, 2),
            'total_faturado_periodo' => round($totalFaturadoPeriodo, 2),
            'indice_inadimplencia_pct' => $indiceInadSobreFaturadoPct,
        ],
    ];

    @file_put_contents($cacheFile, json_encode($payload, JSON_UNESCAPED_UNICODE));

    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}