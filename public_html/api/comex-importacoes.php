<?php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/config-pipefy.php';

// ✅ valida sessão SEM HTML
$u = function_exists('current_user') ? current_user() : null;
if (!$u || !is_array($u)) {
  http_response_code(401);
  echo json_encode(['ok'=>false,'error'=>'unauthorized'], JSON_UNESCAPED_UNICODE);
  exit;
}

// ===== CONFIG CACHE =====
const CACHE_DIR = __DIR__ . '/../cache';
const CACHE_TTL = 300; // 5 min
const CACHE_FILE = CACHE_DIR . '/comex-importacoes.json';

$force = (($_GET['force'] ?? '') === '1');

// quantos no máximo você quer puxar (evita travar se tiver milhares)
$max = isset($_GET['max']) ? max(50, min(2000, (int)$_GET['max'])) : 500;

// tamanho do lote por request pro Pipefy
$batch = 50;

function deny(int $code, string $msg, array $extra = []): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>false,'error'=>$msg], $extra), JSON_UNESCAPED_UNICODE);
  exit;
}

function cache_read(): ?array {
  if (!is_file(CACHE_FILE)) return null;
  if (time() - filemtime(CACHE_FILE) > CACHE_TTL) return null;
  $raw = @file_get_contents(CACHE_FILE);
  $json = $raw ? json_decode($raw, true) : null;
  return is_array($json) ? $json : null;
}
function cache_write(array $data): void {
  if (!is_dir(CACHE_DIR)) @mkdir(CACHE_DIR, 0775, true);
  @file_put_contents(CACHE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function pipefy_graphql(string $query, array $variables): array {
  $url = 'https://api.pipefy.com/graphql';
  $payload = json_encode(['query'=>$query,'variables'=>$variables], JSON_UNESCAPED_UNICODE);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => [
      'Content-Type: application/json',
      'Authorization: Bearer ' . PIPEFY_TOKEN,
    ],
    CURLOPT_TIMEOUT => 30,
  ]);

  $raw = curl_exec($ch);
  $err = curl_error($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($raw === false) deny(500, 'curl_error', ['detail'=>$err]);

  $json = json_decode($raw, true);
  if (!is_array($json)) deny(500, 'invalid_json_from_pipefy', ['raw'=>$raw]);
  if ($http >= 400) deny(500, 'pipefy_http_error', ['http'=>$http,'raw'=>$raw]);
  if (!empty($json['errors'])) deny(500, 'pipefy_graphql_error', ['errors'=>$json['errors']]);

  return $json['data'] ?? [];
}

function field_map(array $fields): array {
  $map = [];
  foreach ($fields as $f) {
    $name = trim((string)($f['name'] ?? ''));
    if ($name === '') continue;
    $map[mb_strtolower($name)] = $f['value'] ?? null;
  }
  return $map;
}
function get_field(array $map, string $name): ?string {
  $k = mb_strtolower($name);
  if (!array_key_exists($k, $map)) return null;
  $v = $map[$k];
  return $v !== null ? (string)$v : null;
}

// ===== CACHE =====
if (!$force) {
  $cached = cache_read();
  if ($cached) {
    echo json_encode($cached, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
  }
}

// ===== QUERY =====
$query = <<<'GQL'
query($pipeId: ID!, $first: Int!, $after: String) {
  cards(pipe_id: $pipeId, first: $first, after: $after) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        title
        current_phase { name }
        fields { name value }
      }
    }
  }
}
GQL;

// ===== PAGINAÇÃO =====
$items = [];
$after = null;
$lastPageInfo = ['hasNextPage'=>false,'endCursor'=>null];

while (count($items) < $max) {
  $data = pipefy_graphql($query, [
    'pipeId' => (string)PIPE_ID,
    'first'  => $batch,
    'after'  => $after,
  ]);

  $edges = $data['cards']['edges'] ?? [];
  $pageInfo = $data['cards']['pageInfo'] ?? ['hasNextPage'=>false,'endCursor'=>null];
  $lastPageInfo = $pageInfo;

  foreach ($edges as $edge) {
    $node = $edge['node'] ?? [];
    $map  = field_map($node['fields'] ?? []);

    $items[] = [
      'card_id' => $node['id'] ?? null,
      'container' => $node['title'] ?? null, // ✅ container = título
      'fase' => $node['current_phase']['name'] ?? null,
      'previsao_embarque_etd' => get_field($map, 'ETD (Previsão de saída)'),
      'previsao_entrega_eta'  => get_field($map, 'ETA  (Previsão de chegada no porto)'),
    ];

    if (count($items) >= $max) break;
  }

  if (empty($pageInfo['hasNextPage'])) break;
  $after = $pageInfo['endCursor'] ?? null;
  if (!$after) break;
}

$out = [
  'ok' => true,
  'pipe_id' => PIPE_ID,
  'total' => count($items),
  'pageInfo' => $lastPageInfo,
  'items' => $items,
  'cached_at' => date('c'),
];

cache_write($out);
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);