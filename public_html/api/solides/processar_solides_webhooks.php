<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/integrations/solides.php';

header('Content-Type: application/json; charset=utf-8');

function json_response(int $status, array $data): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function only_digits(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $value);
    return ($digits !== '') ? $digits : null;
}

function normalize_name(?string $value): string
{
    $value = trim(mb_strtolower((string) $value, 'UTF-8'));

    $map = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c'
    ];

    $value = strtr($value, $map);
    $value = preg_replace('/\s+/', ' ', $value);

    return trim((string) $value);
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

function extract_colaboradores_list(array $ret): array
{
    if (isset($ret['data']) && is_array($ret['data'])) {
        return $ret['data'];
    }

    if (isset($ret['colaboradores']) && is_array($ret['colaboradores'])) {
        return $ret['colaboradores'];
    }

    if (isset($ret[0])) {
        return $ret;
    }

    return [];
}

function find_colaborador_by_name(string $nomeBusca, int $maxPages = 10, int $pageSize = 100): ?array
{
    $nomeBuscaNorm = normalize_name($nomeBusca);
    if ($nomeBuscaNorm === '') {
        return null;
    }

    $best = null;
    $bestScore = -1;

    for ($page = 1; $page <= $maxPages; $page++) {
        $ret = solides_list_colaboradores($page, $pageSize, 'todos');
        $lista = extract_colaboradores_list($ret);

        if (!$lista) {
            break;
        }

        foreach ($lista as $col) {
            if (!is_array($col)) {
                continue;
            }

            $nome = trim((string) ($col['name'] ?? $col['nome'] ?? ''));
            if ($nome === '') {
                continue;
            }

            $nomeNorm = normalize_name($nome);

            $score = 0;

            if ($nomeNorm === $nomeBuscaNorm) {
                $score = 100;
            } elseif (mb_strpos($nomeNorm, $nomeBuscaNorm) !== false || mb_strpos($nomeBuscaNorm, $nomeNorm) !== false) {
                $score = 70;
            } else {
                similar_text($nomeNorm, $nomeBuscaNorm, $percent);
                $score = (int) round($percent);
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $col;
            }
        }

        if ($bestScore === 100) {
            break;
        }

        if (count($lista) < $pageSize) {
            break;
        }
    }

    // segurança mínima para não pegar nome muito errado
    if ($bestScore < 70) {
        return null;
    }

    return $best;
}

function fetch_pending_webhooks(int $limit = 20): array
{
    $stmt = db()->prepare("
        SELECT
            id,
            acao,
            pesquisa_id,
            respondente_id,
            respondente_nome,
            payload_json,
            processado,
            erro
        FROM solides_webhook_logs
        WHERE processado = 0
          AND acao = 'nova_resposta_pesquisa'
          AND respondente_nome IS NOT NULL
          AND respondente_nome <> ''
        ORDER BY id ASC
        LIMIT :limite
    ");

    $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function mark_webhook_processed(
    int $logId,
    array $colaborador,
    ?string $email,
    ?string $cpf,
    ?string $matricula,
    ?string $status
): void {
    $stmt = db()->prepare("
        UPDATE solides_webhook_logs
        SET
            colaborador_json = CAST(:colaborador_json AS JSON),
            colaborador_email = :colaborador_email,
            colaborador_cpf = :colaborador_cpf,
            colaborador_matricula = :colaborador_matricula,
            colaborador_status = :colaborador_status,
            processado = 1,
            processado_em = NOW(),
            erro = NULL
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':colaborador_json'      => json_encode($colaborador, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ':colaborador_email'     => $email,
        ':colaborador_cpf'       => $cpf,
        ':colaborador_matricula' => $matricula,
        ':colaborador_status'    => $status,
        ':id'                    => $logId,
    ]);
}

function mark_webhook_error(int $logId, string $erro): void
{
    $stmt = db()->prepare("
        UPDATE solides_webhook_logs
        SET erro = :erro
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':erro' => mb_substr($erro, 0, 65535),
        ':id'   => $logId,
    ]);
}

try {
    $logs = fetch_pending_webhooks(20);

    $processados = [];
    $falhas = [];

    foreach ($logs as $log) {
        $logId = (int) ($log['id'] ?? 0);
        $respondenteNome = trim((string) ($log['respondente_nome'] ?? ''));

        try {
            if ($respondenteNome === '') {
                throw new RuntimeException('respondente_nome vazio.');
            }

            $colaborador = find_colaborador_by_name($respondenteNome, 10, 100);

            if (!$colaborador) {
                throw new RuntimeException('Colaborador não encontrado na Solides pelo nome: ' . $respondenteNome);
            }

            $email = trim((string) ($colaborador['email'] ?? '')) ?: null;
            $cpf = only_digits((string) ($colaborador['idNumber'] ?? '')) ?: null;
            $matricula = trim((string) ($colaborador['registration'] ?? '')) ?: null;
            $status = isset($colaborador['active'])
                ? ((bool) $colaborador['active'] ? 'ativo' : 'inativo')
                : null;

            mark_webhook_processed(
                logId: $logId,
                colaborador: $colaborador,
                email: $email,
                cpf: $cpf,
                matricula: $matricula,
                status: $status
            );

            $processados[] = [
                'log_id' => $logId,
                'respondente_nome' => $respondenteNome,
                'colaborador_id' => $colaborador['id'] ?? null,
                'colaborador_nome' => $colaborador['name'] ?? null,
                'email' => $email,
                'cpf' => $cpf,
                'matricula' => $matricula,
                'status' => $status,
            ];
        } catch (Throwable $e) {
            mark_webhook_error($logId, $e->getMessage());

            $falhas[] = [
                'log_id' => $logId,
                'respondente_nome' => $respondenteNome,
                'erro' => $e->getMessage(),
            ];
        }
    }

    json_response(200, [
        'ok' => true,
        'processados' => $processados,
        'falhas' => $falhas,
        'total_lidos' => count($logs),
        'total_processados' => count($processados),
        'total_falhas' => count($falhas),
    ]);
} catch (Throwable $e) {
    json_response(500, [
        'ok' => false,
        'message' => 'Erro ao processar webhooks da Solides.',
        'error' => $e->getMessage(),
    ]);
}