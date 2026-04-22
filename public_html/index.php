<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
require_once APP_ROOT . '/app/services/popper_news.php';
require_once APP_ROOT . '/app/services/corporate_landing.php';

require_login();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u = current_user();
$activePage = 'home';
$isAdmin = (($u['role'] ?? '') === 'admin');
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
      'detail' => trim((string) ($person['setor'] ?? '')) !== '' ? 'Tempo de casa - ' . trim((string) $person['setor']) : 'Tempo de casa',
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

  <style>
    :root {
      --bg: #f4eef7;
      --card: rgba(255, 255, 255, .95);
      --text: #231c2a;
      --muted: #6c6474;
      --line: rgba(92, 44, 140, .10);
      --purple: #5c2c8c;
      --purple-2: #8648c0;
      --shadow: 0 24px 70px rgba(74, 28, 108, .14);
      --radius-xl: 30px;
      --radius-lg: 22px;
      --container: min(1440px, calc(100% - 32px));
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      color: var(--text);
      background:
        radial-gradient(circle at top right, rgba(216, 63, 133, .08), transparent 20%),
        radial-gradient(circle at left center, rgba(92, 44, 140, .10), transparent 26%),
        linear-gradient(180deg, #fbf7fd 0%, #f2ebf6 100%);
    }

    .corp-page {
      padding: 24px 0 42px;
    }

    .corp-shell {
      width: var(--container);
      margin: 0 auto;
      display: grid;
      gap: 20px;
    }

    .section-card {
      position: relative;
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow);
      overflow: hidden;
    }

    .hero-card {
      padding: 28px;
      background:
        radial-gradient(circle at top right, rgba(92, 44, 140, .16), transparent 28%),
        linear-gradient(135deg, rgba(255, 255, 255, .98), rgba(247, 240, 251, .96));
    }

    .eyebrow {
      display: inline-flex;
      min-height: 32px;
      align-items: center;
      justify-content: center;
      padding: 0 12px;
      border-radius: 999px;
      background: rgba(92, 44, 140, .10);
      color: var(--purple);
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .06em;
      text-transform: uppercase;
    }

    .hero-card p,
    .card-head p {
      margin: 0;
      color: var(--muted);
      line-height: 1.8;
    }

    .hero-actions {
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-top: 22px;
    }

    .hero-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 14px;
      margin-top: 20px;
    }

    .hero-kpi {
      padding: 18px;
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(247, 241, 251, .92));
      border: 1px solid rgba(15, 23, 42, .06);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .4);
    }

    .hero-kpi__label {
      display: block;
      font-size: 12px;
      font-weight: 900;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 10px;
    }

    .hero-kpi__value {
      display: block;
      font-size: clamp(1.5rem, 2vw, 2.2rem);
      line-height: 1;
      font-weight: 900;
      color: var(--purple);
      margin-bottom: 8px;
    }

    .hero-kpi__meta {
      color: var(--muted);
      font-size: .9rem;
      line-height: 1.55;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 46px;
      padding: 0 18px;
      border-radius: 14px;
      border: 1px solid transparent;
      text-decoration: none;
      font-weight: 800;
      transition: transform .18s ease, box-shadow .18s ease;
      cursor: pointer;
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .btn--primary {
      background: linear-gradient(135deg, var(--purple), var(--purple-2));
      color: #fff;
      box-shadow: 0 14px 30px rgba(92, 44, 140, .24);
    }

    .btn--ghost {
      background: rgba(255, 255, 255, .92);
      color: var(--purple);
      border-color: var(--line);
    }

    .btn--danger {
      background: rgba(239, 68, 68, .08);
      color: #991b1b;
      border-color: rgba(239, 68, 68, .14);
    }

    .layout-grid {
      display: grid;
      grid-template-columns: 1.5fr .72fr;
      gap: 20px;
      align-items: start;
    }

    .stack {
      display: grid;
      gap: 20px;
    }

    .card-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px;
      padding: 22px 22px 0;
    }

    .card-head h2 {
      margin: 0 0 8px;
      font-size: 1.45rem;
      color: #1c1623;
    }

    .card-body {
      padding: 18px 22px 22px;
    }

    .edit-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 999px;
      border: 1px solid rgba(92, 44, 140, .14);
      background: rgba(255, 255, 255, .88);
      color: var(--purple);
      cursor: pointer;
    }

    .hero-card>.edit-btn {
      position: absolute;
      top: 18px;
      right: 18px;
    }

    .news-preview {
      display: grid;
      gap: 0;
      background: #fff;
      border-radius: 24px;
      padding: 0;
      box-shadow: inset 0 0 0 1px rgba(15, 23, 42, .05);
    }

    .news-preview__btn {
      min-height: 40px;
      padding: 0 14px;
      border-radius: 12px;
      border: 0;
      background: transparent;
      color: var(--purple);
      font: inherit;
      font-weight: 900;
      cursor: pointer;
      transition: color .18s ease, opacity .18s ease, transform .18s ease;
    }

    .news-preview__btn:hover:not(:disabled) {
      color: #7a3db1;
      opacity: .92;
    }

    .news-preview__btn:disabled {
      opacity: .45;
      cursor: not-allowed;
      box-shadow: none;
    }

    .news-preview__stage {
      width: 100%;
      min-height: 620px;
      display: grid;
      place-items: center center;
      align-content: stretch;
      overflow: hidden;
      position: relative;
      border-radius: 18px;
      background: #fff;
      border: 0;
      padding: 0;
    }

    .news-preview__image {
      display: block;
      width: 100%;
      max-width: none;
      max-height: none;
      height: 100%;
      min-height: 620px;
      object-fit: cover;
      object-position: center top;
      background: #fff;
      box-shadow: none;
      position: absolute;
      inset: 0;
      z-index: 2;
    }


    .news-preview__message {
      color: var(--muted);
      text-align: center;
      line-height: 1.7;
      padding: 28px 18px;
    }

    .news-preview__nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 34px;
      height: 34px;
      border-radius: 0;
      font-size: 1.4rem;
      line-height: 1;
      text-shadow: 0 2px 12px rgba(255, 255, 255, .92);
      z-index: 3;
    }

    .news-preview__image.is-dragging {
      transition: none;
      cursor: grabbing;
    }

    .news-preview__image.is-enter-next {
      transform: translateX(100%);
      opacity: .92;
    }

    .news-preview__image.is-enter-prev {
      transform: translateX(-100%);
      opacity: .92;
    }

    .news-preview__image.is-leave-next {
      transform: translateX(-100%);
      opacity: 0;
    }

    .news-preview__image.is-leave-prev {
      transform: translateX(100%);
      opacity: 0;
    }

    .news-preview__nav--prev {
      left: 18px;
    }

    .news-preview__nav--next {
      right: 18px;
    }

    .news-preview__dots {
      position: absolute;
      left: 50%;
      bottom: 18px;
      transform: translateX(-50%);
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .86);
      backdrop-filter: blur(8px);
      box-shadow: 0 12px 28px rgba(15, 23, 42, .10);
      z-index: 3;
    }

    .news-preview__dot {
      width: 9px;
      height: 9px;
      border-radius: 999px;
      background: rgba(92, 44, 140, .22);
      transition: transform .24s ease, background-color .24s ease;
    }

    .news-preview__dot.is-active {
      background: var(--purple);
      transform: scale(1.18);
    }

    .news-preview__image.is-sliding-next {
      transform: translateX(120px);
      opacity: 0;
    }

    .news-preview__image.is-sliding-prev {
      transform: translateX(-120px);
      opacity: 0;
    }

    .notices-list {
      display: grid;
      gap: 12px;
    }

    .notice-card {
      display: grid;
      gap: 8px;
      padding: 16px 18px;
      border-radius: 18px;
      border: 1px solid rgba(15, 23, 42, .06);
      background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 244, 251, .94));
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .36);
    }

    .notice-card__label {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: fit-content;
      min-height: 28px;
      padding: 0 10px;
      border-radius: 999px;
      background: rgba(92, 44, 140, .10);
      color: var(--purple);
      font-size: .76rem;
      font-weight: 900;
      letter-spacing: .04em;
      text-transform: uppercase;
    }

    .notice-card__title {
      margin: 0;
      color: #1f1727;
      font-size: 1rem;
      line-height: 1.4;
    }

    .notice-card__detail {
      color: var(--muted);
      line-height: 1.7;
      font-size: .94rem;
      white-space: pre-wrap;
    }

    .list-grid {
      display: grid;
      gap: 12px;
    }

    .people-card {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      padding: 14px 16px;
      border-radius: 18px;
      background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 244, 251, .94));
      border: 1px solid rgba(15, 23, 42, .06);
    }

    .people-card__name {
      font-weight: 800;
      color: #1f1727;
    }

    .people-card__meta {
      margin-top: 4px;
      color: var(--muted);
      font-size: .92rem;
    }

    .people-card__date {
      min-width: 82px;
      text-align: center;
      padding: 10px 12px;
      border-radius: 14px;
      background: rgba(216, 63, 133, .08);
      color: #ad2f68;
      font-weight: 900;
    }

    .quick-links {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
    }

    .quick-link {
      display: grid;
      gap: 6px;
      min-height: 110px;
      padding: 16px;
      border-radius: 18px;
      text-decoration: none;
      color: var(--text);
      background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 244, 251, .94));
      border: 1px solid rgba(15, 23, 42, .06);
      transition: transform .18s ease, border-color .18s ease;
    }

    .quick-link:hover {
      transform: translateY(-2px);
      border-color: rgba(92, 44, 140, .24);
    }

    .quick-link strong {
      color: var(--purple);
      font-size: 1rem;
    }

    .quick-link span {
      color: var(--muted);
      line-height: 1.6;
      font-size: .92rem;
    }

    .empty-note {
      padding: 18px;
      border-radius: 18px;
      border: 1px dashed rgba(92, 44, 140, .18);
      color: var(--muted);
      line-height: 1.7;
      background: rgba(255, 255, 255, .72);
    }

    .flash {
      padding: 14px 16px;
      border-radius: 16px;
      font-weight: 700;
      border: 1px solid transparent;
    }

    .flash--ok {
      background: rgba(22, 163, 74, .08);
      color: #166534;
      border-color: rgba(22, 163, 74, .16);
    }

    .flash--error {
      background: rgba(239, 68, 68, .08);
      color: #991b1b;
      border-color: rgba(239, 68, 68, .16);
    }

    .modal {
      position: fixed;
      inset: 0;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
      background: rgba(15, 23, 42, .42);
      backdrop-filter: blur(6px);
      z-index: 200000;
    }

    .modal.is-open {
      display: flex;
    }

    .modal__dialog {
      width: min(900px, 100%);
      max-height: calc(100vh - 40px);
      overflow: auto;
      border-radius: 26px;
      background: #fff;
      border: 1px solid rgba(15, 23, 42, .08);
      box-shadow: 0 30px 90px rgba(15, 23, 42, .22);
    }

    .modal__head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 14px;
      padding: 20px 22px;
      border-bottom: 1px solid rgba(15, 23, 42, .08);
    }

    .modal__title {
      margin: 0;
      font-size: 1.2rem;
      color: #1f1727;
    }

    .modal__body {
      padding: 20px 22px 24px;
      display: grid;
      gap: 14px;
    }

    .field {
      display: grid;
      gap: 8px;
    }

    .field label {
      font-size: .94rem;
      font-weight: 800;
      color: #334155;
    }

    .field input,
    .field textarea {
      width: 100%;
      min-height: 46px;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, .12);
      background: #fff;
      color: #0f172a;
      font: inherit;
      resize: vertical;
    }

    .news-preview__slide {
      position: absolute;
      inset: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center top;
      background: #fff;
      will-change: transform;
    }

    .news-preview__slide--current {
      z-index: 2;
      transform: translateX(0);
    }

    .news-preview__slide--next {
      z-index: 3;
    }

    .news-preview__slide--from-right {
      transform: translateX(100%);
    }

    .news-preview__slide--from-left {
      transform: translateX(-100%);
    }

    .news-preview__slide--to-left {
      transform: translateX(-100%);
      transition: transform .45s cubic-bezier(.22, 1, .36, 1);
    }

    .news-preview__slide--to-right {
      transform: translateX(100%);
      transition: transform .45s cubic-bezier(.22, 1, .36, 1);
    }

    .news-preview__slide--to-center {
      transform: translateX(0);
      transition: transform .45s cubic-bezier(.22, 1, .36, 1);
    }

    .field textarea {
      min-height: 130px;
    }

    .field small {
      color: var(--muted);
      line-height: 1.6;
    }

    .modal__actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      flex-wrap: wrap;
    }

    .repeater {
      display: grid;
      gap: 12px;
    }

    .repeater__head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .repeater__list {
      display: grid;
      gap: 12px;
    }

    .repeater__item {
      padding: 18px;
      border-radius: 18px;
      border: 1px solid rgba(15, 23, 42, .08);
      background: linear-gradient(180deg, rgba(248, 250, 252, .82), rgba(255, 255, 255, 1));
    }

    .repeater__grid {
      display: grid;
      grid-template-columns: repeat(12, minmax(0, 1fr));
      gap: 14px;
    }

    .span-12 {
      grid-column: span 12;
    }

    .span-8 {
      grid-column: span 8;
    }

    .span-7 {
      grid-column: span 7;
    }

    .span-5 {
      grid-column: span 5;
    }

    .span-4 {
      grid-column: span 4;
    }

    .span-3 {
      grid-column: span 3;
    }

    .span-2 {
      grid-column: span 2;
    }

    .notice-form-grid {
      grid-template-columns: 170px minmax(0, 1fr);
      align-items: start;
    }

    .notice-form-grid .notice-title-field,
    .notice-form-grid .notice-description-field {
      grid-column: auto;
    }

    .notice-form-grid .notice-description-field {
      grid-column: 1 / -1;
    }

    .notice-form-grid textarea {
      min-height: 118px;
      line-height: 1.55;
    }

    .item-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: 10px;
    }

    @media (max-width: 1080px) {
      .layout-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 860px) {

      .hero-card,
      .card-head,
      .card-body {
        padding-left: 18px;
        padding-right: 18px;
      }

      .hero-kpis {
        grid-template-columns: 1fr;
      }

      .quick-links {
        grid-template-columns: 1fr;
      }

      .repeater__grid {
        grid-template-columns: 1fr;
      }

      .span-12,
      .span-8,
      .span-7,
      .span-5,
      .span-4,
      .span-3,
      .span-2,
      .notice-form-grid .notice-title-field,
      .notice-form-grid .notice-description-field {
        grid-column: auto;
      }

      .news-preview__stage {
        min-height: 54vh;
      }

      .news-preview__image {
        min-height: 54vh;
      }

      .news-preview__nav {
        width: 30px;
        height: 30px;
        font-size: 1.2rem;
      }

      .news-preview__nav--prev {
        left: 10px;
      }

      .news-preview__nav--next {
        right: 10px;
      }

      .news-preview__dots {
        bottom: 10px;
        padding: 8px 12px;
        gap: 7px;
      }
    }
  </style>
</head>

<body>
  <?php require_once APP_ROOT . '/app/layout/header.php'; ?>

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
                <p>Lista automática baseada na data de nascimento cadastrada nos usuários ativos.</p>
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
  <script>
    (function () {
      const viewer = document.querySelector('[data-news-viewer]');
      if (!viewer) return;

      const pagesNode = viewer.querySelector('[data-news-pages]');
      const image = viewer.querySelector('[data-news-image]');
      const message = viewer.querySelector('[data-news-message]');
      const prevBtn = viewer.querySelector('[data-news-prev]');
      const nextBtn = viewer.querySelector('[data-news-next]');
      const dots = viewer.querySelector('[data-news-dots]');
      const stage = viewer.querySelector('.news-preview__stage');

      let pages = [];

      try {
        pages = JSON.parse(pagesNode ? pagesNode.textContent : '[]');
      } catch (error) {
        console.warn(error);
      }

      if (!Array.isArray(pages) || pages.length === 0) {
        if (message) {
          message.textContent = 'Nenhuma imagem disponível para exibição.';
          message.hidden = false;
        }
        if (prevBtn) prevBtn.disabled = true;
        if (nextBtn) nextBtn.disabled = true;
        return;
      }

      let pageIndex = 0;
      let isAnimating = false;
      let autoplayPaused = false;
      let autoplayTimer = null;
      const autoplayDelay = 4200;

      function setMessage(text, visible) {
        if (!message) return;
        message.textContent = text;
        message.hidden = !visible;
      }

      function updateDots() {
        if (!dots) return;
        dots.innerHTML = '';
        pages.forEach((_, index) => {
          const dot = document.createElement('span');
          dot.className = 'news-preview__dot' + (index === pageIndex ? ' is-active' : '');
          dots.appendChild(dot);
        });
      }

      function updateControls() {
        if (prevBtn) prevBtn.disabled = isAnimating || pages.length <= 1;
        if (nextBtn) nextBtn.disabled = isAnimating || pages.length <= 1;
      }

      function clearAutoplay() {
        if (autoplayTimer !== null) {
          window.clearTimeout(autoplayTimer);
          autoplayTimer = null;
        }
      }

      function scheduleAutoplay() {
        clearAutoplay();
        if (autoplayPaused || pages.length <= 1) return;
        autoplayTimer = window.setTimeout(function () {
          goTo(pageIndex + 1, 'next', false);
        }, autoplayDelay);
      }

      function preload(src) {
        return new Promise(function (resolve, reject) {
          const img = new Image();
          img.onload = function () { resolve(img); };
          img.onerror = reject;
          img.src = src;
        });
      }

      async function goTo(targetIndex, direction, pauseAuto) {
        if (isAnimating || pages.length === 0) return;

        if (pauseAuto) {
          autoplayPaused = true;
          clearAutoplay();
          window.setTimeout(function () {
            autoplayPaused = false;
            scheduleAutoplay();
          }, 9000);
        }

        const normalizedIndex = (targetIndex + pages.length) % pages.length;
        const nextSrc = pages[normalizedIndex];

        isAnimating = true;
        updateControls();
        setMessage('Carregando edição...', true);

        try {
          await preload(nextSrc);

          const currentSlide = document.createElement('img');
          currentSlide.src = image.src || pages[pageIndex];
          currentSlide.className = 'news-preview__slide news-preview__slide--current';

          const nextSlide = document.createElement('img');
          nextSlide.src = nextSrc;
          nextSlide.className =
            'news-preview__slide news-preview__slide--next ' +
            (direction === 'prev'
              ? 'news-preview__slide--from-left'
              : 'news-preview__slide--from-right');

          stage.appendChild(currentSlide);
          stage.appendChild(nextSlide);

          image.style.visibility = 'hidden';

          requestAnimationFrame(function () {
            requestAnimationFrame(function () {
              currentSlide.classList.add(
                direction === 'prev'
                  ? 'news-preview__slide--to-right'
                  : 'news-preview__slide--to-left'
              );
              nextSlide.classList.add('news-preview__slide--to-center');
            });
          });

          window.setTimeout(function () {
            image.src = nextSrc;
            image.style.visibility = '';
            currentSlide.remove();
            nextSlide.remove();

            pageIndex = normalizedIndex;
            isAnimating = false;
            updateControls();
            updateDots();
            setMessage('', false);
            scheduleAutoplay();
          }, 470);
        } catch (error) {
          console.warn(error);
          isAnimating = false;
          updateControls();
          setMessage('Não foi possível carregar esta página.', true);
        }
      }

      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          goTo(pageIndex - 1, 'prev', true);
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          goTo(pageIndex + 1, 'next', true);
        });
      }

      viewer.addEventListener('mouseenter', function () {
        autoplayPaused = true;
        clearAutoplay();
      });

      viewer.addEventListener('mouseleave', function () {
        autoplayPaused = false;
        scheduleAutoplay();
      });

      image.addEventListener('load', function () {
        setMessage('', false);
      });

      image.src = pages[0];
      updateDots();
      updateControls();
      setMessage('', false);
      scheduleAutoplay();
    })();
  </script>
  <script>
    (function () {
      function brl(v) {
        return new Intl.NumberFormat('pt-BR', {
          style: 'currency',
          currency: 'BRL'
        }).format(Number(v) || 0);
      }

      function pct(v) {
        return new Intl.NumberFormat('pt-BR', {
          minimumFractionDigits: 1,
          maximumFractionDigits: 1
        }).format(Number(v) || 0) + '%';
      }

      function safeText(id, text) {
        var el = document.getElementById(id);
        if (el) {
          el.textContent = text;
        }
      }

      const kpiCacheKey = 'index2:hero-kpis:v1';
      const kpiCacheTtl = 5 * 60 * 1000;

      function readKpiCache(allowExpired) {
        try {
          const raw = localStorage.getItem(kpiCacheKey);
          if (!raw) return null;

          const cached = JSON.parse(raw);
          if (!cached || !cached.savedAt || !cached.data) return null;
          if (!allowExpired && (Date.now() - Number(cached.savedAt)) > kpiCacheTtl) return null;

          return cached.data;
        } catch (err) {
          console.warn(err);
          return null;
        }
      }

      function writeKpiCache(data) {
        try {
          localStorage.setItem(kpiCacheKey, JSON.stringify({
            savedAt: Date.now(),
            data: data
          }));
        } catch (err) {
          console.warn(err);
        }
      }

      function renderHeroKpis(data) {
        const v = data && data.values ? data.values : null;
        if (!v) {
          throw new Error('Payload inv\u00e1lido');
        }

        var hojeTotal = Number(v.hoje_total || 0);
        var mesTotal = Number(v.mes_total || 0);
        var hojePct = mesTotal > 0 ? (hojeTotal / mesTotal) * 100 : 0;

        safeText('hero-kpi-mes', brl(v.mes_total));
        safeText('hero-kpi-meta', brl(v.meta_mes));
        safeText('hero-kpi-ating', pct((Number(v.atingimento_mes_pct) || 0) * 100));
        safeText('hero-kpi-hoje', brl(hojeTotal));

        safeText('hero-kpi-mes-meta', 'Atualizado: ' + (data.updated_at || '--'));
        safeText('hero-kpi-meta-meta', 'Meta comercial vigente');
        safeText('hero-kpi-ating-meta', 'Comparado ao m\u00eas atual');
        safeText(
          'hero-kpi-hoje-meta',
          mesTotal > 0
            ? pct(hojePct) + ' do faturamento do m\u00eas'
            : 'Sem base mensal para comparar'
        );
      }

      async function loadHeroKpis() {
        const cached = readKpiCache(true);
        const freshCached = readKpiCache(false);
        let renderedFromCache = false;

        if (cached) {
          renderHeroKpis(cached);
          renderedFromCache = true;
        }

        if (freshCached) {
          return;
        }

        try {
          const response = await fetch('/api/dashboard/dashboard-data.php?dash=executivo', {
            cache: 'no-store'
          });

          if (!response.ok) {
            throw new Error('Falha ao carregar indicadores');
          }

          const data = await response.json();
          renderHeroKpis(data);
          writeKpiCache(data);
        } catch (err) {
          console.warn(err);
          if (renderedFromCache) {
            return;
          }

          safeText('hero-kpi-mes-meta', 'N\u00e3o foi poss\u00edvel carregar');
          safeText('hero-kpi-meta-meta', 'N\u00e3o foi poss\u00edvel carregar');
          safeText('hero-kpi-ating-meta', 'N\u00e3o foi poss\u00edvel carregar');
          safeText('hero-kpi-hoje-meta', 'N\u00e3o foi poss\u00edvel carregar');
        }
      }

      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadHeroKpis, { once: true });
      } else {
        loadHeroKpis();
      }
    })();
  </script>
  <?php if ($isAdmin): ?>
    <script>
      (function () {
        const body = document.body;

        function closeModal(modal) {
          if (!modal) return;
          modal.classList.remove('is-open');
          modal.setAttribute('aria-hidden', 'true');
          body.style.overflow = '';
        }

        function openModal(modal) {
          if (!modal) return;
          modal.classList.add('is-open');
          modal.setAttribute('aria-hidden', 'false');
          body.style.overflow = 'hidden';
        }

        document.querySelectorAll('[data-modal-target]').forEach((button) => {
          button.addEventListener('click', function () {
            openModal(document.getElementById(button.getAttribute('data-modal-target')));
          });
        });

        document.querySelectorAll('[data-modal-close]').forEach((button) => {
          button.addEventListener('click', function () {
            closeModal(button.closest('.modal'));
          });
        });

        document.querySelectorAll('.modal').forEach((modal) => {
          modal.addEventListener('click', function (event) {
            if (event.target === modal) closeModal(modal);
          });
        });

        document.addEventListener('keydown', function (event) {
          if (event.key !== 'Escape') return;
          document.querySelectorAll('.modal.is-open').forEach(closeModal);
        });

        document.querySelectorAll('[data-repeater-add]').forEach((button) => {
          button.addEventListener('click', function () {
            const listId = button.getAttribute('data-repeater-add');
            const list = document.getElementById(listId);
            const template = document.getElementById(listId + '-template');
            if (!list || !template) return;
            list.appendChild(template.content.cloneNode(true));
          });
        });

        document.addEventListener('click', function (event) {
          const removeBtn = event.target.closest('[data-repeater-remove]');
          if (!removeBtn) return;
          const item = removeBtn.closest('[data-repeater-item]');
          if (item) item.remove();
        });
      })();
    </script>
  <?php endif; ?>
</body>

</html>
