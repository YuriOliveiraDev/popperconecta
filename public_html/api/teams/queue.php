<?php
define('QUEUE_SECRET', 'tq_k9xmP3vL8qN2wR5j7cB4hA');

header('Content-Type: application/json');

if (($_SERVER['HTTP_X_QUEUE_SECRET'] ?? '') !== QUEUE_SECRET) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$queueFile = __DIR__ . '/queue.json';

if (isset($_GET['clear'])) {
    file_put_contents($queueFile, json_encode([]));
    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(
    file_exists($queueFile)
        ? (json_decode(file_get_contents($queueFile), true) ?: [])
        : []
);
