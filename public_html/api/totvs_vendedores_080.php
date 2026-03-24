<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config-totvs.php';

header('Content-Type: application/json; charset=utf-8');

try {
    require_login();

    $resp = callTotvsApi('000080');

    if (!$resp['success'] || !is_array($resp['data'])) {
        throw new RuntimeException(
            'Falha ao consultar TOTVS 000080. ' .
            ($resp['json_error'] ?: ($resp['info']['error'] ?? 'sem detalhes'))
        );
    }

    $items = $resp['data']['items'] ?? [];

    if (!is_array($items)) {
        $items = [];
    }

    $vendedores = array_values(array_filter(array_map(
        static function (array $item): ?array {
            $codigo = trim((string)($item['COD_VENDEDOR'] ?? ''));
            $nome   = trim((string)($item['NOME_VENDEDOR'] ?? ''));
            $email  = trim((string)($item['EMAIL'] ?? ''));
            $fone   = trim((string)($item['TELEFONE'] ?? ''));
            $status = trim((string)($item['STATUS'] ?? ''));

            if ($codigo === '' && $nome === '' && $email === '') {
                return null;
            }

            return [
                'codigo' => $codigo,
                'nome' => $nome,
                'email' => $email,
                'telefone' => $fone,
                'status' => $status,
            ];
        },
        $items
    )));

    echo json_encode([
        'ok' => true,
        'items' => $vendedores,
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}