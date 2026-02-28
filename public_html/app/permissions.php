<?php
declare(strict_types=1);

/**
 * Catálogo de permissões (central).
 * Quando surgir uma nova área no Admin, adicione aqui.
 */
const PERMISSION_CATALOG = [
  'admin.users'        => ['label' => 'Usuários',      'icon' => '', 'url' => '/admin/users.php'],
  'admin.metrics'      => ['label' => 'Métricas',      'icon' => '', 'url' => '/admin/metrics.php'],
  'admin.comunicados'  => ['label' => 'Comunicados',   'icon' => '', 'url' => '/admin/comunicados.php'],

  // RH (página “hub”)
  'admin.rh'           => ['label' => 'RH',            'icon' => '', 'url' => '/admin/rh.php'],

];

/**
 * Retorna permissões do usuário como array.
 */
function user_perms(?array $u = null): array {
  if ($u === null) {
    if (!function_exists('current_user')) {
      require_once __DIR__ . '/auth.php';
    }
    $u = current_user();
  }

  $raw = $u['permissions'] ?? null;
  if ($raw === null || $raw === '') return [];

  // JSON pode vir como string (TEXT/JSON). Decodifica.
  $decoded = json_decode((string)$raw, true);
  if (!is_array($decoded)) return [];

  // Normaliza para strings únicas
  $perms = [];
  foreach ($decoded as $p) {
    if (is_string($p) && $p !== '') $perms[] = $p;
  }
  $perms = array_values(array_unique($perms));

  return $perms;
}

/**
 * Checa se o usuário pode acessar uma permissão.
 * Observação importante:
 * - Você pediu “selecionar o que o usuário adm pode acessar”.
 * - Então aqui eu mantenho a regra: SOMENTE role=admin pode usar permissões do admin.
 */
function user_can(string $perm, ?array $u = null): bool {
  if ($u === null) {
    if (!function_exists('current_user')) require_once __DIR__ . '/auth.php';
    $u = current_user();
  }

  $role = (string)($u['role'] ?? '');
  if ($role !== 'admin') return false;

  $perms = user_perms($u);
  return in_array($perm, $perms, true);
}

/**
 * Exige permissão (para bloquear página).
 * Use no topo de páginas /admin específicas.
 */
function require_perm(string $perm): void {
  if (!function_exists('require_admin')) {
    require_once __DIR__ . '/auth.php';
  }
  require_admin();

  $u = current_user();
  if (!user_can($perm, $u)) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }
}