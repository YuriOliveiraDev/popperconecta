<?php
declare(strict_types=1);

if (!defined('CORPORATE_LANDING_DIR')) {
    define('CORPORATE_LANDING_DIR', APP_ROOT . '/uploads/landing');
}

if (!defined('CORPORATE_LANDING_FILE')) {
    define('CORPORATE_LANDING_FILE', CORPORATE_LANDING_DIR . '/index2.json');
}

function corporate_landing_default_config(): array
{
    return [
        'hero' => [
            'badge' => 'Portal Corporativo',
            'title' => 'Tudo o que importa no m&ecirc;s, em uma p&aacute;gina s&oacute;.',
            'subtitle' => 'Uma landing corporativa para acompanhar o Popper News, avisos internos, aniversariantes e acessos r&aacute;pidos com uma navega&ccedil;&atilde;o mais elegante e objetiva.',
        ],
        'popper_news' => [
            'eyebrow' => 'Popper News',
            'title' => 'Edi&ccedil;&atilde;o atual dispon&iacute;vel para consulta',
            'summary' => 'Acompanhe a edi&ccedil;&atilde;o do m&ecirc;s com uma apresenta&ccedil;&atilde;o visual mais organizada e um acesso centralizado ao conte&uacute;do oficial.',
        ],
        'notices' => [
            'title' => 'Painel de avisos',
            'subtitle' => 'Comunicados importantes, lembretes internos e destaques do momento.',
            'items' => [],
        ],
        'house_anniversaries' => [
            'title' => 'Aniversariantes de casa',
            'subtitle' => 'Reconhecimentos e marcos internos destacados para o m&ecirc;s atual.',
            'items' => [],
        ],
        'quick_links' => [
            'title' => 'Acessos r&aacute;pidos',
            'subtitle' => 'Atalhos &uacute;teis para o dia a dia do time.',
            'items' => [
                ['label' => 'In&iacute;cio', 'url' => '/index.php', 'description' => 'P&aacute;gina principal do portal'],
                ['label' => 'Meus dados', 'url' => '/me.php', 'description' => 'Perfil, foto e dados pessoais'],
                ['label' => 'TV', 'url' => '/tv.php', 'description' => 'Vis&atilde;o em tela ampliada'],
                ['label' => 'Comunicados', 'url' => '/admin/comunicados.php', 'description' => 'Gest&atilde;o de comunicados'],
            ],
        ],
    ];
}

function corporate_landing_storage_file(): string
{
    return CORPORATE_LANDING_FILE;
}

function corporate_landing_ensure_dir(): void
{
    $dir = CORPORATE_LANDING_DIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('N&atilde;o foi poss&iacute;vel preparar a pasta da landing.');
    }
}

function corporate_landing_load(): array
{
    $default = corporate_landing_default_config();
    $file = corporate_landing_storage_file();

    if (!is_file($file)) {
        return $default;
    }

    $raw = @file_get_contents($file);
    if (!is_string($raw) || trim($raw) === '') {
        return $default;
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return $default;
    }

    if (!isset($decoded['notices']) && isset($decoded['calendar']) && is_array($decoded['calendar'])) {
        $calendar = $decoded['calendar'];
        $decoded['notices'] = [
            'title' => (string)($calendar['title'] ?? 'Painel de avisos'),
            'subtitle' => (string)($calendar['subtitle'] ?? ''),
            'items' => array_map(
                static function (array $event): array {
                    return [
                        'label' => trim((string)($event['date'] ?? '')),
                        'title' => trim((string)($event['title'] ?? '')),
                        'detail' => trim((string)($event['detail'] ?? '')),
                    ];
                },
                array_values(array_filter((array)($calendar['events'] ?? []), 'is_array'))
            ),
        ];
    }

    return corporate_landing_merge_config($default, $decoded);
}

function corporate_landing_merge_config(array $base, array $override): array
{
    foreach ($override as $key => $value) {
        if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
            $base[$key] = corporate_landing_merge_config($base[$key], $value);
            continue;
        }

        $base[$key] = $value;
    }

    return $base;
}

function corporate_landing_save(array $config): void
{
    corporate_landing_ensure_dir();

    $ok = @file_put_contents(
        corporate_landing_storage_file(),
        json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );

    if ($ok === false) {
        throw new RuntimeException('N&atilde;o foi poss&iacute;vel salvar as configura&ccedil;&otilde;es da landing.');
    }
}

function corporate_landing_parse_structured_lines(string $text, array $keys): array
{
    $lines = preg_split('/\r\n|\r|\n/', trim($text)) ?: [];
    $items = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $parts = array_map('trim', explode('|', $line));
        $item = [];

        foreach ($keys as $idx => $key) {
            $item[$key] = (string)($parts[$idx] ?? '');
        }

        $items[] = $item;
    }

    return $items;
}

function corporate_landing_format_structured_lines(array $items, array $keys): string
{
    $lines = [];

    foreach ($items as $item) {
        $parts = [];
        foreach ($keys as $key) {
            $parts[] = trim((string)($item[$key] ?? ''));
        }
        $lines[] = implode(' | ', $parts);
    }

    return implode("\n", $lines);
}
