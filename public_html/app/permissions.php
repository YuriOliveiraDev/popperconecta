<?php
declare(strict_types=1);

/* ===========================
   CATÁLOGOS
=========================== */

// Admin
const ADMIN_PERMISSION_CATALOG = [
  'admin.users'        => ['label' => 'Usuários',     'url' => '/admin/users.php',        'icon' => '👤'],
  'admin.comunicados'  => ['label' => 'Comunicados',  'url' => '/admin/comunicados.php',  'icon' => '📢'],
  'admin.rh'           => ['label' => 'RH',           'url' => '/admin/rh.php',           'icon' => '🧑‍💼'],
  'admin.metrics'      => ['label' => 'Metrics',      'url' => '/admin/metrics.php',      'icon' => '📊'],
];

// Dashboards
const DASHBOARD_CATALOG = [
  'dash.comercial.faturamento' => ['label' => 'Faturamento',    'url' => '/faturamento.php',           'group' => 'Comercial'],
  'dash.comercial.executivo'   => ['label' => 'Executivo',      'url' => '/dashboard-executivo.php',   'group' => 'Comercial'],
  'dash.comercial.insight'     => ['label' => 'Insight',        'url' => '/insight_comercial.php',     'group' => 'Comercial'],
  'dash.comercial.clientes'    => ['label' => 'Clientes',       'url' => '/clientes.php',              'group' => 'Comercial'],

  'dash.financeiro.contasp'    => ['label' => 'Contas a Pagar', 'url' => '/admin/dashboardContasP.php','group' => 'Financeiro'],

  'dash.comex.importacoes'     => ['label' => 'Importações',    'url' => '/importacoes.php',           'group' => 'Comex'],
];

/* ===========================
   HELPERS
=========================== */

function user_perms(?array $u = null): array
{
  if ($u === null) {
    if (!function_exists('current_user')) {
      require_once __DIR__ . '/auth.php';
    }
    $u = current_user();
  }

  if (!is_array($u)) {
    return [];
  }

  $raw = $u['permissions'] ?? null;

  if ($raw === null || $raw === '') {
    return [];
  }

  $items = [];

  // Caso 1: já veio como array PHP
  if (is_array($raw)) {
    $items = $raw;
  }
  // Caso 2: veio string JSON ou string simples
  elseif (is_string($raw)) {
    $raw = trim($raw);

    if ($raw === '') {
      return [];
    }

    $decoded = json_decode($raw, true);

    if (json_last_error() === JSON_ERROR_NONE) {
      $items = $decoded;
    } else {
      // fallback: string única
      $items = [$raw];
    }
  } else {
    return [];
  }

  $out = [];

  // Array simples: ['admin.users', 'admin.metrics']
  if (array_values($items) === $items) {
    foreach ($items as $p) {
      if (is_string($p) && trim($p) !== '') {
        $out[] = trim($p);
      }
    }
  } else {
    // Array associativo: ['admin.metrics' => true]
    foreach ($items as $perm => $enabled) {
      if (
        is_string($perm) &&
        trim($perm) !== '' &&
        (
          $enabled === true ||
          $enabled === 1 ||
          $enabled === '1' ||
          $enabled === 'true' ||
          $enabled === 'on'
        )
      ) {
        $out[] = trim($perm);
      }
    }
  }

  return array_values(array_unique($out));
}

function user_can(string $perm, ?array $u = null): bool
{
  if ($u === null) {
    if (!function_exists('current_user')) {
      require_once __DIR__ . '/auth.php';
    }
    $u = current_user();
  }

  if (!is_array($u)) {
    return false;
  }

  $perms = user_perms($u);

  return in_array($perm, $perms, true);
}

function require_admin_perm(string $perm): void
{
  if (!function_exists('require_login')) {
    require_once __DIR__ . '/auth.php';
  }

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

function require_dash_perm(string $perm): void
{
  if (!function_exists('require_login')) {
    require_once __DIR__ . '/auth.php';
  }

  require_login();

  $u = current_user();

  if (!user_can($perm, $u)) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit;
  }
}