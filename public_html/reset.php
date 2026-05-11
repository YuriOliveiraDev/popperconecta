<?php
declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');

if (function_exists('opcache_reset')) {
    var_dump(opcache_reset());
} else {
    echo 'opcache indisponivel';
}
