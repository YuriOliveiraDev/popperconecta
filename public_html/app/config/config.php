<?php
declare(strict_types=1);
define('APP_NAME', 'Popper Conecta');
/*
|--------------------------------------------------------------------------
| Ambiente atual
|--------------------------------------------------------------------------
| dev  = ambiente de desenvolvimento na Locaweb
| prod = ambiente de produção na Locaweb
|--------------------------------------------------------------------------
*/
define('APP_ENV', 'dev');
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
define('DB_USER_DEV', 'popper_dev');
define('DB_PASS_DEV', 'Ab@26462126');

/*
|--------------------------------------------------------------------------
| Banco PROD
|--------------------------------------------------------------------------
*/
define('DB_HOST_PROD', 'popperconecta.mysql.dbaas.com.br');
define('DB_PORT_PROD', 3306);
define('DB_NAME_PROD', 'popperconecta');
define('DB_USER_PROD', 'popperconecta');
define('DB_PASS_PROD', 'Ab@26462126');

define('PIPEFY_TOKEN', 'SEU_TOKEN_OU_SERVICE_ACCOUNT');
define('PIPEFY_PIPE_ID_RH', 123456789);


/*
|--------------------------------------------------------------------------
| email
|--------------------------------------------------------------------------
*/
define('GRAPH_TENANT_ID', 'c9617533-25d4-4f27-a17f-cf9b7593999c');

define('GRAPH_CLIENT_ID', 'ee898022-f9fe-483a-b4fd-f28143b35ed9');

define('GRAPH_CLIENT_SECRET', '');

define('GRAPH_SENDER_EMAIL', 'no-reply@popper.com.br');

