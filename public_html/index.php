<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/services/popper_news.php';
require_once APP_ROOT . '/app/services/corporate_landing.php';
require_once APP_ROOT . '/app/helpers/header_helper.php';

require_login();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$activePage = 'home';
$isAdmin = (($u['role'] ?? '') === 'admin');
$userName = header_user_value($u, 'name', 'Usuário');
$mobileAvatarUrl = header_normalize_asset_path(header_user_value($u, 'profile_photo_path'));
$mobileInitials = header_build_initials($userName);
$mobileAdminItems = is_array($u) ? header_get_admin_items($u) : [];
$mobileDashboardGroups = is_array($u) ? header_get_dashboard_groups($u) : [];
$mobileCoinsMenu = [
  ['label' => 'Meus Popper Coins', 'url' => '/coins/coins.php'],
  ['label' => 'Loja', 'url' => '/coins/coins_resgatar.php'],
  ['label' => 'Ranking', 'url' => '/coins/ranking.php'],
  ['label' => 'Campanhas', 'url' => '/coins/coins_campanhas.php'],
];
$popperNewsPdfUrl = popper_news_public_url();
$popperNewsImageFiles = glob(APP_ROOT . '/uploads/popper-news/current/*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [];
natsort($popperNewsImageFiles);
$popperNewsImageUrls = array_map(
  static fn(string $path): string => '/uploads/popper-news/current/' . rawurlencode(basename($path)),
  array_values($popperNewsImageFiles)
);
$success = (isset($_GET['saved']) && $_GET['saved'] === '1')
  ? html_entity_decode('Se&ccedil;&atilde;o atualizada com sucesso.', ENT_QUOTES, 'UTF-8')
  : '';
$error = '';

function h(?string $value): string
{
  return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function month_name_pt(int $month): string
{
  $months = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => html_entity_decode('Mar&ccedil;o', ENT_QUOTES, 'UTF-8'),
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro',
  ];

  return $months[$month] ?? '';
}

function format_birth_date_short(?string $date): string
{
  $date = trim((string) $date);
  if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return '';
  }

  [$year, $month, $day] = explode('-', $date);
  return sprintf('%02d/%02d', (int) $day, (int) $month);
}

function years_since_date(?string $date, DateTimeImmutable $now): string
{
  $date = trim((string) $date);
  if ($date === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    return '';
  }

  try {
    $start = new DateTimeImmutable($date, $now->getTimezone());
  } catch (Throwable $e) {
    return '';
  }

  $years = (int) $now->format('Y') - (int) $start->format('Y');
  return $years > 0 ? (string) $years : '';
}

function normalize_quick_links(array $items, bool $isAdmin): array
{
  $normalized = [];

  foreach ($items as $item) {
    if (!is_array($item)) {
      continue;
    }

    $label = trim((string) ($item['label'] ?? ''));
    $url = trim((string) ($item['url'] ?? ''));
    $description = trim((string) ($item['description'] ?? ''));

    if ($label === '' || $url === '') {
      continue;
    }

    if (!$isAdmin && substr($url, 0, 7) === '/admin/') {
      continue;
    }

    $normalized[] = [
      'label' => $label,
      'url' => $url,
      'description' => $description !== '' ? $description : html_entity_decode('Acesso r&aacute;pido ao m&oacute;dulo', ENT_QUOTES, 'UTF-8'),
    ];
  }

  return $normalized;
}

function normalize_house_anniversaries(array $items): array
{
  $normalized = [];

  foreach ($items as $item) {
    if (!is_array($item)) {
      continue;
    }

    $name = trim((string) ($item['name'] ?? ''));
    $date = trim((string) ($item['date'] ?? ''));
    $years = trim((string) ($item['years'] ?? ''));
    $detail = trim((string) ($item['detail'] ?? ''));

    if ($name === '') {
      continue;
    }

    $normalized[] = [
      'name' => $name,
      'date' => $date,
      'years' => $years,
      'detail' => $detail,
    ];
  }

  usort($normalized, static function (array $a, array $b): int {
    return strcmp((string) $a['date'], (string) $b['date']);
  });

  return $normalized;
}

function normalize_notice_items(array $items): array
{
  $normalized = [];

  foreach ($items as $item) {
    if (!is_array($item)) {
      continue;
    }

    $label = trim((string) ($item['label'] ?? ''));
    $title = trim((string) ($item['title'] ?? ''));
    $detail = trim((string) ($item['detail'] ?? ''));

    if ($title === '') {
      continue;
    }

    $normalized[] = [
      'label' => $label,
      'title' => $title,
      'detail' => $detail,
    ];
  }

  return $normalized;
}

function post_rows(string $prefix, array $fields): array
{
  $columns = [];
  foreach ($fields as $field) {
    $value = $_POST[$prefix . '_' . $field] ?? [];
    $columns[$field] = is_array($value) ? array_values($value) : [];
  }

  $max = 0;
  foreach ($columns as $values) {
    $max = max($max, count($values));
  }

  $rows = [];
  for ($i = 0; $i < $max; $i++) {
    $row = [];
    foreach ($fields as $field) {
      $row[$field] = trim((string) ($columns[$field][$i] ?? ''));
    }
    $rows[] = $row;
  }

  return $rows;
}

$config = corporate_landing_load();

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  if (!$isAdmin) {
    http_response_code(403);
    exit('Acesso negado.');
  }

  try {
    $section = trim((string) ($_POST['section'] ?? ''));
    $updated = corporate_landing_load();

    switch ($section) {
      case 'hero':
        $updated['hero']['badge'] = trim((string) ($_POST['badge'] ?? ''));
        $updated['hero']['title'] = trim((string) ($_POST['title'] ?? ''));
        $updated['hero']['subtitle'] = trim((string) ($_POST['subtitle'] ?? ''));
        break;

      case 'popper_news':
        $updated['popper_news']['eyebrow'] = trim((string) ($_POST['eyebrow'] ?? ''));
        $updated['popper_news']['title'] = trim((string) ($_POST['title'] ?? ''));
        $updated['popper_news']['summary'] = trim((string) ($_POST['summary'] ?? ''));
        break;

      case 'notices':
        $updated['notices']['title'] = trim((string) ($_POST['title'] ?? ''));
        $updated['notices']['subtitle'] = trim((string) ($_POST['subtitle'] ?? ''));
        $updated['notices']['items'] = array_values(array_filter(
          post_rows('notice_item', ['label', 'title', 'detail']),
          static fn(array $row): bool => $row['title'] !== ''
        ));
        break;

      case 'quick_links':
        $updated['quick_links']['title'] = trim((string) ($_POST['title'] ?? ''));
        $updated['quick_links']['subtitle'] = trim((string) ($_POST['subtitle'] ?? ''));
        $updated['quick_links']['items'] = array_values(array_filter(
          post_rows('quick_link', ['label', 'url', 'description']),
          static fn(array $row): bool => $row['label'] !== '' && $row['url'] !== ''
        ));
        break;

      default:
        throw new RuntimeException(html_entity_decode('Se&ccedil;&atilde;o inv&aacute;lida.', ENT_QUOTES, 'UTF-8'));
    }

    corporate_landing_save($updated);
    header('Location: /index.php?saved=1');
    exit;
  } catch (Throwable $e) {
    $error = 'Erro ao salvar: ' . $e->getMessage();
  }
}

$now = new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo'));
$currentYear = (int) $now->format('Y');
$currentMonth = (int) $now->format('m');
$currentMonthLabel = month_name_pt($currentMonth) . ' de ' . $currentYear;

$hero = $config['hero'] ?? [];
$newsBlock = $config['popper_news'] ?? [];
$noticesBlock = $config['notices'] ?? [];
$houseBlock = $config['house_anniversaries'] ?? [];
$quickLinksBlock = $config['quick_links'] ?? [];

$noticeItems = normalize_notice_items((array) ($noticesBlock['items'] ?? []));
$houseAnniversaries = [];
$quickLinks = normalize_quick_links((array) ($quickLinksBlock['items'] ?? []), $isAdmin);

$birthdayStmt = db()->prepare('
    SELECT name, birth_date, setor
    FROM users
    WHERE is_active = 1
      AND birth_date IS NOT NULL
      AND birth_date <> ""
      AND MONTH(birth_date) = ?
    ORDER BY DAY(birth_date) ASC, name ASC
');
$birthdayStmt->execute([$currentMonth]);
$birthdays = $birthdayStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$houseAutoStmt = db()->prepare('
    SELECT name, start_date, setor
    FROM users
    WHERE is_active = 1
      AND start_date IS NOT NULL
      AND start_date <> ""
      AND MONTH(start_date) = ?
      AND YEAR(start_date) < ?
    ORDER BY DAY(start_date) ASC, name ASC
');
$houseAutoStmt->execute([$currentMonth, $currentYear]);
$autoHouseAnniversaries = array_map(
  static function (array $person) use ($now): array {
    $years = years_since_date((string) ($person['start_date'] ?? ''), $now);
    return [
      'name' => (string) ($person['name'] ?? 'Colaborador'),
      'date' => format_birth_date_short((string) ($person['start_date'] ?? '')),
      'years' => $years,
      'detail' => trim((string) ($person['setor'] ?? '')) !== '' ? ' ' . trim((string) $person['setor']) : 'Tempo de casa',
    ];
  },
  $houseAutoStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
);
$houseAnniversaries = normalize_house_anniversaries($autoHouseAnniversaries);
?>
<!doctype html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <title>Popper Conecta</title>

  <link rel="stylesheet" href="/assets/css/base.css?v=<?= filemtime(__DIR__ . '/assets/css/base.css') ?>">
  <link rel="stylesheet" href="/assets/css/header.css?v=<?= filemtime(__DIR__ . '/assets/css/header.css') ?>">
  <link rel="stylesheet" href="/assets/css/index-home.css?v=<?= filemtime(__DIR__ . '/assets/css/index-home.css') ?>">
</head>

<body class="corp-home">
  <?php $layout_embed = true; require_once APP_ROOT . '/app/layout/header.php'; ?>

  <div class="mobile-homebar" data-mobile-homebar>
    <div class="mobile-homebar__row">
      <button class="mobile-homebar__menu" type="button" data-mobile-nav-toggle aria-expanded="false"
        aria-controls="mobileHomeNav" aria-label="Abrir menu">
        <span></span>
        <span></span>
        <span></span>
      </button>

      <a class="mobile-homebar__brand" href="/index.php">
        <span class="mobile-homebar__brand-title">Popper Conecta</span>
        <span class="mobile-homebar__brand-subtitle">Início</span>
      </a>

      <a class="mobile-homebar__profile" href="/me.php" aria-label="Meus dados">
        <?php if ($mobileAvatarUrl !== ''): ?>
          <img class="mobile-homebar__avatar" src="<?= h($mobileAvatarUrl) ?>" alt="Foto de perfil">
        <?php else: ?>
          <span class="mobile-homebar__fallback" aria-hidden="true"><?= h($mobileInitials) ?></span>
        <?php endif; ?>
      </a>
    </div>

  </div>

  <div class="mobile-homenav-backdrop" data-mobile-nav-close hidden></div>
  <aside class="mobile-homenav" id="mobileHomeNav" data-mobile-homenav aria-hidden="true">
    <div class="mobile-homenav__header">
      <div>
        <div class="mobile-homenav__eyebrow">Navegação</div>
        <div class="mobile-homenav__title">Acesso rápido</div>
      </div>
      <button class="mobile-homenav__close" type="button" data-mobile-nav-close aria-label="Fechar menu">&times;</button>
    </div>

    <div class="mobile-homenav__content">
      <nav class="mobile-homenav__section">
        <div class="mobile-homenav__label">Principal</div>
        <a class="mobile-homenav__link" href="/index.php">Início</a>
        <a class="mobile-homenav__link" href="/me.php">Meus dados</a>
        <a class="mobile-homenav__link" href="/logout.php">Sair</a>
      </nav>

      <?php if ($mobileDashboardGroups !== []): ?>
        <div class="mobile-homenav__section">
          <div class="mobile-homenav__label">Dashboards</div>
          <?php foreach ($mobileDashboardGroups as $group): ?>
            <div class="mobile-homenav__group">
              <div class="mobile-homenav__group-title"><?= h((string) ($group['label'] ?? '')) ?></div>
              <?php foreach ((array) ($group['items'] ?? []) as $item): ?>
                <a class="mobile-homenav__link" href="<?= h((string) ($item['url'] ?? '#')) ?>">
                  <?= h((string) ($item['label'] ?? '')) ?>
                </a>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="mobile-homenav__section">
        <div class="mobile-homenav__label">Popper Coins</div>
        <?php foreach ($mobileCoinsMenu as $item): ?>
          <a class="mobile-homenav__link" href="<?= h((string) $item['url']) ?>">
            <?= h((string) $item['label']) ?>
          </a>
        <?php endforeach; ?>
      </div>

      <?php if ($mobileAdminItems !== []): ?>
        <div class="mobile-homenav__section">
          <div class="mobile-homenav__label">Administração</div>
          <?php foreach ($mobileAdminItems as $item): ?>
            <a class="mobile-homenav__link" href="<?= h((string) ($item['url'] ?? '#')) ?>">
              <?= h((string) ($item['label'] ?? '')) ?>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </aside>

  <main class="corp-page">
    <div class="corp-shell">
      <?php if ($success !== ''): ?>
        <div class="flash flash--ok"><?= h($success) ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="flash flash--error"><?= h($error) ?></div>
      <?php endif; ?>

      <section class="section-card hero-card">
        <?php if ($isAdmin): ?>
          <button class="edit-btn" type="button" data-modal-target="modal-hero" aria-label="Editar topo">&#9998;</button>
        <?php endif; ?>
        <span class="eyebrow"><?= h((string) ($hero['badge'] ?? 'Portal Corporativo')) ?></span>
        <p><?= h((string) ($hero['subtitle'] ?? '')) ?></p>

        <div class="hero-kpis">
          <article class="hero-kpi">
            <span class="hero-kpi__label">Faturamento do M&ecirc;s</span>
            <strong class="hero-kpi__value" id="hero-kpi-mes">R$ --</strong>
            <div class="hero-kpi__meta" id="hero-kpi-mes-meta">Carregando indicadores...</div>
          </article>

          <article class="hero-kpi">
            <span class="hero-kpi__label">Meta do M&ecirc;s</span>
            <strong class="hero-kpi__value" id="hero-kpi-meta">R$ --</strong>
            <div class="hero-kpi__meta" id="hero-kpi-meta-meta">Meta comercial vigente</div>
          </article>

          <article class="hero-kpi">
            <span class="hero-kpi__label">Atingimento</span>
            <strong class="hero-kpi__value" id="hero-kpi-ating">--%</strong>
            <div class="hero-kpi__meta" id="hero-kpi-ating-meta">Comparado ao m&ecirc;s atual</div>
          </article>

          <article class="hero-kpi">
            <span class="hero-kpi__label">Faturado Hoje</span>
            <strong class="hero-kpi__value" id="hero-kpi-hoje">R$ --</strong>
            <div class="hero-kpi__meta" id="hero-kpi-hoje-meta">Participa&ccedil;&atilde;o sobre o m&ecirc;s</div>
          </article>
        </div>

      </section>

      <div class="layout-grid">
        <div class="stack">
          <section class="section-card">
            <div class="card-head">
              <div>
                <span class="eyebrow"><?= h((string) ($newsBlock['eyebrow'] ?? 'Popper News')) ?></span>
                <h2>
                  <?= h((string) ($newsBlock['title'] ?? html_entity_decode('Edi&ccedil;&atilde;o atual dispon&iacute;vel para consulta', ENT_QUOTES, 'UTF-8'))) ?>
                </h2>
                <p><?= h((string) ($newsBlock['summary'] ?? '')) ?></p>
              </div>
              <?php if ($isAdmin): ?>
                <button class="edit-btn" type="button" data-modal-target="modal-news"
                  aria-label="Editar Popper News">&#9998;</button>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <?php if ($popperNewsImageUrls !== []): ?>
                <div class="news-preview" data-news-viewer>
                  <div class="news-preview__stage">
                    <button class="news-preview__btn news-preview__nav news-preview__nav--prev" type="button"
                      data-news-prev aria-label="P&aacute;gina anterior">&larr;</button>
                    <button class="news-preview__btn news-preview__nav news-preview__nav--next" type="button"
                      data-news-next aria-label="Pr&oacute;xima p&aacute;gina">&rarr;</button>
                    <img class="news-preview__image" data-news-image alt="Visualiza&ccedil;&atilde;o do Popper News">
                    <div class="news-preview__dots" data-news-dots aria-hidden="true"></div>
                    <div class="news-preview__message" data-news-message>Carregando edi&ccedil;&atilde;o...</div>
                    <script type="application/json"
                      data-news-pages><?= json_encode($popperNewsImageUrls, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
                  </div>
                </div>
              <?php else: ?>
                <div class="empty-note">Nenhuma imagem publicada ainda. Quando a edi&ccedil;&atilde;o for enviada, ela
                  aparece aqui automaticamente.</div>
              <?php endif; ?>
            </div>
          </section>

          <section class="section-card">
            <div class="card-head">
              <div>
                <span class="eyebrow"><?= h($currentMonthLabel) ?></span>
                <h2><?= h((string) ($noticesBlock['title'] ?? 'Painel de avisos')) ?></h2>
                <p><?= h((string) ($noticesBlock['subtitle'] ?? '')) ?></p>
              </div>
              <?php if ($isAdmin): ?>
                <button class="edit-btn" type="button" data-modal-target="modal-notices"
                  aria-label="Editar avisos">&#9998;</button>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <?php if ($noticeItems !== []): ?>
                <div class="notices-list">
                  <?php foreach ($noticeItems as $item): ?>
                    <article class="notice-card">
                      <?php if ((string) $item['label'] !== ''): ?>
                        <span class="notice-card__label"><?= h((string) $item['label']) ?></span>
                      <?php endif; ?>
                      <h3 class="notice-card__title"><?= h((string) $item['title']) ?></h3>
                      <?php if ((string) $item['detail'] !== ''): ?>
                        <div class="notice-card__detail"><?= h((string) $item['detail']) ?></div>
                      <?php endif; ?>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-note">Nenhum aviso cadastrado para exibi&ccedil;&atilde;o.</div>
              <?php endif; ?>
            </div>
          </section>
        </div>

        <div class="stack">
          <section class="section-card">
            <div class="card-head">
              <div>
                <span class="eyebrow">Pessoas</span>
                <h2>Aniversariantes do mês</h2>
              </div>
            </div>
            <div class="card-body">
              <?php if ($birthdays !== []): ?>
                <div class="list-grid">
                  <?php foreach ($birthdays as $person): ?>
                    <article class="people-card">
                      <div>
                        <div class="people-card__name"><?= h((string) ($person['name'] ?? 'Colaborador')) ?></div>
                        <div class="people-card__meta"><?= h((string) ($person['setor'] ?? 'Time Popper')) ?></div>
                      </div>
                      <div class="people-card__date">
                        <?= h(format_birth_date_short((string) ($person['birth_date'] ?? ''))) ?>
                      </div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-note">Nenhum aniversariante encontrado no mês atual com base nos dados cadastrados.
                </div>
              <?php endif; ?>
            </div>
          </section>

          <section class="section-card">
            <div class="card-head">
              <div>
                <span class="eyebrow">Reconhecimento</span>
                <h2><?= h((string) ($houseBlock['title'] ?? 'Aniversariantes de casa')) ?></h2>
                <p><?= h((string) ($houseBlock['subtitle'] ?? '')) ?></p>
              </div>
            </div>
            <div class="card-body">
              <?php if ($houseAnniversaries !== []): ?>
                <div class="list-grid">
                  <?php foreach ($houseAnniversaries as $item): ?>
                    <article class="people-card">
                      <div>
                        <div class="people-card__name"><?= h((string) $item['name']) ?></div>
                        <div class="people-card__meta">
                          <?php
                          $metaParts = array_filter([
                            trim((string) $item['years']) !== ''
                              ? trim((string) $item['years']) . ' ano(s)'
                              : '',
                            trim((string) $item['detail']) !== '' ? trim((string) $item['detail']) : '',
                          ]);
                          echo h(implode(' - ', $metaParts));
                          ?>
                        </div>
                      </div>
                      <div class="people-card__date"><?= h((string) $item['date']) ?></div>
                    </article>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-note">Nenhum aniversariante de casa cadastrado para destaque neste mês.</div>
              <?php endif; ?>
            </div>
          </section>

          <section class="section-card">
            <div class="card-head">
              <div>
                <span class="eyebrow">Atalhos</span>
                <h2><?= h((string) ($quickLinksBlock['title'] ?? 'Acessos rápidos')) ?></h2>
                <p><?= h((string) ($quickLinksBlock['subtitle'] ?? '')) ?></p>
              </div>
              <?php if ($isAdmin): ?>
                <button class="edit-btn" type="button" data-modal-target="modal-links"
                  aria-label="Editar acessos rápidos">&#9998;</button>
              <?php endif; ?>
            </div>
            <div class="card-body">
              <?php if ($quickLinks !== []): ?>
                <div class="quick-links">
                  <?php foreach ($quickLinks as $link): ?>
                    <a class="quick-link" href="<?= h((string) $link['url']) ?>">
                      <strong><?= h((string) $link['label']) ?></strong>
                      <span><?= h((string) $link['description']) ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="empty-note">Nenhum acesso rápido configurado para exibição.</div>
              <?php endif; ?>
            </div>
          </section>
        </div>
      </div>
    </div>
  </main>
  <?php if ($isAdmin): ?>
    <div class="modal" id="modal-hero" aria-hidden="true">
      <div class="modal__dialog">
        <div class="modal__head">
          <h3 class="modal__title">Editar topo da landing</h3>
          <button class="edit-btn" type="button" data-modal-close aria-label="Fechar modal">&times;</button>
        </div>
        <form method="post" class="modal__body">
          <input type="hidden" name="section" value="hero">
          <div class="field">
            <label for="hero-badge">Badge</label>
            <input id="hero-badge" type="text" name="badge" value="<?= h((string) ($hero['badge'] ?? '')) ?>">
          </div>
          <input id="hero-title" type="hidden" name="title" value="<?= h((string) ($hero['title'] ?? '')) ?>">
          <div class="field">
            <label for="hero-subtitle">Texto de apoio</label>
            <textarea id="hero-subtitle" name="subtitle"><?= h((string) ($hero['subtitle'] ?? '')) ?></textarea>
          </div>
          <div class="modal__actions">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancelar</button>
            <button class="btn btn--primary" type="submit">Salvar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal" id="modal-news" aria-hidden="true">
      <div class="modal__dialog">
        <div class="modal__head">
          <h3 class="modal__title">Editar bloco Popper News</h3>
          <button class="edit-btn" type="button" data-modal-close aria-label="Fechar modal">&times;</button>
        </div>
        <form method="post" class="modal__body">
          <input type="hidden" name="section" value="popper_news">
          <div class="field">
            <label for="news-eyebrow">Eyebrow</label>
            <input id="news-eyebrow" type="text" name="eyebrow" value="<?= h((string) ($newsBlock['eyebrow'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="news-title">T&iacute;tulo</label>
            <input id="news-title" type="text" name="title" value="<?= h((string) ($newsBlock['title'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="news-summary">Resumo</label>
            <textarea id="news-summary" name="summary"><?= h((string) ($newsBlock['summary'] ?? '')) ?></textarea>
          </div>
          <div class="modal__actions">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancelar</button>
            <button class="btn btn--primary" type="submit">Salvar</button>
          </div>
        </form>
      </div>
    </div>

    <div class="modal" id="modal-notices" aria-hidden="true">
      <div class="modal__dialog">
        <div class="modal__head">
          <h3 class="modal__title">Editar painel de avisos</h3>
          <button class="edit-btn" type="button" data-modal-close aria-label="Fechar modal">&times;</button>
        </div>
        <form method="post" class="modal__body">
          <input type="hidden" name="section" value="notices">
          <div class="field">
            <label for="notices-title">T&iacute;tulo</label>
            <input id="notices-title" type="text" name="title" value="<?= h((string) ($noticesBlock['title'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="notices-subtitle">Subt&iacute;tulo</label>
            <textarea id="notices-subtitle"
              name="subtitle"><?= h((string) ($noticesBlock['subtitle'] ?? '')) ?></textarea>
          </div>
          <div class="field">
            <div class="repeater">
              <div class="repeater__head">
                <label>Avisos</label>
                <button class="btn btn--ghost" type="button" data-repeater-add="notices-items-list">Adicionar
                  aviso</button>
              </div>
              <div class="repeater__list" id="notices-items-list">
                <?php foreach (($noticesBlock['items'] ?? []) as $item): ?>
                  <div class="repeater__item" data-repeater-item>
                    <div class="repeater__grid notice-form-grid">
                      <div class="field notice-label-field">
                        <label>Etiqueta</label>
                        <input type="text" name="notice_item_label[]" placeholder="Ex.: Hoje, RH, Importante"
                          value="<?= h((string) ($item['label'] ?? '')) ?>">
                      </div>
                      <div class="field notice-title-field">
                        <label>T&iacute;tulo</label>
                        <input type="text" name="notice_item_title[]" value="<?= h((string) ($item['title'] ?? '')) ?>">
                      </div>
                      <div class="field notice-description-field">
                        <label>Descri&ccedil;&atilde;o</label>
                        <textarea name="notice_item_detail[]" rows="4"><?= h((string) ($item['detail'] ?? '')) ?></textarea>
                      </div>
                    </div>
                    <div class="item-actions">
                      <button class="btn btn--ghost btn--danger" type="button" data-repeater-remove>Remover</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <template id="notices-items-list-template">
                <div class="repeater__item" data-repeater-item>
                  <div class="repeater__grid notice-form-grid">
                    <div class="field notice-label-field">
                      <label>Etiqueta</label>
                      <input type="text" name="notice_item_label[]" placeholder="Ex.: Hoje, RH, Importante" value="">
                    </div>
                    <div class="field notice-title-field">
                      <label>T&iacute;tulo</label>
                      <input type="text" name="notice_item_title[]" value="">
                    </div>
                    <div class="field notice-description-field">
                      <label>Descri&ccedil;&atilde;o</label>
                      <textarea name="notice_item_detail[]" rows="4"></textarea>
                    </div>
                  </div>
                  <div class="item-actions">
                    <button class="btn btn--ghost btn--danger" type="button" data-repeater-remove>Remover</button>
                  </div>
                </div>
              </template>
            </div>
          </div>
          <div class="modal__actions">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancelar</button>
            <button class="btn btn--primary" type="submit">Salvar</button>
          </div>
        </form>
      </div>
    </div>
    <div class="modal" id="modal-links" aria-hidden="true">
      <div class="modal__dialog">
        <div class="modal__head">
          <h3 class="modal__title">Editar acessos r&aacute;pidos</h3>
          <button class="edit-btn" type="button" data-modal-close aria-label="Fechar modal">&times;</button>
        </div>
        <form method="post" class="modal__body">
          <input type="hidden" name="section" value="quick_links">
          <div class="field">
            <label for="links-title">T&iacute;tulo</label>
            <input id="links-title" type="text" name="title" value="<?= h((string) ($quickLinksBlock['title'] ?? '')) ?>">
          </div>
          <div class="field">
            <label for="links-subtitle">Subt&iacute;tulo</label>
            <textarea id="links-subtitle"
              name="subtitle"><?= h((string) ($quickLinksBlock['subtitle'] ?? '')) ?></textarea>
          </div>
          <div class="field">
            <div class="repeater">
              <div class="repeater__head">
                <label>Acessos r&aacute;pidos</label>
                <button class="btn btn--ghost" type="button" data-repeater-add="quick-links-list">Adicionar link</button>
              </div>
              <div class="repeater__list" id="quick-links-list">
                <?php foreach (($quickLinksBlock['items'] ?? []) as $item): ?>
                  <div class="repeater__item" data-repeater-item>
                    <div class="repeater__grid">
                      <div class="field span-5">
                        <label>T&iacute;tulo</label>
                        <input type="text" name="quick_link_label[]" value="<?= h((string) ($item['label'] ?? '')) ?>">
                      </div>
                      <div class="field span-7">
                        <label>URL</label>
                        <input type="text" name="quick_link_url[]" value="<?= h((string) ($item['url'] ?? '')) ?>">
                      </div>
                      <div class="field span-12">
                        <label>Descri&ccedil;&atilde;o</label>
                        <input type="text" name="quick_link_description[]"
                          value="<?= h((string) ($item['description'] ?? '')) ?>">
                      </div>
                    </div>
                    <div class="item-actions">
                      <button class="btn btn--ghost btn--danger" type="button" data-repeater-remove>Remover</button>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
              <template id="quick-links-list-template">
                <div class="repeater__item" data-repeater-item>
                  <div class="repeater__grid">
                    <div class="field span-5">
                      <label>T&iacute;tulo</label>
                      <input type="text" name="quick_link_label[]" value="">
                    </div>
                    <div class="field span-7">
                      <label>URL</label>
                      <input type="text" name="quick_link_url[]" value="">
                    </div>
                    <div class="field span-12">
                      <label>Descri&ccedil;&atilde;o</label>
                      <input type="text" name="quick_link_description[]" value="">
                    </div>
                  </div>
                  <div class="item-actions">
                    <button class="btn btn--ghost btn--danger" type="button" data-repeater-remove>Remover</button>
                  </div>
                </div>
              </template>
            </div>
          </div>
          <div class="modal__actions">
            <button class="btn btn--ghost" type="button" data-modal-close>Cancelar</button>
            <button class="btn btn--primary" type="submit">Salvar</button>
          </div>
        </form>
      </div>
    </div>
  <?php endif; ?>

  <?php require_once APP_ROOT . '/app/layout/footer.php'; ?>

  <script src="/assets/js/header.js?v=<?= filemtime(APP_ROOT . '/assets/js/header.js') ?>"></script>
  <script src="/assets/js/index-mobile-nav.js?v=<?= filemtime(APP_ROOT . '/assets/js/index-mobile-nav.js') ?>"></script>
  <script src="/assets/js/index-news-carousel.js?v=<?= filemtime(APP_ROOT . '/assets/js/index-news-carousel.js') ?>"></script>
  <script src="/assets/js/index-hero-kpis.js?v=<?= filemtime(APP_ROOT . '/assets/js/index-hero-kpis.js') ?>"></script>
  <?php if ($isAdmin): ?>
    <script src="/assets/js/index-admin-modals.js?v=<?= filemtime(APP_ROOT . '/assets/js/index-admin-modals.js') ?>"></script>
  <?php endif; ?>
</body>

</html>