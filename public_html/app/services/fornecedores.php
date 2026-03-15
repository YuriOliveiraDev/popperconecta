<?php
declare(strict_types=1);
if (!function_exists('callTotvsApi')) {
    require_once APP_ROOT . '/app/config/config-totvs.php';
}
require_once APP_ROOT . '/app/config/config-totvs.php';

function normalizarTextoTotvs(string $texto): string
{
    $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto);

    $enc = mb_detect_encoding($texto, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    if ($enc && $enc !== 'UTF-8') {
        $texto = mb_convert_encoding($texto, 'UTF-8', $enc);
    }

    $texto = iconv('UTF-8', 'UTF-8//IGNORE', $texto);
    return trim($texto);
}

function montarDicionarioFornecedoresTotvs(array $items): array
{
    $fornecedores = [];

    foreach ($items as $row) {
        if (!is_array($row)) {
            continue;
        }

        $codigo = trim((string)($row['CODIGO'] ?? ''));
        $nome   = trim((string)($row['NOME'] ?? ''));

        if ($codigo === '' || $nome === '') {
            continue;
        }

        if (preg_match('/^\d+$/', $codigo)) {
            $codigo = str_pad($codigo, 6, '0', STR_PAD_LEFT);
        }

        $nome = normalizarTextoTotvs($nome);
        $fornecedores[$codigo] = $nome;
    }

    ksort($fornecedores, SORT_NATURAL);
    return $fornecedores;
}

function fornecedoresCacheFile(): string
{
    return dirname(__DIR__) . '/cache/fornecedores.json';
}

function carregarFornecedoresCache(): array
{
    $arquivo = fornecedoresCacheFile();

    if (!is_file($arquivo)) {
        return [];
    }

    $json = file_get_contents($arquivo);
    if ($json === false || trim($json) === '') {
        return [];
    }

    $dados = json_decode($json, true);
    return is_array($dados) ? $dados : [];
}

function salvarFornecedoresCache(array $fornecedores): bool
{
    $arquivo = fornecedoresCacheFile();
    $pasta = dirname($arquivo);

    if (!is_dir($pasta)) {
        if (!mkdir($pasta, 0775, true) && !is_dir($pasta)) {
            return false;
        }
    }

    $json = json_encode($fornecedores, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) {
        return false;
    }

    return file_put_contents($arquivo, $json) !== false;
}

function buscarFornecedoresTotvs(): array
{
    $ret = callTotvsApi('000074');

    if (!$ret['success'] || !is_array($ret['data'])) {
        return [];
    }

    $data = $ret['data'];
    $items = $data['items'] ?? null;

    if (!is_array($items)) {
        return [];
    }

    return montarDicionarioFornecedoresTotvs($items);
}

function carregarFornecedores(bool $forcarAtualizacao = false): array
{
    $cacheTtl = 60 * 60 * 12; // 12 horas
    $arquivo = fornecedoresCacheFile();

    if (!$forcarAtualizacao && is_file($arquivo)) {
        $expirado = (filemtime($arquivo) + $cacheTtl) < time();
        if (!$expirado) {
            $cache = carregarFornecedoresCache();
            if (!empty($cache)) {
                return $cache;
            }
        }
    }

    $fornecedores = buscarFornecedoresTotvs();

    if (!empty($fornecedores)) {
        salvarFornecedoresCache($fornecedores);
        return $fornecedores;
    }

    // fallback: se API falhar, usa cache antigo
    $cache = carregarFornecedoresCache();
    if (!empty($cache)) {
        return $cache;
    }

    return [];
}

function atualizarFornecedoresCache(): array
{
    return carregarFornecedores(true);
}