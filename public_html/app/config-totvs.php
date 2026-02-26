<?php

declare(strict_types=1);
date_default_timezone_set('America/Sao_Paulo');

// =========================
// CONFIGURAÇÕES API TOTVS
// =========================

// Base da API (sem travar consulta)
define('TOTVS_API_BASE', 'https://tuffloglogistica122016.protheus.cloudtotvs.com.br:4050/api/wscmrelaut/v1/Consulta/');

// Consulta padrão (caso você chame callTotvsApi() sem parâmetros)
define('TOTVS_DEFAULT_CONSULTA', '000033');

// Mapa de métricas -> consulta (adicione quantas quiser)
define('TOTVS_CONSULTAS', [
    'kpi_faturamento' => '000033',
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
    "1.2.1"  => "Vendas",
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

// =========================
// DICIONÁRIO: FORNECEDOR → NOME
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
    "UNIAO"  => "UNIAO",
    "INPS"   => "INSTITUTO NACIONAL DE PREVIDENCIA SOCIAL",
    "MUNIC"  => "MUNICIPIO",
];

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