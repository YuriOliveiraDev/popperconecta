# AGENTS.md

## Visão geral do projeto
Este projeto é o portal interno **Popper Conecta**, desenvolvido em PHP, com foco em centralização de informações estratégicas, dashboards gerenciais, integrações internas e ferramentas operacionais para a empresa.

O sistema roda localmente em ambiente XAMPP e é publicado em hospedagem Locaweb via FTP.

## Objetivo do agente
Ao trabalhar neste projeto, o agente deve:
- manter compatibilidade com a estrutura existente
- respeitar os padrões visuais e funcionais já adotados
- evitar refatorações desnecessárias
- priorizar soluções simples, funcionais e seguras
- preservar regras de negócio já definidas
- sempre considerar o impacto em ambiente desktop e notebook
- evitar criar dependências desnecessárias

---

## Stack principal
- PHP
- MySQL
- HTML
- CSS
- JavaScript puro
- jQuery (presente em páginas legadas)
- Chart.js
- Leaflet
- APIs do TOTVS via Relatórios Automáticos
- Microsoft Graph para envio de e-mails
- XAMPP em ambiente local
- Deploy por FTP em hospedagem Locaweb

---

## Estrutura e contexto do sistema
O sistema possui múltiplos módulos internos, incluindo:
- Home / Index
- Dashboards comerciais
- Dashboards financeiros
- Inadimplência
- Contas a pagar
- COMEX / Importações
- Geografia de vendas
- Popper Coins
- Áreas administrativas e de configuração

O portal deve manter aparência profissional, limpa e moderna, com foco em uso interno corporativo.

---

## Regras gerais de desenvolvimento

### 1. Autenticação e segurança
- Sempre respeitar autenticação existente
- Sempre usar `require_login()` quando necessário
- Sempre usar controle de permissão por dashboard ou módulo
- Nunca expor dados financeiros, estratégicos ou sensíveis sem verificação de acesso
- Nunca remover proteções existentes sem motivo explícito
- Usar prepared statements (PDO) para todas queries SQL
- Implementar CSRF token em formulários POST quando possível
- Considerar rate limiting em endpoints críticos (login, API)

### 2. Permissões
Quando envolver dashboards ou páginas restritas, considerar o uso de permissões como:
- `require_dash_perm('dash.financeiro.inadimplencia')`
- `require_dash_perm('dash.financeiro.contasp')`
- `require_dash_perm('dash.comercial.executivo')`

Se uma funcionalidade estiver em área sensível, assumir que deve respeitar permissões.

### 3. Estilo de código
- Preferir código claro e direto
- Evitar complexidade excessiva
- Evitar abstrações desnecessárias
- Reutilizar helpers existentes sempre que possível
- Manter consistência com o estilo já usado no projeto
- Comentar somente quando realmente agregar clareza
- Usar `try/catch` com `Throwable` para tratamento de erros
- Nunca expor stack traces em produção (`ini_set('display_errors', '0')`)

### 4. JavaScript
- Priorizar JavaScript puro quando possível
- jQuery já está presente em algumas páginas legadas (login, etc)
- Evitar soluções que dependam exclusivamente de frameworks modernos
- Sempre considerar compatibilidade com o código já existente

### 5. Front-end
- Priorizar layout desktop (1920px) e notebook (1366px-1600px)
- Garantir boa visualização em monitor Full HD
- Breakpoints principais:
  - Desktop: `min-width: 1600px`
  - Notebook: `max-width: 1600px`
  - Tablet: `max-width: 900px`
  - Mobile: `max-width: 700px`
- Evitar estouro de tabelas e cards em notebooks (usar `overflow-x: hidden` e colunas responsivas)
- Manter visual clean, corporativo e responsivo dentro do padrão atual
- Sempre que possível, usar skeleton loading ou placeholders antes da carga de dados

---

## Padrões visuais
- Interface limpa e corporativa
- Evitar excesso de margens e textos desnecessários
- Manter consistência com header, footer, cards e identidade já existente
- Usar carrosséis e animações com moderação
- Em dashboards, priorizar clareza de leitura sobre efeitos visuais
- Em telas de TV ou fullscreen, considerar uso de headers anti-cache e comportamento contínuo

---

## Integrações TOTVS
O sistema usa relatórios automáticos do TOTVS como fonte de dados, funcionando na prática como APIs internas.

### Relatórios importantes
- `000070` = Faturado
- `000071` = Carteira
- `000073` = Cadastro de clientes
- `000076` = Inadimplentes

### Diretrizes para uso das APIs
- Sempre preservar a lógica já validada pelo negócio
- Não alterar critérios de cálculo sem instrução explícita
- Tratar dados ausentes, nulos ou inconsistentes
- Considerar cache local quando já existir padrão implementado
- Cache padrão: arquivos JSON em `/tmp/` com TTL de 2 minutos
- Sempre formatar valores e datas no padrão brasileiro quando exibidos ao usuário

---

## Regras de negócio importantes

### Inadimplência
- A base principal de inadimplência vem do relatório `000076`
- A regra principal considera **vencimento**
- O cálculo de `% inadimplência` deve usar comparação com faturado do período
- O filtro `dias_min_atraso` é importante e deve ser respeitado
- Valor padrão comum: `3`
- Cruzamento de clientes usa chave `codigo|loja` (sem vendedor)
- Deve ser possível filtrar por:
  - vendedor
  - supervisor
  - faixa de atraso
  - valor mínimo
  - pesquisa textual

### Rankings e análises
- Pode haver rankings por vendedor e supervisor
- Clientes devem ser separados por vendedor quando necessário
- Faixas de atraso devem ser mantidas quando aplicável
- Indicadores de concentração e risco devem ser preservados se já existirem

### E-mail de aviso de inadimplência
- O envio usa Microsoft Graph
- O modal de envio deve respeitar exatamente os destinatários visíveis em tela
- Não adicionar destinatários ocultos
- O assunto pode ser gerado automaticamente
- O corpo deve manter aparência profissional
- Excluir `% Inad.` da tabela do e-mail quando essa for a regra vigente

---

## Funções e helpers importantes
Sempre procurar por helpers existentes antes de criar novos.

Exemplos de conceitos já usados no projeto:
- `db()` - conexão PDO
- `current_user()` - retorna usuário logado
- `require_login()` - verifica autenticação
- `require_dash_perm($perm)` - verifica permissão de dashboard
- autenticação por sessão
- helpers de permissão
- formatação de moeda BRL
- formatação de datas
- parsing de datas TOTVS em formato Ymd
- cache temporário de respostas (arquivos em `/tmp/`)
- carregamento dinâmico de dashboards a partir do banco

---

## Banco e catálogos
Existem estruturas de catálogo e permissões no sistema, incluindo conceitos como:
- `ADMIN_PERMISSION_CATALOG`
- `DASHBOARD_CATALOG`
- tabela `dashboards` com campos como:
  - `slug`
  - `name`
  - `icon`
  - `is_active`
  - `sort_order`

Sempre respeitar a lógica já usada no header e na navegação.

---

## Home / Portal / Comunicação interna
A home do Popper Conecta deve funcionar como hub principal de comunicação e acesso.

Pode incluir:
- avisos
- indicadores
- calendário do mês
- aniversariantes
- tempo de empresa
- atalhos para dashboards
- conteúdos em formato de destaque ou carrossel

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
- Evitar consultas ou chamadas desnecessárias
- Minimizar recarregamentos pesados
- Sempre considerar falhas das APIs externas
- Implementar fallback quando possível
- Não quebrar a página se uma API falhar
- Exibir mensagens amigáveis ao usuário quando necessário
- Usar cache local para dados que mudam infrequently (2 min default)

---

## Deploy e ambiente
### Ambiente local
- XAMPP
- pastas do projeto dentro do ambiente local do usuário

### Publicação
- GitHub Desktop pode ser usado no fluxo local
- publicação final pode ocorrer por FTP na Locaweb

Ao sugerir scripts, caminhos ou comandos, considerar ambiente Windows.

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
- Não assumir framework moderno se o projeto é PHP customizado
- Não migrar para jQuery em páginas novas
- Não sugerir React, Vue ou Node sem necessidade real
- Não quebrar permissões existentes
- Não esconder regras de negócio importantes
- Não remover compatibilidade com estrutura atual
- Não usar soluções que dependam de bibliotecas não instaladas
- Não inventar endpoints sem alinhamento com o que já existe
- Não expor erros de stack trace em produção

---

## Preferências do projeto
- Soluções práticas
- Código funcional
- Boa aparência visual
- Manutenção simples
- Integração com o que já existe
- Foco no negócio real da empresa
- Clareza para futuras alterações

---

## Instrução final ao agente
Sempre considerar que este é um sistema interno corporativo em produção ou próximo disso.
Antes de propor mudanças grandes, verificar se existe uma forma menor, mais segura e mais compatível com a estrutura atual.

Quando houver dúvida entre estética e funcionalidade, priorizar funcionalidade com boa apresentação.
Quando houver dúvida entre recriar tudo ou adaptar, priorizar adaptação.