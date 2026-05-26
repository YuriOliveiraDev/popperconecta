<?php
// Handshake de validacao da subscription Microsoft Graph
if (isset($_GET['validationToken'])) {
    header('Content-Type: text/plain');
    echo $_GET['validationToken'];
    exit;
}

$body = file_get_contents('php://input');
$data = json_decode($body, true);
$items = $data['value'] ?? [];

if (empty($items)) {
    http_response_code(202);
    exit;
}

$queueFile = __DIR__ . '/queue.json';
$queue = file_exists($queueFile)
    ? (json_decode(file_get_contents($queueFile), true) ?: [])
    : [];

foreach ($items as $n) {
    if (($n['changeType'] ?? '') === 'created') {
        $msgId = $n['resourceData']['id'] ?? null;
        $chatId = '19:9de0587248074ddfbce7247c6f6db579@thread.v2';

        if ($msgId && !in_array($msgId, array_column($queue, 'id'), true)) {
            $queue[] = ['id' => $msgId, 'chatId' => $chatId, 'ts' => time()];
        }
    }
}

// Descarta itens com mais de 5 minutos
$queue = array_values(array_filter($queue, fn($i) => (time() - $i['ts']) < 300));
file_put_contents($queueFile, json_encode($queue));

http_response_code(202);
