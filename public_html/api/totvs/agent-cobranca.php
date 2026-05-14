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

function cobranca_agent_read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }

    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function cobranca_agent_header(string $name): string
{
    $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $value = $_SERVER[$serverKey] ?? '';
    return is_string($value) ? trim($value) : '';
}

function cobranca_agent_access_context(): array
{
    $expectedToken = trim((string) (
        getenv('POPPER_INTERNAL_AGENT_TOKEN')
        ?: (defined('POPPER_INTERNAL_AGENT_TOKEN') ? POPPER_INTERNAL_AGENT_TOKEN : '')
    ));
    $providedToken = cobranca_agent_header('X-Internal-Agent-Token');

    if ($providedToken === '') {
        $authHeader = cobranca_agent_header('Authorization');
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

    if (!user_can('dash.financeiro.inadimplencia', $user)) {
        http_response_code(403);
        echo json_encode([
            'ok' => false,
            'message' => 'Acesso negado.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
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

function cobranca_agent_catalogo(): array
{
    return [
        'acoes' => [
            [
                'action' => 'cobranca_inadimplentes_whatsapp_por_vendedor',
                'descricao' => 'Agrupa clientes inadimplentes por vendedor usando TOTVS 000076 e 000080, retornando telefone, link wa.me e mensagem pronta para WhatsApp.',
                'params' => ['vendedor', 'supervisor', 'search', 'dias_min_atraso', 'valor_min', 'limit_clientes', 'somente_com_telefone', 'force'],
            ],
        ],
    ];
}

try {
    $body = cobranca_agent_read_json_body();
    $params = array_merge($_GET, $_POST, $body);
    $action = trim((string) ($params['action'] ?? 'catalogo'));

    $access = cobranca_agent_access_context();

    if ($action === 'catalogo') {
        $data = cobranca_agent_catalogo();
    } elseif ($action === 'cobranca_inadimplentes_whatsapp_por_vendedor') {
        $data = TotvsAgentService::execute($action, $params);
    } else {
        throw new InvalidArgumentException('Acao invalida para agente de cobranca.');
    }

    echo json_encode([
        'ok' => true,
        'agent' => 'cobranca',
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
        'message' => 'Falha ao processar consulta do agente de cobranca.',
        'detail' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
