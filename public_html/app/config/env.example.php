<?php
declare(strict_types=1);

// =============================================================
// TEMPLATE DE SEGREDOS — este arquivo É versionado no git.
// Copie para env.php e preencha com os valores reais.
// NUNCA coloque valores reais aqui.
// =============================================================

// Banco DEV
putenv('DB_USER_DEV=');
putenv('DB_PASS_DEV=');

// Banco PROD
putenv('DB_USER_PROD=');
putenv('DB_PASS_PROD=');

// Microsoft Graph (envio de e-mail)
putenv('GRAPH_TENANT_ID=');
putenv('GRAPH_CLIENT_ID=');
putenv('GRAPH_CLIENT_SECRET=');

// Pipefy
putenv('PIPEFY_TOKEN_COMEX=');
putenv('PIPEFY_TOKEN_RH=');

// TOTVS
putenv('TOTVS_API_USER=');
putenv('TOTVS_API_PASS=');

// Sólides
putenv('SOLIDES_TOKEN=');

// Tokens internos
putenv('POPPER_INTERNAL_AGENT_TOKEN=');
putenv('POPPER_API_TOKEN=');
