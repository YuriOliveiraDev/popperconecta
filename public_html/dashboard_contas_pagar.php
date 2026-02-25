<?php
date_default_timezone_set('America/Sao_Paulo');

// =========================
// CONFIG - AJUSTE AQUI
// =========================
define('API_URL', 'https://tuffloglogistica122016.protheus.cloudtotvs.com.br:4050/api/wscmrelaut/v1/Consulta/000033');
define('API_USER', 'YURI.YANG');
define('API_PASS', 'Tufflog@2026');
define('DISABLE_SSL', true);

// =========================
// HELPERS
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
    return date('Y-m-d', $ts);
}

function moneyBR($v) {
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function safe($v) {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// =========================
// MAPA: Centro de Custo (E2_CCD) -> Setor
// =========================
$CCD_SETORES = [
    "1.1.9"  => "COMPRAS",
    "1.1.8"  => "LOGÍSTICA",
    "1.2.2"  => "MARKETING",
    "1.2.6"  => "FACILITIES",
    "1.1.5"  => "RH",
    "1.1.10" => "COMEX",
    "1.3.1"  => "DIRETORIA COMERCIAL",
    "1.1.3"  => "TI",
    "1.1.4"  => "FINANCEIRO",
    "1.9.4"  => "Celebra Show 2025",
    "1.2.1" => "Vendas",
    "1.9.1"  => "BEAUTY FAIR",
    "1.2.7"  => "RATEIO",
    "1.1.7"  => "FATURAMENTO",
    "1.1.6"  => "ADMINISTRATIVO",
    "1.1.1"  => "CONTABILIDADE",
    "1.1.2"  => "FISCAL",
    "1.2.5"  => "SAC",
    "1.3.2"  => "DIRETORIA CONTROLADORIA",
    "1.2.8"  => "TRADE MARKETING",
    "1.9.2"  => "NOVOS NEGOCIOS",
];

function normalizarCCD($ccd) {
    $ccd = preg_replace('/\s+/', '', $ccd);
    return trim($ccd);
}

function nomeSetorCCD($ccd, $mapa) {
    $ccd = normalizarCCD((string)$ccd);
    if ($ccd === '') return 'N/A';
    $nome = $mapa[$ccd] ?? null;
    return $nome ? ($ccd . ' - ' . $nome) : $ccd;
}

// =========================
// MAPA: Fornecedor (E2_FORNECE) -> Nome
// =========================
$FORNECEDORES = [
    "000004" => "DELFIX INDUSTRIA E COMERCIO DE FITAS ADE",
    "000011" => "TELEFONICA BRASIL S.A",
    "000013" => "COPEL DISTRIBUICOES S.A",
    "000019" => "TUFFLOG LOGISTICA LTDA ME",
    "000039" => "SINGULARIS IND. IMP. E EXP. DE ARTEFATOS",
    "000058" => "EDUARDO GUIMARAES KUBITSKI SERVICOS",
    "000076" => "PEGASUS ASSESSORIA EM COMERCIO EXTERIOR",
    "000087" => "TCP - TERMINAL DE CONTEINERES DE PARANAG",
    "000106" => "YONGKANG CHAOSHUAI ARTS&CRAFTS CO.,LTD.",
    "000107" => "HONGKONG KONGSTAR DAILY COMMODITY CO., LTD",
    "000111" => "ZHANGZHOU TOP BRITE INDUSTRY TRADE CO.,LTD",
    "000114" => "SHINSTAR TRADING CO.,LTD",
    "000157" => "TIANJIN XINGYU ARTS AND CRAFTS CO., LTD",
    "000209" => "JR ASSESSORIA HABITACIONAL E REPRESENT.",
    "000213" => "HUGO JESUS SOARES SOCIEDADE INDIVIDUAL",
    "000219" => "L & R REPRESENTACOES LTDA",
    "000220" => "MANGA VERDE COM DE ART FESTAS E REP LTDA",
    "000221" => "A. J REPRESENTACOES COMERCIAIS EIRELI",
    "000222" => "AFREITAS REPRESENTACOES LTDA",
    "000223" => "O V DA CUNHA REPRESENTACOES E SERVICOS",
    "000224" => "P. A PANILHA LIMA",
    "000225" => "ELIGRE REPRESENTACAO DE PRESENTES LTDA",
    "000227" => "LUIZ FLAVIO REP COMERCIAIS EIRELI",
    "000228" => "JMD REPRESENTACOES COMERCIAIS LTDA",
    "000229" => "IMPERIUM REPRESENTACOES LTDA",
    "000236" => "FERREIRA & KURPIEL REP COMERCIAIS LTDA",
    "000248" => "FIDC PROSPECTA LP",
    "000250" => "HIPER FESTA FRANCHISING LTDA",
    "000258" => "TTJB TRANSPORTES E LOGISTICA EIREILI",
    "000280" => "ITAU SEGUROS S/A",
    "000281" => "BANCO ITAU",
    "000283" => "SANTANDER BRASIL ADMINISTRADORA DE CONSOR",
    "000310" => "ANDRESSA JESSELIN BASSINELLI",
    "000344" => "EDUARDO GUIMARAES KUBITSKI",
    "000345" => "BANCO SANTANDER",
    "000347" => "PRUDENTIAL DO BRASIL SEGUROS",
    "000354" => "TOTVS S A",
    "000362" => "PORTO SEGUROS COMPANHIA DE SEG. GERAIS",
    "000365" => "FUNDO DA JUSTIÇA DO PODER JUDICIARIO",
    "000382" => "EXPRESSO RIO VERMELHO TRANSPORTES LTDA",
    "000391" => "YANG I LIN",
    "000414" => "PYRO LOUIS H K TRADING GO",
    "000427" => "CMA CGM DO BRASIL AGÊNCIA MARÍTIMA LTDA",
    "000434" => "JOSE DOMINGOS LINARES EIRELI",
    "000438" => "STOCKCON CONSULTORIA E CONTABILIDADE LTD",
    "000456" => "ASSOCIACAO BRASILEIRA DE ARTIGOS PARA CA",
    "000457" => "FOLHA DE PAGAMENTO",
    "000475" => "RODONAVES TRANSP E ENCOMENDAS LTDA",
    "000487" => "MS BANK S.A. BANCO DE CAMBIO",
    "000504" => "FREDDY A RANGEL EPP",
    "000543" => "EXPLO SFX GMBH",
    "000544" => "JAS DO BRASIL AGENCIAMENTO LOGISTICO LTD",
    "000589" => "VORAZ TECNOLOGIA LTDA ME",
    "000610" => "GOLD LINE TRANSPORTES DE CARGAS AEREAS",
    "000626" => "SOLIDES TECNOLOGIA S/A",
    "000663" => "LL ASSESSORIA LTDA",
    "000672" => "RD GESTAO E SISTEMAS S.A.",
    "000734" => "YIWU PARTY STAR IMPORT&EXPORT CO.,LTD",
    "000738" => "MAGIC FX B.V.",
    "000739" => "UNIMED CURITIBA SOCIEDADE COOPERATIVA DE",
    "000748" => "RODOVITOR TRANSPORTES E LOCACAO DE VEICU",
    "000765" => "MARIA C. S. CARRAZEDO LTDA",
    "000793" => "FACEBOOK SERVICOS ONLINE DO BRASIL LTDA.",
    "000843" => "EBAZAR.COM.BR. LTDA",
    "000851" => "REFILCART COMERCIO E SERVICOS DE INFORMA",
    "000889" => "WEBFONES COMERCIO DE ARTIGOS DE TELEFONI",
    "000910" => "NOVA ESSENCIA FLORICULTURA LTDA.",
    "000915" => "ASSOCIACAO BRASILEIRA DO COMERCIO DE ART",
    "000918" => "SKYMARINE LOGISTICA LTDA",
    "000921" => "FUNGRAM GUANGZHOU INDUSTRY LIMITED.",
    "000937" => "ITAU UNIBANCO S.A.",
    "000970" => "EBANX LTDA",
    "000979" => "F.C.T. TRANSPORTE E LOGISTICA LTDA",
    "000982" => "SAD CONSULTORIA LTDA.",
    "000995" => "TRD TRANSPORTE RODOVIARIO DALFAN LTDA",
    "001030" => "ISISVISION",
    "001071" => "YANG I LIN DE AZEVEDO",
    "001109" => "MOBI ALL TECNOLOGIA S.A",
    "001116" => "FLASH TECNOLOGIA E INSTITUICAO DE PAGAM",
    "001126" => "AVIOES TRANSPORTES LTDA",
    "001167" => "GRT TRANSPORTE E LOGISTICA LTDA",
    "001202" => "TAM LINHAS AEREAS S/A.",
    "001255" => "ASIA SHIPPING TRANSPORTES INTERNACIONAIS",
    "001264" => "ANA CRISTINA STREMEL BIERNASKI HORTIFRUT",
    "001272" => "NCH TRANSPORTES E LOGISTICA LTDA",
    "001301" => "BAODING HABO ARTS AND CRAFTS MANUFACTURI",
    "001303" => "JINHUA JOSO TECHNOLOGY CO.,LTD",
    "001329" => "JINHUA LVHUA PLASTIC CO.,LTD",
    "001330" => "DEPARTAMENTO DE TRANSITO DETRAN",
    "001348" => "ADOBE SYSTEMS BRASIL LTDA.",
    "001352" => "BANCO ALFA DE INVESTIMENTO S.A.",
    "001354" => "FLEXYDIGITAL TECNOLOGIA LTDA",
    "001360" => "G4 EDUCACAO LTDA",
    "001404" => "CIALFIR, SL",
    "001418" => "MFIELD COMUNICACAO ESTRATEGICA LTDA.",
    "001445" => "MARCIO MODESTO 28037465810",
    "001448" => "YIHAI TRADING CORPORATION",
    "001467" => "UBER DO BRASIL TECNOLOGIA LTDA.",
    "001482" => "VIKINGS DIGITAL LTDA",
    "001548" => "1",
    "001565" => "ACTA CERTIFICACOES LTDA",
    "001566" => "CANVA MARKETING LTDA",
    "001601" => "A.V .IMPRESSOES E XEROCOPIAS LTDA",
    "001621" => "KEMPER S.R.L.",
    "001624" => "CIS TREINAMENTO EM DESENVOLVIMENTO PROFI",
    "001629" => "KINGSONS BAGS GROUP CO., LIMITED",
    "001630" => "TECMAR TRANSPORTES LTDA.",
    "001631" => "BANCO COOPERATIVO SICREDI S.A.",
    "001655" => "ELETRO PARA VOCE LTDA",
    "001661" => "DLT LOGISTICA EM TRANSPORTES LTDA",
    "001669" => "JEFFERSON DA SILVA PEREIRA",
    "001675" => "UNION SERVICE CO.,LTD",
    "001681" => "34.846.974 JORGE LUIZ ANDRETTA FILHO",
    "001687" => "61.454.205 EDUARDO JOSE POLAK",
    "001691" => "HOTEIS ROYAL PALM PLAZA LTDA.",
    "001693" => "DIMENSA S.A.",
    "001700" => "AFC COMERCIO VAREJISTA DE COMPONENTES ELETRONICOS",
    "001713" => "E.V.F COMERCIO DE VARIEDADES E GESTAO LTDA",
    "001723" => "EMIRATES",
    "001757" => "HOTEL VERDE MAR LTDA",
    "001762" => "ONFLY TECNOLOGIA LTDA",
    "001764" => "ASSOCIACAO HOSPITALAR DE PROT INFANCIA DR RAUL CAR",
    "001765" => "LEADLOVERS TECNOLOGIA LTDA",
    "001773" => "AJ FERREIRA COMERCIO DE EMBALAGENS LTDA",
    "001776" => "UAU HUB CONSULTORIA EM MARKETING LTDA",
    "001782" => "NINGJIN COUNTY BAIHUA CANDLE INDUSTRY CO., LTD",
    "001815" => "BDN LOGISTICS LTDA",
    "001816" => "ASSOCIACAO PARANAENSE DE SUPERMERCADOS",
    "001826" => "RS CONSULTING CURSOS TECNICOS LTDA",
    "001829" => "JJSAF INDUSTRIA E COMERCIO DE PLASTICO LTDA",
    "001841" => "FLASHJOY YIWU CO., LTD",
    "001843" => "PLAY SERVICE STORE ASSISTENCIA TECNICA E COMERCIO",
    "001873" => "UBIQUITI BRAZIL COMERCIO DE ELETRONICOS LTDA.",
    "001875" => "SIMPLIFICAMAIS SOFTWARE E INOVACAO LTDA",
    "001902" => "NATHAN MATTOS DE LIMA CONSULTORIA EM PUBLICIDADE LTDA",
    "001904" => "LOGTEMPO LOGISTICA INTELIGENTE LTDA",
    "001929" => "THERESINHA KAREN CALDAS DE ARAUJO MORAES",
    "001930" => "MANA COMERCIO VAREJISTAS LTDA",
    "001931" => "VIVIAN TUMASONIS DE CASTRO",
    "001932" => "AFIX COMERCIO DE SOLUCOES FLEXIVEIS LTDA",
    "001933" => "NATOCAMP DISTRIBUIDORA LTDA",
    "001934" => "CAVALHEIRO COMERCIAL LTDA",
    "001935" => "COMPETICAO INDUSTRIA E COMERCIO DE MOVEIS PARA ESC",
    "001936" => "E J P VENDAS LTDA",
    "001938" => "BEST PARK TRANSPORTES E LOCACOES LTDA",
    "001939" => "GGS LOCAL CONSULTORIA E COMERCIO LTDA",
    "001940" => "ICELROMA PACK COMERCIO DE EMBALAGENS LTDA",
    "001941" => "DDMIX ECOMMERCE LTDA",
    "001944" => "STALTEK SOLUCOES EM ENERGIA LTDA",
    "001945" => "BR KUSH LOUNGE LTDA",
    "001953" => "HEFEI LEKI LIGHT INDUSTRY CO.,LTD",
    "001956" => "ROSEMERI SCARAMELLA RAISSA",
    "001958" => "TATIANA CAMARGO MODESTO",
    "001960" => "62.422.838 JOHN DANILO TOZATO",
    "001962" => "RODRIGUES CORREA COMERCIO DE BEBIDAS LTDA",
    "001963" => "BELBRAS IMPORTADORA E EXPORTADORA INDUSTRIA E COME",
    "001965" => "LW MAGAZINE LTDA",
    "001966" => "MADEIRA GARDEN LOGISTICA E COMERCIO DE PRODUTOS",
    "001967" => "FRANCISCO AVELANEDA",
    "001970" => "DEUTSCHE LUFTHANSA AG",
    "001971" => "RESTAURANTE DONA ANA DE SAO VICENTE LTDA",
    "001972" => "A THE CAKE LAB LTDA",
    "001973" => "BELLA CHAIR IMPORTADORA E DISTRIBUIDORA DE MOVEI",
    "001975" => "COMERCIAL LUZIA MEIRE DE GENEROS ALIMENTICIOS LTDA",
    "001976" => "JAILTON FERNANDES FAGUNDES",
    "001978" => "LAUVIAH COMERCIO VAREJISTA LTDA.",
    "001979" => "VERISURE BRASIL MONITORAMENTO DE ALARMES S.A",
    "001980" => "SONIA CARVALHO BALSALOBRE",
    "001982" => "ANDREA ALEXANDRA IBARRA RODRIGUEZ",
    "001983" => "MISS GLAMOUR PRESENTES E ACESSORIOS LTDA",
    "001984" => "FERNANDO VINICIUS GRANDE CENTRO AUTOMOTIVO LTDA",
    "001986" => "MRM - EMPREENDIMENTOS HOTELEIROS LTDA",
    "001987" => "ALCIDES VASCONCELOS DA SILVA JUNIOR",
    "001988" => "VIAGEM INTERNACIONAL ALEMANHA - EDUARDO",
    "001989" => "MIGET INDUSTRIA E COMERCIO DE EMBALAGENS LTDA",
    "001990" => "RAFAEL CAVICCHIA LTDA",
    "001991" => "KM TRANSPORTES RODOVIARIOS DE CARGAS LTDA",
    "001992" => "ALIANCA CASTING PROMOCOES LTDA",
    "001993" => "M2BR IMPORTACAO E COMERCIO DE ELETROELETRONICOS LT",
    "001994" => "M. S. COMERCIO E SERVICOS LTDA",
    "001996" => "BUYSELL STORE COMERCIO ONLINE LTDA",
    "001997" => "LAIS SILVA RIBAS",
    "002006" => "TRES MEIA CINCO LTDA",
    "002007" => "GDR DISTRIBUICOES LTDA",
    "002008" => "M M DISTRIBUIDORA E COMERCIO DE PRODUTOS LTDA",
    "002010" => "ANDREIA PEREIRA DA SILVA",
    "002011" => "PGU DISTRIBUIDORA DE EMBALAGENS LTDA",
    "UNIAO" => "UNIAO",
    "INPS" => "INSTITUTO NACIONAL DE PREVIDENCIA SOCIAL",
    "MUNIC" => "MUNICIPIO",
];

function nomeFornecedor($forn, $mapa) {
    $forn = trim((string)$forn);
    if ($forn === '') return 'N/A';
    $nome = $mapa[$forn] ?? null;
    return $nome ? ($forn . ' - ' . $nome) : $forn;
}

// =========================
// API CALL + ENCODING FIX
// =========================
function callTotvsJson($url, $user, $pass, $disableSSL) {
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

// =========================
// LOAD DATA
// =========================
$result = callTotvsJson(API_URL, API_USER, API_PASS, DISABLE_SSL);

$items = [];
if ($result['success'] && is_array($result['data'])) {
    if (isset($result['data']['items']) && is_array($result['data']['items'])) {
        $items = $result['data']['items'];
    }
}

// =========================
// PERÍODOS (para botões)
// =========================
$todayTs = strtotime('today');
$yesterdayTs = strtotime('yesterday');
$tomorrowTs = strtotime('tomorrow');

$next3Ts  = strtotime('+3 days', $todayTs);
$next7Ts  = strtotime('+7 days', $todayTs);
$next15Ts = strtotime('+15 days', $todayTs);

function rangeMonthTs($year, $month) {
    $from = strtotime(sprintf('%04d-%02d-01', $year, $month));
    $to = strtotime(date('Y-m-t', $from));
    return [$from, $to];
}

$curY = (int)date('Y');
$curM = (int)date('m');

[$curMonthFrom, $curMonthTo] = rangeMonthTs($curY, $curM);

$prevM = $curM - 1; $prevY = $curY;
if ($prevM === 0) { $prevM = 12; $prevY--; }
[$prevMonthFrom, $prevMonthTo] = rangeMonthTs($prevY, $prevM);

$nextM = $curM + 1; $nextY = $curY;
if ($nextM === 13) { $nextM = 1; $nextY++; }
[$nextMonthFrom, $nextMonthTo] = rangeMonthTs($nextY, $nextM);

// =========================
// FILTRO PERÍODO (E2_VENCREA)
// =========================
$defaultFromTs = $curMonthFrom;
$defaultToTs = $curMonthTo;

$fromTs = htmlDateToTs($_GET['from'] ?? '') ?? $defaultFromTs;
$toTs = htmlDateToTs($_GET['to'] ?? '') ?? $defaultToTs;

if ($fromTs > $toTs) { $tmp=$fromTs; $fromTs=$toTs; $toTs=$tmp; }

$itemsFiltered = array_values(array_filter($items, function($row) use ($fromTs, $toTs) {
    $vencTs = toDateTs($row['E2_VENCREA'] ?? '');
    if ($vencTs === null) return false;
return ($vencTs >= $fromTs && $vencTs <= $toTs);
}));

// =========================
// PROCESS (filtrado para rankings/totais)
// =========================
$totalValor = 0.0;
$totalQtd = 0;

$totalVencido = 0.0;
$totalAVencer = 0.0;

$topCentro = [];
$topFornecedor = [];

foreach ($itemsFiltered as $row) {
    $forn = trim((string)($row['E2_FORNECE'] ?? ''));
    $vencrea = $row['E2_VENCREA'] ?? '';
    $valor = (float)($row['E2_VALOR'] ?? 0);

    $ccdRaw = $row['E2_CCD'] ?? '';
    $ccdNomeado = nomeSetorCCD($ccdRaw, $CCD_SETORES);

    $vencTs = toDateTs($vencrea);

    $totalValor += $valor;
    $totalQtd++;

    if (!isset($topCentro[$ccdNomeado])) $topCentro[$ccdNomeado] = ['key' => $ccdNomeado, 'total' => 0.0, 'qtd' => 0];
    $topCentro[$ccdNomeado]['total'] += $valor;
    $topCentro[$ccdNomeado]['qtd']++;

    $fornNomeado = nomeFornecedor($forn, $FORNECEDORES);
    if (!isset($topFornecedor[$fornNomeado])) $topFornecedor[$fornNomeado] = ['key' => $fornNomeado, 'total' => 0.0, 'qtd' => 0];
    $topFornecedor[$fornNomeado]['total'] += $valor;
    $topFornecedor[$fornNomeado]['qtd']++;

    if ($vencTs !== null) {
        if ($vencTs < $todayTs) {
            $totalVencido += $valor;
        } else {
            $totalAVencer += $valor;
        }
    }
}

$topCentroList = array_values($topCentro);
usort($topCentroList, fn($a, $b) => $b['total'] <=> $a['total']);

$topFornecedorList = array_values($topFornecedor);
usort($topFornecedorList, fn($a, $b) => $b['total'] <=> $a['total']);

$maxCentro = 0.0; foreach ($topCentroList as $t) $maxCentro = max($maxCentro, $t['total']);
$maxForn = 0.0; foreach ($topFornecedorList as $t) $maxForn = max($maxForn, $t['total']);

// =========================
// PRÓXIMOS 3/7/15 (sempre de hoje, independente do filtro)
// =========================
$proximos3 = [];
$proximos7 = [];
$proximos15 = [];
$vencidos = [];

foreach ($items as $row) {
    $vencrea = $row['E2_VENCREA'] ?? '';
    $vencTs = toDateTs($vencrea);
    if ($vencTs === null) continue;

    if ($vencTs >= $todayTs && $vencTs <= $next3Ts) {
        $proximos3[] = $row;
    }
    if ($vencTs >= $todayTs && $vencTs <= $next7Ts) {
        $proximos7[] = $row;
    }
    if ($vencTs >= $todayTs && $vencTs <= $next15Ts) {
        $proximos15[] = $row;
    }
    if ($vencTs < $todayTs) {
        $vencidos[] = $row;
    }
}

usort($proximos3, fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? PHP_INT_MAX) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? PHP_INT_MAX));
usort($proximos7, fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? PHP_INT_MAX) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? PHP_INT_MAX));
usort($proximos15, fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? PHP_INT_MAX) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? PHP_INT_MAX));
usort($vencidos, fn($a, $b) => (toDateTs($a['E2_VENCREA'] ?? '') ?? 0) <=> (toDateTs($b['E2_VENCREA'] ?? '') ?? 0));

$sumProx3  = array_sum(array_map(fn($r)=>(float)($r['E2_VALOR']??0), $proximos3));
$sumProx7  = array_sum(array_map(fn($r)=>(float)($r['E2_VALOR']??0), $proximos7));
$sumProx15 = array_sum(array_map(fn($r)=>(float)($r['E2_VALOR']??0), $proximos15));
$sumVencidos = array_sum(array_map(fn($r)=>(float)($r['E2_VALOR']??0), $vencidos));

// =========================
// LINK BUILDER
// =========================
function selfLinkWithRange($fromTs, $toTs) {
    $file = basename(__FILE__);
    return $file . '?from=' . urlencode(tsToHtmlDate($fromTs)) . '&to=' . urlencode(tsToHtmlDate($toTs));
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Dashboard - Contas a Pagar</title>
<style>
    .scroll-10{max-height:520px;overflow:auto}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:Segoe UI,system-ui,-apple-system,Arial,sans-serif;background:#f3f5f8;color:#1f2937}
.header{background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);color:#fff;padding:28px 22px;box-shadow:0 6px 22px rgba(0,0,0,.18)}
.header h1{font-size:22px;font-weight:700}
.header .sub{opacity:.9;margin-top:6px;font-size:13px}
.wrap{max-width:1400px;margin:0 auto;padding:22px}
.grid-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;margin-bottom:18px}
.card{background:#fff;border-radius:14px;box-shadow:0 6px 20px rgba(15,23,42,.06);overflow:hidden}
.kpi{padding:16px 16px;display:flex;gap:12px;align-items:center}
.kpi .icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px}
.i1{background:#e0f2fe}.i2{background:#dcfce7}.i3{background:#fee2e2}.i4{background:#fff7ed}
.kpi .label{font-size:12px;color:#6b7280;text-transform:uppercase;letter-spacing:.4px}
.kpi .value{font-size:20px;font-weight:800;margin-top:3px}
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px}
.grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px}
@media(max-width:1100px){.grid-2{grid-template-columns:1fr}.grid-3{grid-template-columns:1fr}}
.card-hd{padding:14px 16px;border-bottom:1px solid #eef2f7;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.card-hd h2{font-size:15px;font-weight:800;color:#111827}
.badge{font-size:12px;font-weight:700;padding:6px 10px;border-radius:999px;background:#e0f2fe;color:#1d4ed8;white-space:nowrap}
.badge.red{background:#fee2e2;color:#b91c1c}
.rank{list-style:none}
.rank li{display:flex;gap:12px;align-items:center;padding:12px 16px;border-bottom:1px solid #f1f5f9}
.rank li:last-child{border-bottom:none}
.pos{width:28px;height:28px;border-radius:999px;background:#e5e7eb;color:#374151;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:12px;flex:0 0 auto}
.pos.p1{background:#fbbf24;color:#111827}
.pos.p2{background:#cbd5e1;color:#111827}
.pos.p3{background:#fb923c;color:#111827}
.rinfo{flex:1;min-width:0}
.rtitle{font-weight:800;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.rmeta{font-size:12px;color:#6b7280;margin-top:3px}
.rval{text-align:right}
.rval .v{font-weight:900;color:#1e3a8a}
.bar{height:6px;background:#e5e7eb;border-radius:999px;margin-top:8px;overflow:hidden}
.fill{height:100%;background:linear-gradient(90deg,#60a5fa,#1d4ed8)}
.fill.green{background:linear-gradient(90deg,#4ade80,#16a34a)}
.table-wrap{overflow:auto;max-height:520px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:12px 12px;border-bottom:1px solid #f1f5f9;text-align:left;white-space:nowrap}
th{position:sticky;top:0;background:#f8fafc;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:#64748b}
td.val{font-weight:900;color:#1e3a8a;text-align:right}
td.ccd span{background:#e0f2fe;color:#1d4ed8;font-weight:800;padding:4px 8px;border-radius:8px}
.err{background:#fff;border-radius:14px;padding:18px;border-left:6px solid #ef4444;box-shadow:0 6px 20px rgba(15,23,42,.06)}
.err h2{font-size:16px;margin-bottom:6px}
.muted{color:#6b7280;font-size:13px}
.btn{position:fixed;right:18px;bottom:18px;border:none;border-radius:999px;padding:12px 16px;background:linear-gradient(135deg,#0f172a 0%,#1e3a8a 100%);color:#fff;font-weight:800;cursor:pointer;box-shadow:0 12px 30px rgba(30,58,138,.35)}
.btn:hover{transform:translateY(-1px)}
.filter{display:flex;gap:10px;flex-wrap:wrap;align-items:end}
.filter .f{display:flex;flex-direction:column;gap:6px}
.filter label{font-size:12px;color:#64748b;font-weight:800}
.filter input[type="date"]{border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px;font-weight:700}
.filter button{border:none;border-radius:10px;padding:10px 14px;background:#1e3a8a;color:#fff;font-weight:900;cursor:pointer}
.filter a{font-size:12px;color:#1d4ed8;font-weight:900;text-decoration:none;padding:10px 12px;border-radius:10px;border:1px solid #e5e7eb;background:#fff}
.quick{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.quick a{display:inline-block;font-size:12px;font-weight:900;text-decoration:none;color:#0f172a;border:1px solid #e5e7eb;background:#fff;padding:8px 10px;border-radius:999px}
.quick a.active{background:#0f172a;color:#fff;border-color:#0f172a}
</style>
</head>
<body>
<div class="header">
    <h1>Contas a Pagar</h1>
</div>

<div class="wrap">
<?php if (!$result['success']): ?>
    <div class="err">
        <h2>Não foi possível carregar os dados</h2>
        <div class="muted">HTTP: <?= safe($result['info']['http_code']) ?> | Content-Type: <?= safe($result['info']['content_type'] ?? '') ?></div>
        <div class="muted">cURL: <?= safe($result['info']['error'] ?: 'sem erro de cURL') ?> | JSON: <?= safe($result['json_error'] ?: 'ok') ?></div>
    </div>
<?php else: ?>

    <div class="card" style="margin-bottom:14px;">
        <div class="card-hd">
            <h2>Filtro (vencimento: E2_VENCREA)</h2>
            <span class="badge"><?= safe(ddmmyyyy(date('Ymd', $fromTs))) ?> até <?= safe(ddmmyyyy(date('Ymd', $toTs))) ?></span>
        </div>
        <div style="padding:14px 16px;">
            <form method="GET" class="filter">
                <div class="f">
                    <label>Data de</label>
                    <input type="date" name="from" value="<?= safe(tsToHtmlDate($fromTs)) ?>">
                </div>
                <div class="f">
                    <label>Data até</label>
                    <input type="date" name="to" value="<?= safe(tsToHtmlDate($toTs)) ?>">
                </div>
                <button type="submit">Aplicar</button>
                <a href="<?= safe(basename(__FILE__)) ?>">Limpar</a>
            </form>

            <?php
                $active = tsToHtmlDate($fromTs) . '|' . tsToHtmlDate($toTs);
                $mkActive = function($a) use ($active) { return $a === $active ? 'active' : ''; };
            ?>

            <div class="quick">

                                <a class="<?= $mkActive(tsToHtmlDate(strtotime('-2 days', $todayTs)).'|'.tsToHtmlDate($todayTs)) ?>" href="<?= safe(selfLinkWithRange(strtotime('-2 days', $todayTs), $todayTs)) ?>">Últimos 3 dias</a>
                <a class="<?= $mkActive(tsToHtmlDate(strtotime('-6 days', $todayTs)).'|'.tsToHtmlDate($todayTs)) ?>" href="<?= safe(selfLinkWithRange(strtotime('-6 days', $todayTs), $todayTs)) ?>">Últimos 7 dias</a>

                <a class="<?= $mkActive(tsToHtmlDate($yesterdayTs).'|'.tsToHtmlDate($yesterdayTs)) ?>" href="<?= safe(selfLinkWithRange($yesterdayTs, $yesterdayTs)) ?>">Ontem</a>
                <a class="<?= $mkActive(tsToHtmlDate($todayTs).'|'.tsToHtmlDate($todayTs)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $todayTs)) ?>">Hoje</a>
                <a class="<?= $mkActive(tsToHtmlDate($tomorrowTs).'|'.tsToHtmlDate($tomorrowTs)) ?>" href="<?= safe(selfLinkWithRange($tomorrowTs, $tomorrowTs)) ?>">Amanhã</a>

                                <a class="<?= $mkActive(tsToHtmlDate($todayTs).'|'.tsToHtmlDate($next3Ts)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $next3Ts)) ?>">Próximos 3 dias</a>
                <a class="<?= $mkActive(tsToHtmlDate($todayTs).'|'.tsToHtmlDate($next7Ts)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $next7Ts)) ?>">Próximos 7 dias</a>
                <a class="<?= $mkActive(tsToHtmlDate($todayTs).'|'.tsToHtmlDate($next15Ts)) ?>" href="<?= safe(selfLinkWithRange($todayTs, $next15Ts)) ?>">Próximos 15 dias</a>


                
                <a class="<?= $mkActive(tsToHtmlDate($prevMonthFrom).'|'.tsToHtmlDate($prevMonthTo)) ?>" href="<?= safe(selfLinkWithRange($prevMonthFrom, $prevMonthTo)) ?>">Mês passado</a>
                <a class="<?= $mkActive(tsToHtmlDate($curMonthFrom).'|'.tsToHtmlDate($curMonthTo)) ?>" href="<?= safe(selfLinkWithRange($curMonthFrom, $curMonthTo)) ?>">Mês atual</a>
                <a class="<?= $mkActive(tsToHtmlDate($nextMonthFrom).'|'.tsToHtmlDate($nextMonthTo)) ?>" href="<?= safe(selfLinkWithRange($nextMonthFrom, $nextMonthTo)) ?>">Próximo mês</a>


            </div>

            <div class="muted" style="margin-top:10px;">
                Agora limpa caracteres invisíveis no E2_CCD e nomeia fornecedores em E2_FORNECE.
            </div>
        </div>
    </div>

    <div class="grid-cards">
        <div class="card"><div class="kpi"><div class="icon i1">💳</div><div><div class="label">Total (registros)</div><div class="value"><?= number_format($totalQtd, 0, ',', '.') ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="icon i2">💰</div><div><div class="label">Total a pagar (período)</div><div class="value"><?= moneyBR($totalValor) ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="icon i4">📆</div><div><div class="label">Próximos 3 dias</div><div class="value"><?= moneyBR($sumProx3) ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="icon i4">📆</div><div><div class="label">Próximos 7 dias</div><div class="value"><?= moneyBR($sumProx7) ?></div></div></div></div>
        <div class="card"><div class="kpi"><div class="icon i4">📆</div><div><div class="label">Próximos 15 dias</div><div class="value"><?= moneyBR($sumProx15) ?></div></div></div></div>
    </div>

   <div class="grid-2">
    <div class="card">
        <div class="card-hd">
            <h2>Top gastos por Centro de Custo (E2_CCD)</h2>
            <span class="badge">Top <?= count($topCentroList) ?></span>
        </div>

        <div class="scroll-10">
            <ul class="rank">
                <?php $p=1; foreach ($topCentroList as $t):
                    $cls = ($p === 1 ? 'p1' : ($p === 2 ? 'p2' : ($p === 3 ? 'p3' : '')));
                    $w = $maxCentro > 0 ? ($t['total'] / $maxCentro) * 100 : 0;
                    $pct = $totalValor > 0 ? ($t['total'] / $totalValor) * 100 : 0;
                ?>
                <li>
                    <div class="pos <?= $cls ?>"><?= $p ?></div>
                    <div class="rinfo">
                        <div class="rtitle" title="<?= safe($t['key']) ?>"><?= safe($t['key']) ?></div>
                        <div class="rmeta"><?= (int)$t['qtd'] ?> títulos • <?= number_format($pct, 1, ',', '.') ?>%</div>
                        <div class="bar"><div class="fill" style="width:<?= number_format($w,2,'.','') ?>%"></div></div>
                    </div>
                    <div class="rval"><div class="v"><?= moneyBR($t['total']) ?></div></div>
                </li>
                <?php $p++; endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-hd">
            <h2>Top gastos por Fornecedor (E2_FORNECE)</h2>
            <span class="badge">Top <?= count($topFornecedorList) ?></span>
        </div>

        <div class="scroll-10">
            <ul class="rank">
                <?php $p=1; foreach ($topFornecedorList as $t):
                    $cls = ($p === 1 ? 'p1' : ($p === 2 ? 'p2' : ($p === 3 ? 'p3' : '')));
                    $w = $maxForn > 0 ? ($t['total'] / $maxForn) * 100 : 0;
                    $pct = $totalValor > 0 ? ($t['total'] / $totalValor) * 100 : 0;
                ?>
                <li>
                    <div class="pos <?= $cls ?>"><?= $p ?></div>
                    <div class="rinfo">
                        <div class="rtitle" title="<?= safe($t['key']) ?>"><?= safe($t['key']) ?></div>
                        <div class="rmeta"><?= (int)$t['qtd'] ?> títulos • <?= number_format($pct, 1, ',', '.') ?>%</div>
                        <div class="bar"><div class="fill green" style="width:<?= number_format($w,2,'.','') ?>%"></div></div>
                    </div>
                    <div class="rval"><div class="v"><?= moneyBR($t['total']) ?></div></div>
                </li>
                <?php $p++; endforeach; ?>
            </ul>
        </div>
    </div>
</div>

    <div class="grid-3">
        <div class="card">
            <div class="card-hd">
                <h2>Contas a pagar — próximos 3 dias (a partir de hoje)</h2>
                <span class="badge">
                    <?= number_format(count($proximos3), 0, ',', '.') ?> itens • <?= moneyBR($sumProx3) ?>
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Filial</th>
                            <th>Emissão</th>
                            <th>Fornecedor</th>
                            <th>Venc.</th>
                            <th>Centro (setor)</th>
                            <th style="text-align:right;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proximos3)): ?>
                            <tr><td colspan="6" class="muted">Sem títulos vencendo nos próximos 3 dias.</td></tr>
                        <?php else: foreach ($proximos3 as $r):
                            $ccdRaw = $r['E2_CCD'] ?? '';
                            $ccdNome = nomeSetorCCD($ccdRaw, $CCD_SETORES);
                            $fornRaw = $r['E2_FORNECE'] ?? '';
                            $fornNome = nomeFornecedor($fornRaw, $FORNECEDORES);
                        ?>
                            <tr>
                                <td><?= safe($r['E2_FILIAL'] ?? '') ?></td>
                                <td><?= safe(ddmmyyyy($r['E2_EMISSAO'] ?? '')) ?></td>
                                <td title="<?= safe($fornRaw) ?>"><?= safe($fornNome) ?></td>
                                <td><?= safe(ddmmyyyy($r['E2_VENCREA'] ?? '')) ?></td>
                                <td class="ccd"><span title="<?= safe(normalizarCCD($ccdRaw)) ?>"><?= safe($ccdNome) ?></span></td>
                                <td class="val"><?= safe(moneyBR($r['E2_VALOR'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-hd">
                <h2>Contas a pagar — próximos 7 dias (a partir de hoje)</h2>
                <span class="badge">
                    <?= number_format(count($proximos7), 0, ',', '.') ?> itens • <?= moneyBR($sumProx7) ?>
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Filial</th>
                            <th>Emissão</th>
                            <th>Fornecedor</th>
                            <th>Venc.</th>
                            <th>Centro (setor)</th>
                            <th style="text-align:right;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proximos7)): ?>
                            <tr><td colspan="6" class="muted">Sem títulos vencendo nos próximos 7 dias.</td></tr>
                        <?php else: foreach ($proximos7 as $r):
                            $ccdRaw = $r['E2_CCD'] ?? '';
                            $ccdNome = nomeSetorCCD($ccdRaw, $CCD_SETORES);
                            $fornRaw = $r['E2_FORNECE'] ?? '';
                            $fornNome = nomeFornecedor($fornRaw, $FORNECEDORES);
                        ?>
                            <tr>
                                <td><?= safe($r['E2_FILIAL'] ?? '') ?></td>
                                <td><?= safe(ddmmyyyy($r['E2_EMISSAO'] ?? '')) ?></td>
                                <td title="<?= safe($fornRaw) ?>"><?= safe($fornNome) ?></td>
                                <td><?= safe(ddmmyyyy($r['E2_VENCREA'] ?? '')) ?></td>
                                <td class="ccd"><span title="<?= safe(normalizarCCD($ccdRaw)) ?>"><?= safe($ccdNome) ?></span></td>
                                <td class="val"><?= safe(moneyBR($r['E2_VALOR'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-hd">
                <h2>Contas a pagar — próximos 15 dias (a partir de hoje)</h2>
                <span class="badge">
                    <?= number_format(count($proximos15), 0, ',', '.') ?> itens • <?= moneyBR($sumProx15) ?>
                </span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Filial</th>
                            <th>Emissão</th>
                            <th>Fornecedor</th>
                            <th>Venc.</th>
                            <th>Centro (setor)</th>
                            <th style="text-align:right;">Valor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($proximos15)): ?>
                            <tr><td colspan="6" class="muted">Sem títulos vencendo nos próximos 15 dias.</td></tr>
                        <?php else: foreach ($proximos15 as $r):
                            $ccdRaw = $r['E2_CCD'] ?? '';
                            $ccdNome = nomeSetorCCD($ccdRaw, $CCD_SETORES);
                            $fornRaw = $r['E2_FORNECE'] ?? '';
                            $fornNome = nomeFornecedor($fornRaw, $FORNECEDORES);
                        ?>
                            <tr>
                                <td><?= safe($r['E2_FILIAL'] ?? '') ?></td>
                                <td><?= safe(ddmmyyyy($r['E2_EMISSAO'] ?? '')) ?></td>
                                <td title="<?= safe($fornRaw) ?>"><?= safe($fornNome) ?></td>
                                <td><?= safe(ddmmyyyy($r['E2_VENCREA'] ?? '')) ?></td>
                                <td class="ccd"><span title="<?= safe(normalizarCCD($ccdRaw)) ?>"><?= safe($ccdNome) ?></span></td>
                                <td class="val"><?= safe(moneyBR($r['E2_VALOR'] ?? 0)) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php endif; ?>
</div>

<button class="btn" onclick="location.reload()">Atualizar</button>
</body>
</html>