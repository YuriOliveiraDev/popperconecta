<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../app/db.php';

header('Content-Type: application/json; charset=utf-8');

/**
 * CONFIG
 * Se quiser proteger o endpoint com um header fixo da Solides, use aqui.
 * Exemplo no webhook da Solides:
 *   X-Webhook-Token: popper-solides-2026
 */
const SOLIDES_WEBHOOK_TOKEN = ''; // deixe '' para não validar
const RUN_PIPELINE_AFTER_WEBHOOK = true;

/**
 * Caminho absoluto do pipeline.
 */
const SOLIDES_PIPELINE_FILE = __DIR__ . '/processar_solides_pipeline.php';

function json_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
    );
    exit;
}

function get_header_value(string $name): ?string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
}

function client_ip(): ?string
{
    $candidates = [
        $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
        $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ];

    foreach ($candidates as $value) {
        if (!$value) {
            continue;
        }

        $parts = array_map('trim', explode(',', (string) $value));
        foreach ($parts as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return null;
}

function save_webhook_log(
    ?string $acao,
    ?int $pesquisaId,
    ?int $respondenteId,
    ?string $respondenteNome,
    string $payloadRaw,
    ?string $erro = null
): int {
    $stmt = db()->prepare("
        INSERT INTO solides_webhook_logs
        (
            acao,
            pesquisa_id,
            respondente_id,
            respondente_nome,
            payload_json,
            erro,
            ip_origem,
            user_agent
        )
        VALUES
        (
            :acao,
            :pesquisa_id,
            :respondente_id,
            :respondente_nome,
            CAST(:payload_json AS JSON),
            :erro,
            :ip_origem,
            :user_agent
        )
    ");

    $stmt->execute([
        ':acao'             => $acao ?: 'desconhecida',
        ':pesquisa_id'      => $pesquisaId,
        ':respondente_id'   => $respondenteId,
        ':respondente_nome' => $respondenteNome,
        ':payload_json'     => $payloadRaw,
        ':erro'             => $erro,
        ':ip_origem'        => client_ip(),
        ':user_agent'       => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
    ]);

    return (int) db()->lastInsertId();
}

/**
 * Executa o pipeline logo após gravar o webhook.
 * Não quebra o webhook se o pipeline falhar.
 */
function run_solides_pipeline(): array
{
    if (!RUN_PIPELINE_AFTER_WEBHOOK) {
        return [
            'ran' => false,
            'ok' => false,
            'message' => 'Pipeline desabilitado por configuração.',
        ];
    }

    if (!is_file(SOLIDES_PIPELINE_FILE)) {
        return [
            'ran' => false,
            'ok' => false,
            'message' => 'Arquivo do pipeline não encontrado: ' . SOLIDES_PIPELINE_FILE,
        ];
    }

    try {
        ob_start();
        include SOLIDES_PIPELINE_FILE;
        $output = ob_get_clean();

        $decoded = json_decode((string) $output, true);

        if (is_array($decoded)) {
            return [
                'ran' => true,
                'ok' => (bool) ($decoded['ok'] ?? false),
                'response' => $decoded,
            ];
        }

        return [
            'ran' => true,
            'ok' => false,
            'message' => 'Pipeline executado, mas retornou saída não JSON.',
            'raw_output' => $output,
        ];
    } catch (Throwable $e) {
        if (ob_get_level() > 0) {
            ob_end_clean();
        }

        return [
            'ran' => true,
            'ok' => false,
            'message' => 'Erro ao executar pipeline.',
            'error' => $e->getMessage(),
        ];
    }
}

/**
 * Salva observação de erro/pipeline no log já criado.
 */
function append_log_error(int $logId, string $message): void
{
    $stmt = db()->prepare("
        UPDATE solides_webhook_logs
        SET erro = CASE
            WHEN erro IS NULL OR erro = '' THEN :msg
            ELSE CONCAT(erro, '\n', :msg)
        END
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':msg' => mb_substr($message, 0, 65535),
        ':id'  => $logId,
    ]);
}

/**
 * =========================
 * SEGURANÇA OPCIONAL
 * =========================
 */
if (SOLIDES_WEBHOOK_TOKEN !== '') {
    $receivedToken = get_header_value('X-Webhook-Token');
    if ($receivedToken !== SOLIDES_WEBHOOK_TOKEN) {
        json_response(401, [
            'ok' => false,
            'message' => 'Webhook não autorizado.',
        ]);
    }
}

/**
 * =========================
 * MÉTODO
 * =========================
 */
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    json_response(405, [
        'ok' => false,
        'message' => 'Método não permitido.',
    ]);
}

/**
 * =========================
 * PAYLOAD
 * =========================
 */
$raw = file_get_contents('php://input');

if ($raw === false || trim($raw) === '') {
    json_response(400, [
        'ok' => false,
        'message' => 'Payload vazio.',
    ]);
}

$data = json_decode($raw, true);

if (!is_array($data)) {
    try {
        save_webhook_log(
            'json_invalido',
            null,
            null,
            null,
            json_encode(['raw' => $raw], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'JSON inválido recebido no webhook.'
        );
    } catch (Throwable $e) {
        // não mascarar o erro original
    }

    json_response(400, [
        'ok' => false,
        'message' => 'JSON inválido.',
    ]);
}

/**
 * =========================
 * EXTRAÇÃO
 * =========================
 */
$acao = isset($data['acao']) ? trim((string) $data['acao']) : null;
$dados = (isset($data['dados']) && is_array($data['dados'])) ? $data['dados'] : [];

$pesquisaId = isset($dados['pesquisa_id']) ? (int) $dados['pesquisa_id'] : null;

$respondente = (isset($dados['respondente']) && is_array($dados['respondente']))
    ? $dados['respondente']
    : [];

$respondenteId = isset($respondente['id']) ? (int) $respondente['id'] : null;
$respondenteNome = isset($respondente['nome']) ? trim((string) $respondente['nome']) : null;

/**
 * =========================
 * IGNORA EVENTOS DIFERENTES
 * =========================
 */
if ($acao !== 'nova_resposta_pesquisa') {
    try {
        $logId = save_webhook_log(
            $acao,
            $pesquisaId,
            $respondenteId,
            $respondenteNome,
            $raw,
            'Evento ignorado por não ser nova_resposta_pesquisa.'
        );
    } catch (Throwable $e) {
        json_response(500, [
            'ok' => false,
            'message' => 'Erro ao registrar evento ignorado.',
            'error' => $e->getMessage(),
        ]);
    }

    json_response(200, [
        'ok' => true,
        'message' => 'Evento ignorado com sucesso.',
        'log_id' => $logId,
    ]);
}

/**
 * =========================
 * GRAVA LOG
 * =========================
 */
try {
    $logId = save_webhook_log(
        $acao,
        $pesquisaId,
        $respondenteId,
        $respondenteNome,
        $raw,
        null
    );
} catch (Throwable $e) {
    json_response(500, [
        'ok' => false,
        'message' => 'Erro ao salvar webhook.',
        'error' => $e->getMessage(),
    ]);
}

/**
 * =========================
 * PROCESSA PIPELINE
 * =========================
 */
$pipeline = run_solides_pipeline();

if (($pipeline['ran'] ?? false) && !($pipeline['ok'] ?? false)) {
    $msg = 'Pipeline executado após webhook, mas com falha.';
    if (!empty($pipeline['message'])) {
        $msg .= ' ' . $pipeline['message'];
    }
    if (!empty($pipeline['error'])) {
        $msg .= ' Erro: ' . $pipeline['error'];
    }

    try {
        append_log_error($logId, $msg);
    } catch (Throwable $e) {
        // não quebrar resposta do webhook por isso
    }
}

/**
 * =========================
 * RESPOSTA FINAL
 * =========================
 */
json_response(200, [
    'ok' => true,
    'message' => 'Webhook recebido com sucesso.',
    'log_id' => $logId,
    'pipeline' => $pipeline,
]);