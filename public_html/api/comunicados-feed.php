<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/db.php';

// se o carrossel roda sem login, remova. Se roda logado, mantenha:
require_login();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
  // Pega ativos em ordem
  $stmt = db()->prepare('
    SELECT id, titulo, conteudo, imagem_path, ordem
    FROM comunicados
    WHERE ativo = TRUE
    ORDER BY ordem ASC, id ASC
  ');
  $stmt->execute();
  $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Gera uma versão estável baseada no conteúdo (muda se algo muda)
  $fingerprint = '';
  foreach ($items as $c) {
    $fingerprint .= (int)$c['id'] . '|'
      . (int)($c['ordem'] ?? 0) . '|'
      . (string)($c['titulo'] ?? '') . '|'
      . (string)($c['conteudo'] ?? '') . '|'
      . (string)($c['imagem_path'] ?? '') . '||';
  }
  $version = sha1($fingerprint);

  echo json_encode([
    'ok' => true,
    'version' => $version,
    'items' => $items,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE);
}