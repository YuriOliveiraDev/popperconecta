<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/integrations/solides.php';

header('Content-Type: application/json; charset=utf-8');


if (!function_exists('array_is_list')) {
    function array_is_list(array $array): bool
    {
        $i = 0;
        foreach ($array as $k => $v) {
            if ($k !== $i++) {
                return false;
            }
        }
        return true;
    }
}
function json_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function arr_get(array $data, array $path): mixed
{
    $current = $data;

    foreach ($path as $key) {
        if (!is_array($current) || !array_key_exists($key, $current)) {
            return null;
        }
        $current = $current[$key];
    }

    return $current;
}

function first_non_empty(array $values): ?string
{
    foreach ($values as $value) {
        if ($value === null) {
            continue;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            return $value;
        }
    }

    return null;
}

function normalesp(string $s): string
{
    $s = trim(mb_strtolower($s));
    $map = [
        'á' => 'a',
        'à' => 'a',
        'ã' => 'a',
        'â' => 'a',
        'ä' => 'a',
        'é' => 'e',
        'è' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'í' => 'i',
        'ì' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ó' => 'o',
        'ò' => 'o',
        'õ' => 'o',
        'ô' => 'o',
        'ö' => 'o',
        'ú' => 'u',
        'ù' => 'u',
        'û' => 'u',
        'ü' => 'u',
        'ç' => 'c'
    ];
    return strtr($s, $map);
}

function solides_list_colaboradores(int $page = 1, int $pageSize = 100, string $status = 'todos'): array
{
    $query = http_build_query([
        'page' => $page,
        'page_size' => $pageSize,
        'status' => $status,
    ]);

    return solides_request('GET', '/colaboradores?' . $query);
}

try {
    $nomeBusca = isset($_GET['nome']) ? trim((string) $_GET['nome']) : '';
    $pagina = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $pageSize = isset($_GET['page_size']) ? max(1, min(150, (int) $_GET['page_size'])) : 100;

    $ret = solides_list_colaboradores($pagina, $pageSize, 'todos');

    // tenta descobrir onde estão os colaboradores no retorno
    $lista = [];
    if (isset($ret['data']) && is_array($ret['data'])) {
        $lista = $ret['data'];
    } elseif (isset($ret['colaboradores']) && is_array($ret['colaboradores'])) {
        $lista = $ret['colaboradores'];
    } elseif (array_is_list($ret)) {
        $lista = $ret;
    }

    $filtrados = [];

    foreach ($lista as $col) {
        if (!is_array($col)) {
            continue;
        }

        $nome = first_non_empty([
            $col['nome'] ?? null,
            $col['name'] ?? null,
            arr_get($col, ['dados_pessoais', 'nome']) ?? null,
        ]);

        $email = first_non_empty([
            arr_get($col, ['contato', 'email_empresarial']),
            arr_get($col, ['contato', 'email']),
            $col['email'] ?? null,
        ]);

        $cpf = first_non_empty([
            arr_get($col, ['documentos', 'cpf']),
            $col['cpf'] ?? null,
        ]);

        $matricula = first_non_empty([
            $col['registration'] ?? null,
            $col['matricula'] ?? null,
            $col['codigo'] ?? null,
        ]);

        $id = $col['id'] ?? null;

        if ($nomeBusca !== '') {
            $n1 = normalesp($nomeBusca);
            $n2 = normalesp((string) $nome);

            if ($n2 === '' || mb_strpos($n2, $n1) === false) {
                continue;
            }
        }

        $filtrados[] = [
            'id' => $id,
            'nome' => $nome,
            'email' => $email,
            'cpf' => $cpf,
            'matricula' => $matricula,
            'raw' => $col,
        ];
    }

    json_response(200, [
        'ok' => true,
        'nome_busca' => $nomeBusca,
        'pagina' => $pagina,
        'page_size' => $pageSize,
        'total_recebidos_na_pagina' => count($lista),
        'total_filtrados' => count($filtrados),
        'resultados' => $filtrados,
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
}