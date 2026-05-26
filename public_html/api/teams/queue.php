<?php
define('QUEUE_SECRET', 'tq_k9xmP3vL8qN2wR5j7cB4hA');

header('Content-Type: application/json');

if (($_SERVER['HTTP_X_QUEUE_SECRET'] ?? '') !== QUEUE_SECRET) {
    http_response_code(403);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$queueFile = __DIR__ . '/queue.json';

if ($method === 'GET') {
    echo json_encode(
        file_exists($queueFile)
            ? (json_decode(file_get_contents($queueFile), true) ?: [])
            : []
    );
    exit;
}

if ($method === 'DELETE') {
    if (file_put_contents($queueFile, '[]') === false) {
        http_response_code(500);
        echo json_encode(['error' => 'write_failed']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'method_not_allowed']);
