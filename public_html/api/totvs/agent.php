<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config-totvs.php';
require_once APP_ROOT . '/app/services/totvs_agent.php';

$secretsFile = APP_ROOT . '/app/config/config-secrets.php';
if (is_file($secretsFile)) {
    require_once $secretsFile;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Deploy-Version: debug-2026-05-11');
if (function_exists('opcache_reset')) {
    opcache_reset();
}

function agent_totvs_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function agent_totvs_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';
    return is_string($value) ? trim($value) : '';
}

function agent_totvs_access_context(array $requiredPerms): array
{
    $expectedToken = trim((string) (
        getenv('POPPER_INTERNAL_AGENT_TOKEN')
        ?: (defined('POPPER_INTERNAL_AGENT_TOKEN') ? POPPER_INTERNAL_AGENT_TOKEN : '')
    ));
    $providedToken = agent_totvs_header('X-Internal-Agent-Token');

    if ($providedToken === '') {
        $authHeader = agent_totvs_header('Authorization');
        if (preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            $providedToken = trim((string) ($matches[1] ?? ''));
        }
    }

    if ($expectedToken !== '' && $providedToken !== '' && hash_equals($expectedToken, $providedToken)) {
        return [
            'auth_mode' => 'internal_token',
            'user' => null,
        ];
    }

    $user = current_user();
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'ok' => false,
            'message' => 'Nao autenticado.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($requiredPerms !== []) {
        $allowed = false;
        foreach ($requiredPerms as $perm) {
            if (user_can($perm, $user)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            http_response_code(403);
            echo json_encode([
                'ok' => false,
                'message' => 'Acesso negado.',
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    return [
        'auth_mode' => 'session',
        'user' => [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
        ],
    ];
}

function agent_totvs_perms_for_action(string $action): array
{
    $financeiro = ['dash.financeiro.inadimplencia'];
    $comercial = [
        'dash.comercial.clientes',
        'dash.comercial.executivo',
        'dash.comercial.faturamento',
        'dash.financeiro.inadimplencia',
    ];
    $comex = ['dash.comex.importacoes'];
    $financeiroAmplo = [
        'dash.financeiro.inadimplencia',
        'dash.financeiro.contasp',
        'dash.comercial.executivo',
        'dash.comercial.faturamento',
    ];

    switch ($action) {
        case 'top_clientes_inadimplentes':
        case 'cliente_com_mais_titulos_em_atraso':
        case 'vendedor_com_maior_inadimplencia':
        case 'clientes_inadimplentes_por_vendedor':
        case 'total_inadimplente_por_supervisor':
            return $financeiro;

        case 'contas_pagar_resumo':
        case 'contas_pagar_proximos':
        case 'contas_pagar_rankings':
        case 'documento_entrada_resumo':
        case 'documento_entrada_proximos':
        case 'documento_entrada_rankings':
            return $financeiroAmplo;

        case 'comex_importacoes_resumo':
        case 'comex_importacoes_lista':
            return $comex;

        case 'buscar_cliente_documento':
        case 'buscar_cliente_nome':
        case 'vendedor_do_cliente':
        case 'ultima_compra_cliente':
        case 'top_clientes_faturamento':
        case 'faturamento_por_vendedor':
        case 'faturamento_por_supervisor':
        case 'historico_compras_cliente':
        case 'resumo_cliente':
        case 'comparativo_faturado_inadimplente':
        case 'executivo_resumo':
        case 'executivo_tops':
        case 'faturamento_total_empresa':
        case 'faturamento_hoje':
        case 'faturamento_mes_atual':
        case 'faturamento_ano_atual':
        case 'realizado_hoje':
        case 'realizado_mes_atual':
        case 'realizado_ano_atual':
        case 'clientes_dashboard':
        case 'insight_comercial':
        case 'catalogo':
            return $comercial;
    }

    return $comercial;
}

try {
    $body = agent_totvs_read_json_body();
    $params = array_merge($_GET, $_POST, $body);
    $action = trim((string) ($params['action'] ?? 'catalogo'));

    if ($action === '') {
        throw new InvalidArgumentException('Informe a action da consulta.');
    }

    $access = agent_totvs_access_context(agent_totvs_perms_for_action($action));
    $data = TotvsAgentService::execute($action, $params);

    echo json_encode([
        'ok' => true,
        'action' => $action,
        'auth' => $access,
        'data' => $data,
        'generated_at' => date('c'),
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Falha ao processar consulta do agente TOTVS.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
