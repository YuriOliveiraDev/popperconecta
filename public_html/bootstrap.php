<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

date_default_timezone_set('America/Sao_Paulo');

// Carrega segredos do arquivo local (gitignored). Deve vir antes de qualquer config.
$_envFile = APP_ROOT . '/app/config/env.php';
if (is_file($_envFile)) {
    require_once $_envFile;
}
unset($_envFile);

require_once APP_ROOT . '/app/core/helpers.php';
require_once APP_ROOT . '/app/core/db.php';
require_once APP_ROOT . '/app/core/auth.php';
require_once APP_ROOT . '/app/core/permissions.php';
require_once APP_ROOT . '/app/core/notifications.php';
require_once APP_ROOT . '/app/core/calendario.php';