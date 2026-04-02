<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config.php';

function brl_excel($value): float
{
    return round((float) $value, 2);
}

function xml_escape(string $value): string
{
    return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_col_name(int $index): string
{
    $name = '';
    while ($index > 0) {
        $index--;
        $name = chr(65 + ($index % 26)) . $name;
        $index = intdiv($index, 26);
    }
    return $name;
}

function xlsx_inline_string_cell(string $ref, string $value): string
{
    return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . xml_escape($value) . '</t></is></c>';
}

function xlsx_number_cell(string $ref, $value, int $styleIndex = 0): string
{
    $num = is_numeric($value) ? (string) (0 + $value) : '0';
    $styleAttr = $styleIndex > 0 ? ' s="' . $styleIndex . '"' : '';
    return '<c r="' . $ref . '"' . $styleAttr . '><v>' . $num . '</v></c>';
}

function gerar_xlsx_titulos_detalhados(array $clientes, string $nomeArquivo = 'inadimplencia_detalhada.xlsx'): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('A extensão ZipArchive não está habilitada no servidor.');
    }

    $headers = [
        'Cliente Código',
        'Cliente Nome',
        'CNPJ',
        'Vendedor',
        'Supervisor',
        'Título',
        'Tipo',
        'Emissão',
        'Vencimento',
        'Dias Atraso',
        'Faixa Atraso',
        'Saldo',
        'Forma Pagamento',
    ];

    $rows = [];
    $rows[] = $headers;

    foreach ($clientes as $cli) {
        $titulos = is_array($cli['titulos'] ?? null) ? $cli['titulos'] : [];

        if (!$titulos) {
            $rows[] = [
                (string) ($cli['cliente'] ?? ''),
                (string) ($cli['nome'] ?? ''),
                (string) ($cli['cnpj'] ?? ''),
                (string) ($cli['vendedor_nome'] ?? ''),
                (string) ($cli['supervisor_nome'] ?? ''),
                '',
                '',
                '',
                '',
                '',
                '',
                brl_excel(0),
                '',
            ];
            continue;
        }

        foreach ($titulos as $t) {
            $rows[] = [
                (string) ($cli['cliente'] ?? ''),
                (string) ($cli['nome'] ?? ''),
                (string) ($cli['cnpj'] ?? ''),
                (string) ($cli['vendedor_nome'] ?? ''),
                (string) ($cli['supervisor_nome'] ?? ''),
                (string) ($t['titulo_composto'] ?? ''),
                (string) ($t['tipo'] ?? ''),
                (string) ($t['emissao_fmt'] ?? ''),
                (string) ($t['vencto_fmt'] ?? ''),
                (string) ($t['dias_atraso'] ?? ''),
                (string) ($t['faixa_atraso'] ?? ''),
                brl_excel($t['saldo'] ?? 0),
                (string) ($t['forma_pagamento'] ?? ''),
            ];
        }
    }

    $sheetRowsXml = '';
    foreach ($rows as $rowIndex => $row) {
        $excelRow = $rowIndex + 1;
        $sheetRowsXml .= '<row r="' . $excelRow . '">';

        foreach ($row as $colIndex => $value) {
            $cellRef = xlsx_col_name($colIndex + 1) . $excelRow;

            // Saldo = coluna 12 -> índice 11
            if ($colIndex === 11) {
                $sheetRowsXml .= xlsx_number_cell($cellRef, $value, 1);
            } else {
                $sheetRowsXml .= xlsx_inline_string_cell($cellRef, (string) $value);
            }
        }

        $sheetRowsXml .= '</row>';
    }

    $lastColumn = xlsx_col_name(count($headers));
    $lastRow = count($rows);

    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<dimension ref="A1:' . $lastColumn . $lastRow . '"/>'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>'
        . '<col min="1" max="1" width="16" customWidth="1"/>'
        . '<col min="2" max="2" width="40" customWidth="1"/>'
        . '<col min="3" max="3" width="22" customWidth="1"/>'
        . '<col min="4" max="5" width="28" customWidth="1"/>'
        . '<col min="6" max="6" width="22" customWidth="1"/>'
        . '<col min="7" max="7" width="14" customWidth="1"/>'
        . '<col min="8" max="9" width="14" customWidth="1"/>'
        . '<col min="10" max="10" width="12" customWidth="1"/>'
        . '<col min="11" max="11" width="14" customWidth="1"/>'
        . '<col min="12" max="12" width="14" customWidth="1"/>'
        . '<col min="13" max="13" width="20" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . $sheetRowsXml . '</sheetData>'
        . '<autoFilter ref="A1:' . $lastColumn . $lastRow . '"/>'
        . '</worksheet>';

    $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
        . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Inadimplência Detalhada" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'inad_xlsx_');
    if ($tmpFile === false) {
        throw new RuntimeException('Não foi possível criar arquivo temporário.');
    }

    $finalPath = $tmpFile . '.xlsx';
    if (!rename($tmpFile, $finalPath)) {
        @unlink($tmpFile);
        throw new RuntimeException('Não foi possível preparar arquivo XLSX temporário.');
    }

    $zip = new ZipArchive();
    if ($zip->open($finalPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($finalPath);
        throw new RuntimeException('Não foi possível montar o arquivo XLSX.');
    }

    $zip->addFromString('[Content_Types].xml', $contentTypesXml);
    $zip->addFromString('_rels/.rels', $rootRelsXml);
    $zip->addFromString('xl/workbook.xml', $workbookXml);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/styles.xml', $stylesXml);
    $zip->close();

    $content = file_get_contents($finalPath);
    @unlink($finalPath);

    if ($content === false) {
        throw new RuntimeException('Não foi possível ler o XLSX gerado.');
    }

    return [
        'name' => $nomeArquivo,
        'contentBytes' => base64_encode($content),
        'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];
}

try {
    require_login();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Método não permitido.');
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);

    if (!is_array($data)) {
        throw new RuntimeException('Payload inválido.');
    }

    $clientesPayload = is_array($data['clientes'] ?? null) ? $data['clientes'] : [];
    if (!$clientesPayload) {
        throw new RuntimeException('Nenhum cliente informado para exportação.');
    }

    $nomeArquivo = trim((string) ($data['nome_arquivo'] ?? 'inadimplencia_detalhada.xlsx'));
    if ($nomeArquivo === '') {
        $nomeArquivo = 'inadimplencia_detalhada.xlsx';
    }

    $xlsx = gerar_xlsx_titulos_detalhados($clientesPayload, $nomeArquivo);
    $binary = base64_decode($xlsx['contentBytes'], true);

    if ($binary === false) {
        throw new RuntimeException('Falha ao decodificar XLSX.');
    }

    header('Content-Type: ' . $xlsx['contentType']);
    header('Content-Disposition: attachment; filename="' . basename($xlsx['name']) . '"');
    header('Content-Length: ' . strlen($binary));
    echo $binary;
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo $e->getMessage();
    exit;
}