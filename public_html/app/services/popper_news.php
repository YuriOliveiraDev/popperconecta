<?php
declare(strict_types=1);

if (!defined('POPPER_NEWS_UPLOAD_DIR')) {
    define('POPPER_NEWS_UPLOAD_DIR', APP_ROOT . '/uploads/popper-news');
}

if (!defined('POPPER_NEWS_META_FILE')) {
    define('POPPER_NEWS_META_FILE', POPPER_NEWS_UPLOAD_DIR . '/current.json');
}

function popper_news_default_pdf_path(): string
{
    return '/uploads/popper-news/Popper-News-atual.pdf';
}

function popper_news_upload_dir(): string
{
    return POPPER_NEWS_UPLOAD_DIR;
}

function popper_news_meta_file(): string
{
    return POPPER_NEWS_META_FILE;
}

function popper_news_public_path(): ?string
{
    $default = popper_news_default_pdf_path();
    $defaultAbs = APP_ROOT . $default;

    if (is_file($defaultAbs)) {
        return $default;
    }

    $metaFile = popper_news_meta_file();
    if (!is_file($metaFile)) {
        return null;
    }

    $raw = @file_get_contents($metaFile);
    if (!is_string($raw) || trim($raw) === '') {
        return null;
    }

    $decoded = json_decode($raw, true);
    $path = is_array($decoded) ? (string)($decoded['public_path'] ?? '') : '';
    if ($path === '') {
        return null;
    }

    $abs = APP_ROOT . $path;
    if (!is_file($abs)) {
        return null;
    }

    return $path;
}

function popper_news_public_url(): ?string
{
    $path = popper_news_public_path();
    if ($path === null) {
        return null;
    }

    $version = @filemtime(APP_ROOT . $path) ?: time();
    return $path . '?v=' . $version;
}

function popper_news_write_metadata(string $publicPath, string $originalName = ''): void
{
    $meta = [
        'public_path' => $publicPath,
        'original_name' => $originalName,
        'updated_at' => date('c'),
    ];

    $dir = popper_news_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível preparar a pasta do Popper News.');
    }

    $ok = @file_put_contents(
        popper_news_meta_file(),
        json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
    );

    if ($ok === false) {
        throw new RuntimeException('Não foi possível salvar os metadados do Popper News.');
    }
}

function popper_news_store_uploaded_pdf(array $file): string
{
    if (!isset($file['error']) || (int)$file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('Selecione um arquivo PDF para enviar.');
    }

    if ((int)$file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do PDF.');
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Upload inválido.');
    }

    $size = (int)($file['size'] ?? 0);
    $maxBytes = 100 * 1024 * 1024;
    if ($size <= 0 || $size > $maxBytes) {
        throw new RuntimeException('O PDF deve ter no máximo 100MB.');
    }

    $originalName = (string)($file['name'] ?? 'popper-news.pdf');
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        throw new RuntimeException('Formato inválido. Envie um arquivo PDF.');
    }

    $mime = strtolower((string)($file['type'] ?? ''));
    if ($mime !== '' && $mime !== 'application/pdf') {
        throw new RuntimeException('Tipo de arquivo inválido. Envie um PDF.');
    }

    $dir = popper_news_upload_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Não foi possível criar a pasta de PDFs.');
    }

    $publicPath = popper_news_default_pdf_path();
    $destination = APP_ROOT . $publicPath;

    if (!@move_uploaded_file($tmp, $destination)) {
        throw new RuntimeException('Não foi possível salvar o PDF.');
    }

    popper_news_write_metadata($publicPath, $originalName);

    return $publicPath;
}
