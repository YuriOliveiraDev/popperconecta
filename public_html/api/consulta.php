<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$TOKEN = getenv('POPPER_API_TOKEN') ?: 'troque_isto';
if (($_SERVER['HTTP_X_TOKEN'] ?? '') !== $TOKEN) {
  http_response_code(401);
  echo json_encode(['error' => 'unauthorized']);
  exit;
}

$consulta = preg_replace('/\D/', '', $_GET['id'] ?? '');
if ($consulta === '') {
  http_response_code(400);
  echo json_encode(['error' => 'missing id']);
  exit;
}
$consulta = str_pad($consulta, 6, '0', STR_PAD_LEFT);

// (Opcional) cache 10 min por consulta + querystring
$cacheDir = __DIR__ . '/../../cache/totvs';
@mkdir($cacheDir, 0775, true);

$cacheKey = md5($consulta . '|' . ($_SERVER['QUERY_STRING'] ?? ''));
$cacheFile = $cacheDir . "/{$cacheKey}.json";
$ttl = 600; // 10 min

if (is_file($cacheFile) && (time() - filemtime($cacheFile) < $ttl)) {
  echo file_get_contents($cacheFile);
  exit;
}

// Monta URL TOTVS
$base = 'https://tuffloglogistica122016.protheus.cloudtotvs.com.br:4050';
$url  = $base . "/api/wscmrelaut/v1/Consulta/{$consulta}";

// Credenciais TOTVS (não deixar hardcoded se puder)
$user = getenv('TOTVS_API_USER') ?: 'YURI.YANG';
$pass = getenv('TOTVS_API_PASS') ?: 'Tufflog@2026';

if ($pass === '') {
  http_response_code(500);
  echo json_encode(['error' => 'TOTVS_API_PASS not set']);
  exit;
}

$ch = curl_init($url);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_USERPWD        => "{$user}:{$pass}",
  CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
  CURLOPT_HTTPHEADER     => ['Accept: application/json'],
  CURLOPT_TIMEOUT        => 25,
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_SSL_VERIFYPEER => true, // se der erro de certificado, mude pra false
]);

$body = curl_exec($ch);
$err  = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($body === false) {
  http_response_code(502);
  echo json_encode(['error' => 'totvs_request_failed', 'detail' => $err]);
  exit;
}

if ($http >= 200 && $http < 300) {
  file_put_contents($cacheFile, $body);
}

http_response_code($http ?: 502);
echo $body;