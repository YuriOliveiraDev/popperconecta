<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__);
}

date_default_timezone_set('America/Sao_Paulo');

require_once APP_ROOT . '/app/core/helpers.php';
require_once APP_ROOT . '/app/core/db.php';
require_once APP_ROOT . '/app/core/auth.php';
require_once APP_ROOT . '/app/core/permissions.php';
require_once APP_ROOT . '/app/core/notifications.php';
require_once APP_ROOT . '/app/core/calendario.php';