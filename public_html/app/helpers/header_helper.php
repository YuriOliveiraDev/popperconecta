<?php
declare(strict_types=1);

if (!function_exists('header_e')) {
    function header_e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('header_user_value')) {
    function header_user_value(array|string|null $user, string $key, string $default = ''): string
    {
        return (is_array($user) && isset($user[$key]) && is_string($user[$key]))
            ? trim((string) $user[$key])
            : $default;
    }
}

if (!function_exists('header_build_initials')) {
    function header_build_initials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name) ?: [];
        $first = strtoupper(substr((string) ($parts[0] ?? 'U'), 0, 1));
        $last = count($parts) > 1 ? strtoupper(substr((string) end($parts), 0, 1)) : '';

        return $first . $last;
    }
}

if (!function_exists('header_get_greeting')) {
    function header_get_greeting(): string
    {
        $hour = (int) date('H');

        if ($hour >= 5 && $hour < 12) {
            return 'Ótimo Dia';
        }

        if ($hour >= 12 && $hour < 18) {
            return 'Ótima Tarde';
        }

        return 'Ótima Noite';
    }
}

if (!function_exists('header_normalize_asset_path')) {
    function header_normalize_asset_path(string $path): string
    {
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('header_build_notification_href')) {
function header_build_notification_href(string $link): array
{
    $link = trim($link);

    if ($link === '') {
        return [
            'href' => '#',
            'clickable' => false,
        ];
    }

    // normaliza barras
    $link = str_replace('\\', '/', $link);

    // CORREÇÃO ESPECÍFICA RH
    if ($link === '/rh_redemptions.php' || $link === 'rh_redemptions.php') {
        $link = '/admin/rh/rh_redemptions.php';
    }

    // garante barra inicial
    if ($link[0] !== '/') {
        $link = '/' . $link;
    }

    return [
        'href' => $link,
        'clickable' => true,
    ];
}
}

if (!function_exists('header_get_admin_items')) {
    function header_get_admin_items(array $user): array
    {
        $items = [];

        if (($user['role'] ?? '') !== 'admin') {
            return $items;
        }

        foreach (ADMIN_PERMISSION_CATALOG as $perm => $meta) {
            if (!user_can($perm, $user)) {
                continue;
            }

            $items[] = [
                'url'   => (string) ($meta['url'] ?? '#'),
                'label' => (string) ($meta['label'] ?? $perm),
                'icon'  => (string) ($meta['icon'] ?? ''),
            ];
        }

        return $items;
    }
}

if (!function_exists('header_get_dashboard_groups')) {
    function header_get_dashboard_groups(array $user): array
    {
        $groups = [];

        $comercial = [];
        if (user_can('dash.comercial.faturamento', $user)) {
            $comercial[] = ['label' => 'Faturamento', 'url' => '/dashboards/faturamento.php'];
        }
        if (user_can('dash.comercial.executivo', $user)) {
            $comercial[] = ['label' => 'Executivo', 'url' => '/dashboards/dashboard-executivo.php'];
        }
        if (user_can('dash.comercial.insight', $user)) {
            $comercial[] = ['label' => 'Insight', 'url' => '/dashboards/insight_comercial.php'];
        }
        if (user_can('dash.comercial.clientes', $user)) {
            $comercial[] = ['label' => 'Clientes', 'url' => '/dashboards/clientes.php'];
        }
        if ($comercial) {
            $groups[] = [
                'label' => 'Comercial',
                'items' => $comercial,
            ];
        }

        $financeiro = [];
        if (user_can('dash.financeiro.contasp', $user)) {
            $financeiro[] = ['label' => 'Contas a Pagar', 'url' => '/dashboards/dashboardContasP.php'];
        }
        if ($financeiro) {
            $groups[] = [
                'label' => 'Financeiro',
                'items' => $financeiro,
            ];
        }

        $comex = [];
        if (user_can('dash.comex.importacoes', $user)) {
            $comex[] = ['label' => 'Importações', 'url' => '/dashboards/importacoes.php'];
        }
        if ($comex) {
            $groups[] = [
                'label' => 'Comex',
                'items' => $comex,
            ];
        }

        return $groups;
    }
}