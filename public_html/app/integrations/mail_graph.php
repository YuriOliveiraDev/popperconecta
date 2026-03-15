<?php
declare(strict_types=1);

require_once APP_ROOT . '/app/config/config.php';

function graph_get_token(): string
{
    $url = 'https://login.microsoftonline.com/' . rawurlencode(GRAPH_TENANT_ID) . '/oauth2/v2.0/token';

    $data = http_build_query([
        'client_id' => GRAPH_CLIENT_ID,
        'client_secret' => GRAPH_CLIENT_SECRET,
        'scope' => 'https://graph.microsoft.com/.default',
        'grant_type' => 'client_credentials',
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Erro no cURL ao obter token: ' . $err);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($response, true);

    if (!is_array($json)) {
        throw new RuntimeException('Resposta inválida ao obter token: ' . $response);
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $json['error_description'] ?? $json['error'] ?? $response;
        throw new RuntimeException('Falha ao obter token Graph (HTTP ' . $httpCode . '): ' . $msg);
    }

    if (empty($json['access_token'])) {
        throw new RuntimeException('Resposta sem access_token: ' . $response);
    }

    return (string) $json['access_token'];
}

function send_mail_graph(string $to, string $subject, string $html): array
{
    $token = graph_get_token();

    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode(GRAPH_SENDER_EMAIL) . '/sendMail';

    $payload = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $html,
            ],
            'toRecipients' => [
                [
                    'emailAddress' => [
                        'address' => $to,
                    ],
                ],
            ],
        ],
        'saveToSentItems' => true,
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => $jsonPayload,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('Erro no cURL ao enviar e-mail: ' . $err);
    }

    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException('Falha ao enviar e-mail (HTTP ' . $httpCode . '): ' . $response);
    }

    return [
        'http_code' => $httpCode,
        'response' => $response,
        'decoded' => $decoded,
    ];
}