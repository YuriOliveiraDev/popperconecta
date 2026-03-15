<?php
declare(strict_types=1);

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return APP_ROOT . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        return '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return base_url($path);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path): never
    {
        header('Location: ' . base_url($path));
        exit;
    }
}