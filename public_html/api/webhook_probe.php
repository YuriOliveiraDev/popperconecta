<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$dir = __DIR__ . '/../logs';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

$file = $dir . '/webhook_probe.log';

$headers = [];
foreach ($_SERVER as $k => $v) {
    if (strpos($k, 'HTTP_') === 0) {
        $headers[$k] = $v;
    }
}

$entry = [
    'data' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'] ?? '',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
    'uri' => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'body' => file_get_contents('php://input'),
];

file_put_contents(
    $file,
    json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
    FILE_APPEND
);

header('Content-Type: application/json; charset=utf-8');
http_response_code(200);
echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);