<?php
declare(strict_types=1);

/* ===========================
   CATÁLOGOS
=========================== */
require_admin_perm('admin.rh');
// Admin
const ADMIN_PERMISSION_CATALOG = [
  'admin.users'       => ['label' => 'Usuários',     'url' => '/admin/users.php'],
  'admin.comunicados' => ['label' => 'Comunicados',  'url' => '/admin/comunicados.php'],
  'admin.rh'          => ['label' => 'RH',           'url' => '/admin/rh.php'],
  // ...
];

// Dashboards (PERMISSÃO POR PÁGINA)
const DASHBOARD_CATALOG = [
  // Comercial
  'dash.comercial.faturamento' => ['label' => 'Faturamento', 'url' => '/dashboard.php',           'group' => 'Comercial'],
  'dash.comercial.executivo'   => ['label' => 'Executivo',   'url' => '/dashboard-executivo.php', 'group' => 'Comercial'],
  'dash.comercial.insight'     => ['label' => 'Insight',     'url' => '/insight_comercial.php',   'group' => 'Comercial'],
  'dash.comercial.clientes'    => ['label' => 'Clientes',    'url' => '/clientes.php',            'group' => 'Comercial'],

  // Financeiro
  'dash.financeiro.contasp'    => ['label' => 'Contas a Pagar','url' => '/admin/dashboardContasP.php','group' => 'Financeiro'],

  // Comex
  'dash.comex.importacoes'     => ['label' => 'Importações', 'url' => '/importacoes.php',         'group' => 'Comex'],
];

/* ===========================
   HELPERS
=========================== */

function user_perms(?array $u = null): array {
  if ($u === null) {
    if (!function_exists('current_user')) require_once __DIR__ . '/auth.php';
    $u = current_user();
  }
  $raw = $u['permissions'] ?? null;
  if ($raw === null || $raw === '') return [];
  $decoded = json_decode((string)$raw, true);
  if (!is_array($decoded)) return [];
  $out = [];
  foreach ($decoded as $p) if (is_string($p) && $p !== '') $out[] = $p;
  return array_values(array_unique($out));
}

/**
 * Regra recomendada:
 * - admin: pode tudo do ADMIN (se você quiser controlar admin também, removemos o bypass)
 * - dashboards: SEMPRE exige permissão marcada (até para admin, se você quiser)
 *
 * Vou deixar assim:
 *   - admin.* : admin tem bypass
 *   - dash.*  : ninguém tem bypass (tem que marcar)
 */
function user_can(string $perm, ?array $u = null): bool {
  if ($u === null) {
    if (!function_exists('current_user')) require_once __DIR__ . '/auth.php';
    $u = current_user();
  }

  $role = (string)($u['role'] ?? '');
  $perms = user_perms($u);

  // ✅ bypass só para ADMIN.* (opcional)
  if ($role === 'admin' && str_starts_with($perm, 'admin.')) return true;

  return in_array($perm, $perms, true);
}

function require_admin_perm(string $perm): void {
  if (!function_exists('require_login')) require_once __DIR__ . '/auth.php';
  require_login();

  $u = current_user();
  if (($u['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }

  if (!user_can($perm, $u)) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }
}

function require_dash_perm(string $perm): void {
  if (!function_exists('require_login')) require_once __DIR__ . '/auth.php';
  require_login();

  $u = current_user();
  if (!user_can($perm, $u)) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }
}