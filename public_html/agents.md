# AGENTS.md

## Visão geral do projeto
Este projeto é o portal interno **Popper Conecta**, desenvolvido em PHP, com foco em centralização de informações estratégicas, dashboards gerenciais, integrações internas e ferramentas operacionais.

O sistema roda em ambiente XAMPP no Windows e a publicação final ocorre em hospedagem Locaweb, normalmente por FTP.

## Objetivo do agente
Ao trabalhar neste projeto, o agente deve:
- manter compatibilidade com a estrutura existente
- respeitar os padrões visuais e funcionais já adotados
- evitar refatorações desnecessárias
- priorizar soluções simples, funcionais e seguras
- preservar regras de negócio já definidas
- sempre considerar o impacto em desktop e notebook
- evitar criar dependências desnecessárias
- adaptar antes de recriar

---

## Stack principal
- PHP customizado
- MySQL
- HTML
- CSS
- JavaScript puro
- jQuery em telas legadas
- Chart.js
- Leaflet
- APIs TOTVS via Relatórios Automáticos
- Microsoft Graph para envio de e-mails
- XAMPP
- Deploy manual via FTP

### Dependências do projeto
- `composer.json` existe, mas hoje não há pacotes obrigatórios em `require`
- o projeto depende majoritariamente de código próprio em `app/`, `api/`, `dashboards/` e `assets/`

---

## Estrutura real do projeto
Pastas e pontos centrais encontrados na base atual:
- `bootstrap.php` carrega helpers centrais, banco, autenticação, permissões, notificações e calendário
- `app/core/` contém autenticação, permissões, helpers e conexão PDO
- `app/config/` contém configuração de ambiente, banco e TOTVS
- `app/layout/` e `app/helpers/` concentram layout e navegação
- `app/services/` contém serviços internos e integrações auxiliares
- `api/` reúne endpoints AJAX e integrações
- `dashboards/` contém as páginas protegidas dos painéis
- `admin/` reúne áreas administrativas e RH
- `coins/` contém o módulo Popper Coins
- `assets/` concentra CSS, JS, mapas e estáticos
- `cache/` e `app/cache/` armazenam arquivos temporários e catálogos locais
- `uploads/` guarda arquivos enviados
- `vendor/` pode existir localmente, mas não é a base da aplicação

O portal atual inclui, entre outros:
- Home / Index
- Dashboard Executivo
- Faturamento
- Insight Comercial
- Clientes
- Inadimplência
- Contas a Pagar
- COMEX / Importações
- Popper Coins
- Comunicados
- RH
- Métricas administrativas

---

## Fluxo de bootstrap e carregamento
O fluxo base observado no projeto é:
- entrada PHP chama `require_once $_SERVER['DOCUMENT_ROOT'] . '/bootstrap.php'`
- `bootstrap.php` define `APP_ROOT`, timezone `America/Sao_Paulo` e carrega os arquivos centrais
- `db()` escolhe credenciais conforme `APP_ENV`
- `start_session()` garante sessão com nome configurado e tenta login por remember me
- `current_user()` reidrata o usuário a partir do banco
- páginas protegidas chamam `require_login()` e, quando necessário, `require_dash_perm()` ou `require_admin_perm()`

Ao criar arquivo novo com acesso protegido, seguir esse padrão antes de qualquer saída HTML.

---

## Ambiente e operação
### Ambiente local
- XAMPP no Windows
- projeto em `C:\xampp\htdocs`
- timezone padrão: `America/Sao_Paulo`
- comandos e caminhos devem considerar PowerShell e estrutura Windows

### Ambientes configurados
- `APP_ENV` controla banco, sessão e URL base
- existem definições para `dev` e `prod`
- o código atual seleciona banco conforme `APP_ENV`

### Publicação
- fluxo local pode usar GitHub Desktop
- publicação final pode acontecer por FTP na Locaweb
- evitar sugestões de pipeline complexo se o ajuste puder ser resolvido com a estrutura atual

---

## Regras gerais de desenvolvimento

### 1. Autenticação e segurança
- sempre respeitar autenticação existente
- sempre usar `require_login()` quando necessário
- sempre usar controle de permissão por dashboard, módulo ou área administrativa
- nunca expor dados financeiros, estratégicos ou sensíveis sem verificação de acesso
- nunca remover proteções existentes sem motivo explícito
- usar prepared statements com PDO em todas as queries
- usar `try/catch` com `Throwable` ao redor de integrações, I/O e trechos sensíveis
- nunca expor stack trace em produção
- preferir mensagens amigáveis e logs internos quando houver falha
- em formulários POST novos, implementar CSRF token quando possível
- considerar rate limiting em endpoints críticos como login e APIs sensíveis

### 2. Permissões
Quando envolver dashboards ou páginas restritas, considerar permissões como:
- `require_dash_perm('dash.financeiro.inadimplencia')`
- `require_dash_perm('dash.financeiro.contasp')`
- `require_dash_perm('dash.comercial.executivo')`
- `require_dash_perm('dash.comercial.faturamento')`
- `require_dash_perm('dash.comercial.clientes')`
- `require_dash_perm('dash.comex.importacoes')`
- `require_admin_perm('admin.users')`
- `require_admin_perm('admin.comunicados')`
- `require_admin_perm('admin.rh')`
- `require_admin_perm('admin.metrics')`

Catálogos já existentes:
- `ADMIN_PERMISSION_CATALOG`
- `DASHBOARD_CATALOG`

Se a funcionalidade estiver em área sensível, assumir que deve respeitar permissão.

### 3. Estilo de código
- preferir código claro e direto
- evitar complexidade excessiva
- evitar abstrações desnecessárias
- reutilizar helpers existentes sempre que possível
- manter consistência com o estilo já usado no projeto
- comentar somente quando realmente agregar clareza
- não introduzir framework novo sem necessidade real
- preservar nomes, fluxos e padrões já aceitos pelo time

### 4. JavaScript
- priorizar JavaScript puro quando possível
- jQuery já está presente em páginas legadas
- evitar soluções que dependam exclusivamente de frameworks modernos
- sempre considerar compatibilidade com o código já existente
- manter loaders, mensagens e interações no padrão atual do portal

### 5. Front-end
- priorizar layout desktop `1920px` e notebook `1366px` a `1600px`
- garantir boa visualização em monitor Full HD
- breakpoints principais:
  - desktop: `min-width: 1600px`
  - notebook: `max-width: 1600px`
  - tablet: `max-width: 900px`
  - mobile: `max-width: 700px`
- evitar estouro de tabelas e cards em notebooks
- manter visual clean, corporativo e responsivo dentro do padrão atual
- usar skeleton loading ou placeholders quando fizer sentido

### 6. Compatibilidade mobile
- o projeto possui bloqueio de mobile por viewport em parte do fluxo
- há exceções para páginas como `login.php`, `index.php`, `dashboards/`, `coins/`, `admin/`, `api/`, `me.php` e rotas relacionadas
- não remover esse comportamento sem instrução explícita
- considerar TV Box e telas fullscreen como cenários válidos do portal

---

## Padrões visuais
- interface limpa e corporativa
- evitar excesso de margens e textos desnecessários
- manter consistência com header, footer, cards e identidade existente
- usar carrosséis e animações com moderação
- em dashboards, priorizar clareza de leitura sobre efeitos visuais
- em telas de TV ou fullscreen, considerar headers anti-cache e comportamento contínuo

---

## Integrações TOTVS
O sistema usa relatórios automáticos do TOTVS como fonte de dados, funcionando na prática como APIs internas.

### Relatórios importantes
- `000070` = faturado
- `000071` = carteira / agendado
- `000072` = contas a pagar
- `000073` = cadastro de clientes
- `000076` = inadimplentes
- `000080` = vendedores

### Processo atual da integração
- a base de configuração fica em `app/config/config-totvs.php`
- a função central é `callTotvsApi()`
- a montagem de URL usa `totvsConsultaUrl()` e `totvsMetricaUrl()`
- o retorno tenta normalizar encoding para UTF-8 antes do `json_decode`
- o código já trata HTTP code, erro cURL, tempo e erro de JSON

### Diretrizes para uso das APIs
- sempre preservar a lógica já validada pelo negócio
- não alterar critérios de cálculo sem instrução explícita
- tratar dados ausentes, nulos ou inconsistentes
- considerar cache local quando já existir padrão implementado
- sempre formatar valores e datas no padrão brasileiro quando exibidos ao usuário
- preferir reaproveitar helpers e endpoints já existentes antes de criar nova consulta
- não hardcodar credenciais novas em arquivos

### Cache e resiliência
- o projeto usa cache local em arquivos
- há uso real de `cache/` e `app/cache/`
- algumas rotas usam TTL curto, com padrão recorrente de `2 minutos`
- se a API externa falhar, a página não deve quebrar por completo

---

## Regras de negócio importantes

### Inadimplência
- a base principal de inadimplência vem do relatório `000076`
- a regra principal considera **vencimento**
- o cálculo de `% inadimplência` deve usar comparação com faturado do período
- o filtro `dias_min_atraso` é importante e deve ser respeitado
- valor padrão comum: `3`
- cruzamento de clientes usa chave `codigo|loja` sem vendedor
- deve ser possível filtrar por:
  - vendedor
  - supervisor
  - faixa de atraso
  - valor mínimo
  - pesquisa textual

### Rankings e análises
- pode haver rankings por vendedor e supervisor
- clientes devem ser separados por vendedor quando necessário
- faixas de atraso devem ser mantidas quando aplicável
- indicadores de concentração e risco devem ser preservados se já existirem

### E-mail de aviso de inadimplência
- o envio usa Microsoft Graph
- o modal de envio deve respeitar exatamente os destinatários visíveis em tela
- não adicionar destinatários ocultos
- o assunto pode ser gerado automaticamente
- o corpo deve manter aparência profissional
- excluir `% Inad.` da tabela do e-mail quando essa for a regra vigente

---

## Funções e helpers importantes
Sempre procurar por helpers existentes antes de criar novos.

Conceitos confirmados na base atual:
- `db()` retorna conexão PDO única
- `current_user()` retorna o usuário logado revalidado no banco
- `require_login()` verifica autenticação
- `require_admin()` valida papel administrativo
- `require_admin_perm($perm)` verifica permissão administrativa específica
- `require_dash_perm($perm)` verifica permissão de dashboard
- `user_can()` e `user_perms()` tratam permissões do usuário
- autenticação por sessão com `SESSION_NAME`
- remember me com tabela `user_remember_tokens`
- helpers de moeda e datas já existem em partes do sistema
- parsing de datas TOTVS em formato `Ymd`
- carregamento dinâmico de dashboards e permissões via catálogo

---

## Banco e catálogos
Existem estruturas de catálogo e permissões no sistema, incluindo:
- `ADMIN_PERMISSION_CATALOG`
- `DASHBOARD_CATALOG`
- tabela `dashboards` em fluxos dinâmicos do header e navegação

Campos citados e já usados no projeto:
- `slug`
- `name`
- `icon`
- `is_active`
- `sort_order`

Sempre respeitar a lógica já usada no header e na navegação.

---

## Home, portal e comunicação interna
A home do Popper Conecta funciona como hub principal de comunicação e acesso.

Pode incluir:
- avisos
- indicadores
- calendário do mês
- aniversariantes
- tempo de empresa
- atalhos para dashboards
- destaques e comunicados

Quando criar componentes da home:
- manter edição futura simples
- priorizar UX corporativa
- evitar excesso de informação visual
- respeitar a identidade do portal

---

## CMS e áreas editáveis
Quando desenvolver áreas editáveis:
- priorizar experiência simples para administradores
- evitar expor JSON ou estruturas técnicas ao usuário final
- usar modais claros e fáceis de fechar
- permitir edição intuitiva de título, subtítulo, texto e cards
- preservar conteúdo atual quando possível

---

## Performance e robustez
- evitar consultas ou chamadas desnecessárias
- minimizar recarregamentos pesados
- sempre considerar falhas das APIs externas
- implementar fallback quando possível
- não quebrar a página se uma API falhar
- exibir mensagens amigáveis ao usuário quando necessário
- usar cache local para dados de atualização frequente quando já houver padrão
- preferir aproveitar arquivos de cache e catálogos já existentes antes de recalcular tudo

---

## Processo esperado ao alterar o projeto
Antes de alterar qualquer funcionalidade:
- identificar o arquivo real já responsável pela tela, endpoint ou serviço
- verificar se já existe helper, endpoint ou consulta semelhante
- confirmar exigência de autenticação e permissão
- verificar impacto em desktop, notebook e fullscreen
- verificar se há cache envolvido
- preservar contratos existentes de resposta, nomes de parâmetros e filtros

Ao implementar:
- alterar o menor número de arquivos possível
- manter o layout e o fluxo já aceitos pelo negócio
- tratar erro com fallback claro
- evitar quebrar integrações TOTVS, Graph e permissões

Ao finalizar:
- validar sintaxe PHP e JS dos arquivos alterados
- revisar se a alteração não expõe dados sem login
- revisar se filtros, datas e moeda continuam no padrão brasileiro
- informar objetivamente o que foi alterado

---

## Convenções de resposta esperadas do agente
Ao editar ou gerar código para este projeto, o agente deve:
- entregar código pronto para uso
- evitar respostas genéricas
- respeitar o contexto já existente
- não sugerir reescrever tudo do zero sem necessidade
- explicar de forma objetiva onde cada trecho deve ser colocado
- quando possível, separar:
  - arquivo
  - local de inserção
  - código
  - observações

---

## O que evitar
- não assumir framework moderno se o projeto é PHP customizado
- não migrar para jQuery em páginas novas
- não sugerir React, Vue ou Node sem necessidade real
- não quebrar permissões existentes
- não esconder regras de negócio importantes
- não remover compatibilidade com estrutura atual
- não usar soluções que dependam de bibliotecas não instaladas
- não inventar endpoints sem alinhamento com o que já existe
- não expor erros de stack trace em produção
- não remover bloqueios, proteções e validações sem instrução explícita

---

## Preferências do projeto
- soluções práticas
- código funcional
- boa aparência visual
- manutenção simples
- integração com o que já existe
- foco no negócio real da empresa
- clareza para futuras alterações

---

## Instrução final ao agente
Sempre considerar que este é um sistema interno corporativo em produção ou muito próximo disso.

Antes de propor mudanças grandes, verificar se existe uma forma menor, mais segura e mais compatível com a estrutura atual.

Quando houver dúvida entre estética e funcionalidade, priorizar funcionalidade com boa apresentação.
Quando houver dúvida entre recriar tudo ou adaptar, priorizar adaptação.
