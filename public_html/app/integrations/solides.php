<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

const SOLIDES_BASE_URL = 'https://app.solides.com/{locale}/api/v1';
const SOLIDES_LOCALE   = 'pt-BR';
const SOLIDES_TOKEN    = 'ead350b9100ebb4ea205ea936318265bae165580984148ba0daa';

function solides_base_url(): string
{
    return str_replace('{locale}', SOLIDES_LOCALE, SOLIDES_BASE_URL);
}

function solides_headers(): array
{
    return [
        'Authorization: Token token=' . SOLIDES_TOKEN,
        'Accept: application/json',
        'Content-Type: application/json',
    ];
}

function solides_request(string $method, string $path, ?array $payload = null): array
{
    $url = rtrim(solides_base_url(), '/') . '/' . ltrim($path, '/');

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => solides_headers(),
        CURLOPT_TIMEOUT        => 30,
    ]);

    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($raw === false || $err) {
        throw new RuntimeException('Erro cURL Solides: ' . $err);
    }

    if ($http >= 400) {
        throw new RuntimeException('Solides HTTP ' . $http . ': ' . $raw);
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        throw new RuntimeException('Resposta inválida da Solides.');
    }

    return $data;
}