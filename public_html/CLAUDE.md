# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

**Popper Conecta** — portal interno corporativo da empresa Popper. PHP puro + MySQL, sem framework, rodando em XAMPP local e publicado via FTP na Locaweb.

## Environment

- Local: XAMPP no Windows (`C:\xampp\htdocs`)
- Timezone: `America/Sao_Paulo`
- `APP_ENV` em `app/config/config.php`: `'dev'` ou `'prod'` — controla qual banco e qual session name usar
- Publicação: FTP manual para Locaweb (não há CI/CD)
- Não há build, bundler, lint ou suite de testes

## Bootstrap pattern

Todo arquivo PHP de entrada segue este padrão obrigatório (antes de qualquer output HTML):

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';
```

`bootstrap.php` define `APP_ROOT`, timezone e carrega `helpers.php`, `db.php`, `auth.php`, `permissions.php`, `notifications.php` e `calendario.php`.

## Autenticação e controle de acesso

| Função | Uso |
|---|---|
| `require_login()` | Redireciona para `/login.php` se não autenticado |
| `require_admin_perm(string $perm)` | Exige `role === 'admin'` + permissão específica |
| `require_dash_perm(string $perm)` | Exige permissão de dashboard (qualquer role) |
| `current_user()` | Retorna array do usuário atual ou `null` |
| `user_can(string $perm)` | Verifica permissão granular |

Permissões são armazenadas no campo `permissions` da tabela `users` como JSON array de strings. Os catálogos canônicos estão em `app/core/permissions.php`:
- Admin: `admin.users`, `admin.comunicados`, `admin.rh`, `admin.metrics`
- Dashboards: `dash.comercial.*`, `dash.financeiro.*`, `dash.comex.*`

## Banco de dados

`db()` retorna um singleton PDO com `FETCH_ASSOC` e `ERRMODE_EXCEPTION`. Sempre usar prepared statements.

## Estrutura de uma página protegida

```php
<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php';

require_login();
require_dash_perm('dash.comercial.faturamento'); // se aplicável

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$u            = current_user();
$activePage   = 'dashboard';     // destaca item no menu
$page_title   = 'Título';
$html_class   = 'minha-page page';
$extra_css    = ['/assets/css/base.css?v=...'];
$extra_js_head = [];             // scripts no <head>

require_once APP_ROOT . '/app/layout/header.php';
?>

<!-- conteúdo da página -->

<?php require_once APP_ROOT . '/app/layout/footer.php'; ?>
```

`app/layout/header.php` lê as variáveis `$page_title`, `$html_class`, `$activePage`, `$current_dash`, `$extra_css`, `$extra_js_head` — defina-as antes de incluir.

## Endpoints AJAX (`api/`)

Arquivos em `api/` são endpoints chamados via fetch/XHR. Devem retornar JSON, começar com `bootstrap.php` + `require_login()`, e responder com `http_response_code()` adequado.

## Integrações externas

| Integração | Arquivo | Uso |
|---|---|---|
| TOTVS | `api/totvs/` | Relatórios automáticos via HTTP |
| Sólides | `app/integrations/solides.php` | API REST de RH/colaboradores |
| Pipefy | `app/integrations/pipefy-rh.php` | GraphQL para fluxos de RH |
| Microsoft Graph | `app/integrations/mail_graph.php` | Envio de e-mails transacionais |

Tokens e credenciais das integrações ficam em `app/config/config.php`, `config-pipefy.php`, `config-secrets.php` e `config-totvs.php`.

## Módulo Popper Coins

Tabelas centrais: `popper_coin_wallets` (saldo), `popper_coin_ledger` (lançamentos). Funções em `app/services/poppers_coins.php`:
- `apply_ledger()` — lançamento com transação própria (uso unitário)
- `apply_ledger_no_tx()` — lançamento sem abrir transação (uso em lotes)

## Assets

CSS e JS ficam em `assets/css/` e `assets/js/`. Cache-busting via `?v=` com `filemtime()`. Não há compilação — arquivos são servidos diretamente.

## Restrição mobile

`auth.php` bloqueia acesso mobile (via cookie `pc_view`) em rotas não listadas como `$mobileRouteAllowed`. Ao criar rotas novas que devem funcionar em mobile, adicionar o path nessa lista em `app/core/auth.php`.
