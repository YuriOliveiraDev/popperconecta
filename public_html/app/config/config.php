<?php
declare(strict_types=1);
define('APP_NAME', 'Popper Conecta');
/*
|--------------------------------------------------------------------------
| Ambiente atual
|--------------------------------------------------------------------------
| dev  = ambiente de desenvolvimento local
| prod = ambiente de produção na Locaweb
|--------------------------------------------------------------------------
*/
define('APP_ENV', 'prod');
/*
|--------------------------------------------------------------------------
| Sessão
|--------------------------------------------------------------------------
*/
define('SESSION_NAME', APP_ENV === 'dev' ? 'POPPERSESSID_DEV' : 'POPPERSESSID');

define('APP_URL', APP_ENV === 'dev'
  ? 'http://localhost'
  : 'https://popperconecta.com.br'
);

/*
|--------------------------------------------------------------------------
| Banco DEV
|--------------------------------------------------------------------------
*/
define('DB_HOST_DEV', 'popper_dev.mysql.dbaas.com.br');
define('DB_PORT_DEV', 3306);
define('DB_NAME_DEV', 'popper_dev');
define('DB_USER_DEV', (string) getenv('DB_USER_DEV'));
define('DB_PASS_DEV', (string) getenv('DB_PASS_DEV'));

/*
|--------------------------------------------------------------------------
| Banco PROD
|--------------------------------------------------------------------------
*/
define('DB_HOST_PROD', 'popperconecta.mysql.dbaas.com.br');
define('DB_PORT_PROD', 3306);
define('DB_NAME_PROD', 'popperconecta');
define('DB_USER_PROD', (string) getenv('DB_USER_PROD'));
define('DB_PASS_PROD', (string) getenv('DB_PASS_PROD'));

/*
|--------------------------------------------------------------------------
| E-mail (Microsoft Graph)
|--------------------------------------------------------------------------
*/
define('GRAPH_TENANT_ID',     (string) getenv('GRAPH_TENANT_ID'));
define('GRAPH_CLIENT_ID',     (string) getenv('GRAPH_CLIENT_ID'));
define('GRAPH_CLIENT_SECRET', (string) getenv('GRAPH_CLIENT_SECRET'));
define('GRAPH_SENDER_EMAIL',  'no-reply@popper.com.br');
