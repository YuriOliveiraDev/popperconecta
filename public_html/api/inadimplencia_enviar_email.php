<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/config/config.php';
require_once APP_ROOT . '/app/integrations/mail_graph.php';

header('Content-Type: application/json; charset=utf-8');

function send_mail_graph_multi(array $to, array $cc, string $subject, string $html): array
{
    $token = graph_get_token();

    $url = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode(GRAPH_SENDER_EMAIL) . '/sendMail';

    $toRecipients = array_map(
        static fn(string $email): array => [
            'emailAddress' => [
                'address' => $email,
            ],
        ],
        $to
    );

    $ccRecipients = array_map(
        static fn(string $email): array => [
            'emailAddress' => [
                'address' => $email,
            ],
        ],
        $cc
    );

    $payload = [
        'message' => [
            'subject' => $subject,
            'body' => [
                'contentType' => 'HTML',
                'content' => $html,
            ],
            'toRecipients' => $toRecipients,
            'ccRecipients' => $ccRecipients,
        ],
        'saveToSentItems' => true,
    ];

    $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($jsonPayload === false) {
        throw new RuntimeException('Falha ao montar payload do e-mail.');
    }

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
        throw new RuntimeException('Falha ao enviar e-mail via Graph (HTTP ' . $httpCode . '): ' . $response);
    }

    return [
        'http_code' => $httpCode,
        'response' => $response,
        'decoded' => $decoded,
    ];
}

try {
    require_login();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'message' => 'Método não permitido.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);

    if (!is_array($data)) {
        throw new RuntimeException('Payload inválido.');
    }

    $para = array_values(array_filter(array_map(
        static fn($v): string => trim((string) $v),
        (array) ($data['para'] ?? [])
    )));

    $cc = array_values(array_filter(array_map(
        static fn($v): string => trim((string) $v),
        (array) ($data['cc'] ?? [])
    )));

    $assunto = trim((string) ($data['assunto'] ?? 'Aviso de inadimplência'));
    $html = (string) ($data['html'] ?? '');

    if ($html === '') {
        throw new RuntimeException('Conteúdo do e-mail não informado.');
    }

    if (!$para) {
        throw new RuntimeException('Informe ao menos um destinatário.');
    }

    $emails = array_merge($para, $cc);
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('E-mail inválido: ' . $email);
        }
    }

    $result = send_mail_graph_multi($para, $cc, $assunto, $html);

    echo json_encode([
        'ok' => true,
        'message' => 'E-mail enviado com sucesso.',
        'graph_http_code' => $result['http_code'] ?? null,
        'sender' => defined('GRAPH_SENDER_EMAIL') ? GRAPH_SENDER_EMAIL : null,
    ], JSON_UNESCAPED_UNICODE);
    exit;

} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}