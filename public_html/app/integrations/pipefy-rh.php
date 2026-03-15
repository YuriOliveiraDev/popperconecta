<?php
declare(strict_types=1);

require_once APP_ROOT . '/app/config/config-pipefy.php';

/**
 * Executa query GraphQL no Pipefy (RH)
 */
function pipefy_graphql_rh(string $query, array $variables = []): array
{
    $payload = json_encode([
        'query' => $query,
        'variables' => $variables,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException('Erro ao gerar payload do Pipefy RH.');
    }

    $ch = curl_init('https://api.pipefy.com/graphql');

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . PIPEFY_TOKEN_RH
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($raw === false) {
        throw new RuntimeException('Erro cURL Pipefy RH: ' . $err);
    }

    $json = json_decode($raw, true);

    if (!is_array($json)) {
        throw new RuntimeException('Resposta inválida do Pipefy RH: ' . $raw);
    }

    if ($http >= 400) {
        $msg = $json['errors'][0]['message'] ?? ('HTTP ' . $http);
        throw new RuntimeException('Erro HTTP Pipefy RH: ' . $msg);
    }

    if (!empty($json['errors'])) {
        $msg = $json['errors'][0]['message'] ?? 'Erro GraphQL Pipefy RH.';
        throw new RuntimeException($msg);
    }

    return $json['data'] ?? [];
}

/**
 * Cria card no pipe de solicitações do RH
 */
function pipefy_create_rh_redemption_card(array $data): array
{
    $mutation = <<<'GQL'
mutation CreateCard($input: CreateCardInput!) {
  createCard(input: $input) {
    card {
      id
      title
      url
    }
  }
}
GQL;

    $variables = [
        'input' => [
            'pipe_id' => PIPE_ID_RH,
            'title' => $data['title'],
            'fields_attributes' => [
                [
                    'field_id' => 'nome_do_solicitante',
                    'field_value' => PIPEFY_RH_SOLICITANTE_ID,
                ],
                [
                    'field_id' => 'tipo_da_solicita_o',
                    'field_value' => 'Trocar Poppercoins',
                ],
                [
                    'field_id' => 'descri_o',
                    'field_value' => $data['descricao'],
                ],
                [
                    'field_id' => 'informa_es_adicionais',
                    'field_value' => $data['informacoes_adicionais'],
                ],
            ],
        ],
    ];

    $result = pipefy_graphql_rh($mutation, $variables);

    $card = $result['createCard']['card'] ?? null;

    if (!is_array($card) || empty($card['id'])) {
        throw new RuntimeException('Pipefy RH não retornou o card criado.');
    }

    return $card;
}