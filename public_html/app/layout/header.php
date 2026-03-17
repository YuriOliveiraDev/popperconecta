<?php
declare(strict_types=1);

/**
 * Layout base
 * Variáveis opcionais:
 * - $page_title
 * - $html_class
 * - $extra_css
 * - $extra_js_head
 * - $activePage
 * - $current_dash
 * - $u
 */

require_once __DIR__ . '/../../bootstrap.php';
require_once APP_ROOT . '/app/helpers/header_helper.php';

$u = $u ?? current_user();
$activePage = $activePage ?? '';
$current_dash = $current_dash ?? 'executivo';

$page_title = (isset($page_title) && is_string($page_title) && trim($page_title) !== '')
    ? trim($page_title)
    : (defined('APP_NAME') ? (string) APP_NAME : 'Popper Conecta');

$html_class = (isset($html_class) && is_string($html_class))
    ? trim($html_class)
    : '';

$extra_css = (isset($extra_css) && is_array($extra_css)) ? $extra_css : [];
$extra_js_head = (isset($extra_js_head) && is_array($extra_js_head)) ? $extra_js_head : [];

$headerCssBase = '/assets/css/header.css';
$headerCssNotif = '/assets/css/notifications.css';

$extra_css[] = $headerCssBase . '?v=' . @filemtime(APP_ROOT . $headerCssBase);
$extra_css[] = $headerCssNotif . '?v=' . @filemtime(APP_ROOT . $headerCssNotif);
$extra_js_head[] = '/assets/js/view-detect.js?v=' . (@filemtime(APP_ROOT . '/assets/js/view-detect.js') ?: time());

$extra_css = array_values(array_unique($extra_css));
$extra_js_head = array_values(array_unique($extra_js_head));
?>
<!doctype html>
<html lang="pt-BR" class="<?= header_e($html_class) ?>">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= header_e($page_title) ?></title>
<link rel="icon" sizes="32x32" href="/assets/img/favicon.png">
<link rel="icon" sizes="16x16" href="/assets/img/favicon.png">
    <link rel="icon"
        href="/assets/img/favicon.ico?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/img/favicon.ico') ?>">
    <link rel="shortcut icon"
        href="/assets/img/favicon.ico?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/img/favicon.ico') ?>">
    <link rel="apple-touch-icon"
        href="/assets/img/favicon.png?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/assets/img/favicon.png') ?>">

    <?php foreach ($extra_css as $href): ?>
        <link rel="stylesheet" href="<?= header_e(header_normalize_asset_path((string) $href)) ?>">
    <?php endforeach; ?>

    <?php foreach ($extra_js_head as $src): ?>
        <script src="<?= header_e(header_normalize_asset_path((string) $src)) ?>"></script>
    <?php endforeach; ?>
</head>

<body class="<?= header_e($html_class) ?>">

    <?php require APP_ROOT . '/app/partials/topbar.php'; ?>

    <div class="popper-tv-logo" id="popperTvLogo">
        <img src="/assets/img/logo.png" alt="Popper">
    </div>