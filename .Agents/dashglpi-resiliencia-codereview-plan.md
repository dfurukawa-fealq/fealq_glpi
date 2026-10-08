# CodeReview DashGLPI - Plano de Resiliencia

Data: 2026-10-08

## Objetivo

Avaliar e fortalecer a resiliencia do DashGLPI em fluxos criticos de chamados, Kanban, filtros, administracao, imports, notificacoes e integracao com GLPI. O foco nao e mudar UX agora, mas reduzir telas em branco, falhas silenciosas, inconsistencias de permissao, perda de dados, respostas HTTP incorretas e erros parciais em operacoes de escrita.

## Escopo Avaliado

- Endpoints AJAX em `Apps/dashglpi/ajax`.
- Base de sessao, CSRF, JSON e acesso em `Apps/dashglpi/inc/bootstrap.php` e `Apps/dashglpi/inc/access.php`.
- Fluxos de chamados em `ticket_create`, `ticket_attendance`, `dashboard`, `kanban_tasks`.
- Frontend principal em `public/js/script.js`, `script.kanban.js`, `script.ticket-attendance.js` e `script.admin.js`.
- Persistencia local de filtros e estado de tela.
- Tratamento de erros, contratos JSON, autorizacao e concorrencia.

## Fora de Escopo Nesta Rodada

- Pentest completo.
- Performance tuning profundo de SQL.
- Redesenho visual da interface.
- Refatoracao funcional ampla.
- Mudancas no `docker-compose.local.yml`.

## Achados Principais

### P0 - Contrato de erro ainda inconsistente entre endpoints

Ha um helper central em `Apps/dashglpi/inc/ajax_endpoint.php` para POST, CSRF, try/catch e resposta padrao, mas o proprio comentario indica que ele ainda nao foi migrado para os endpoints existentes. Hoje varios endpoints fazem validacao manual, cada um com formato e status HTTP ligeiramente diferente.

Risco:
- Frontend pode interpretar erro como sucesso parcial.
- Mensagens mudam entre endpoints.
- Dificulta retry, telemetria e tratamento padrao de sessao expirada.

Evidencias:
- `Apps/dashglpi/inc/ajax_endpoint.php`
- `Apps/dashglpi/ajax/dashboard.php`
- `Apps/dashglpi/ajax/ticket_attendance.php`
- `Apps/dashglpi/ajax/users.php`, `entities.php`, `groups.php`, `profiles.php`, `categories.php`

Direcao:
- Padronizar envelope `{ ok: boolean, data|result?, error?, code?, trace_id? }`.
- Preservar status HTTP real: `400`, `401`, `403`, `404`, `409`, `422`, `500`.
- Criar helper tambem para GET seguro, nao apenas POST.

### P0 - RuntimeException usa codigo HTTP, mas varios catches descartam esse codigo

Algumas funcoes lancam `RuntimeException` com codigo relevante, por exemplo `409`, `422`, `403`, mas alguns endpoints capturam `Throwable` e devolvem sempre `403` ou `500`.

Risco:
- Conflito de revisao vira erro generico.
- Validacao do usuario pode parecer falha de permissao.
- Frontend nao consegue orientar recarregar, corrigir campo ou tentar novamente.

Evidencias:
- `Apps/dashglpi/inc/ticket_attendance_write.php`
- `Apps/dashglpi/ajax/ticket_attendance.php`
- `Apps/dashglpi/ajax/dashboard.php`

Direcao:
- Introduzir `dashglpi_http_status_from_exception(Throwable $e, int $fallback = 500): int`.
- Usar status entre `400..599` quando `$e->getCode()` for HTTP valido.
- Manter mensagem amigavel, mas logar classe/arquivo/linha no servidor.

### P0 - Frontend tem fetch descentralizado e falhas podem gerar tela em branco

Existe `dashglpiPostForm()` em `public/js/script.js`, mas o comentario indica que chamadas ainda nao foram migradas. Ha muitos `fetch()` diretos espalhados pelo JS.

Risco:
- Erro de JSON, HTML de login, 401/403, resposta vazia ou rede indisponivel derrubam fluxos diferentes de formas diferentes.
- A tela pode ficar vazia sem fallback.
- Falhas de sessao expirada nao tem tratamento global.

Evidencias:
- `Apps/dashglpi/public/js/script.js`
- `Apps/dashglpi/public/js/script.admin.js`
- `Apps/dashglpi/public/js/script.kanban.js`
- `Apps/dashglpi/public/js/script.ticket-attendance.js`

Direcao:
- Criar `dashglpiFetchJson(url, options)` com:
  - timeout via `AbortController`;
  - leitura defensiva de `Content-Type`;
  - tratamento de HTML/login;
  - erro tipado `{status, message, payload}`;
  - callback global para `401`/sessao expirada;
  - retry opcional somente para GET idempotente.

### P1 - Boa base de concorrencia em atendimento, mas precisa virar padrao para escritas criticas

O atendimento usa transacao, `SELECT ... FOR UPDATE`, revisao otimista e rollback. Isso e positivo e deve ser tratado como padrao para outros pontos sensiveis.

Risco:
- Escritas fora desse padrao podem sofrer race condition, dupla atualizacao, leitura obsoleta ou sucesso parcial.

Evidencias:
- `Apps/dashglpi/inc/ticket_attendance_write.php`
- `Apps/dashglpi/inc/kanban_tasks.php`
- `Apps/dashglpi/inc/ticket_create.php`

Direcao:
- Criar checklist de escrita critica:
  - permissao antes da mutacao;
  - entidade/escopo validado;
  - transacao quando houver mais de uma alteracao;
  - lock quando alterar ticket ou tarefa sensivel;
  - retorno com revisao atualizada.

### P1 - Acesso por pagina e entidade existe, mas precisa de teste de regressao

Ha funcoes fortes em `access.php` para pagina, escopo de entidade e visibilidade ITIL. Como filtros recentes separam Tarefas do Dash e GLPI, o risco e regressao em universo de entidades.

Risco:
- Usuario visualiza entidade fora do escopo.
- Filtro mostra opcoes indevidas.
- Kanban lista item que detalhe/atendimento depois bloqueia.

Evidencias:
- `Apps/dashglpi/inc/access.php`
- `Apps/dashglpi/inc/dashboard.class.php`
- `Apps/dashglpi/inc/ticket_create.php`

Direcao:
- Testes com usuario admin, usuario com uma entidade, usuario recursivo e usuario sem entidade padrao.
- Garantir que lista, detalhe, catalogos e filtros usam o mesmo universo de acesso.

### P1 - Catalogos GLPI e filtros precisam fallback vazio sem quebrar tela

Filtros avancados dependem de catalogos carregados do GLPI. Quando o catalogo falha, a UI precisa manter o Kanban usavel e mostrar estado degradado.

Risco:
- Botao de filtros nao abre.
- Modal abre sem controles.
- Filtro salvo no localStorage aponta para IDs que nao existem mais.

Evidencias:
- `Apps/dashglpi/ajax/dashboard.php?action=ticket_filter_catalog`
- `Apps/dashglpi/public/js/script.js`

Direcao:
- Sanitizar filtros salvos contra catalogo carregado.
- Mostrar "opcoes indisponiveis" sem quebrar modal.
- Botao de aplicar deve permanecer funcional.

### P1 - Imports/admin tem muito fluxo manual de POST e tokens

Imports e administracao parecem ter validacao propria e uso de preview tokens, mas o comportamento precisa ser uniformizado.

Risco:
- Preview expirado, CSV grande, falha parcial ou resposta do bridge podem ficar ambiguos.
- Usuario pode repetir import por incerteza.

Evidencias:
- `Apps/dashglpi/inc/glpi_admin_import.php`
- `Apps/dashglpi/inc/glpi_admin.php`
- `Apps/dashglpi/public/js/script.admin.js`

Direcao:
- Idempotency key por confirmacao de import.
- Relatorio de sucesso/falha por linha.
- Status HTTP correto para CSV invalido, token expirado e bridge indisponivel.

### P2 - Observabilidade ainda depende muito de `error_log`

O projeto registra erros com `error_log`, mas nao ha padrao visivel de `trace_id`, severidade, contexto do usuario, action e entidade.

Risco:
- Dificil correlacionar erro reportado pelo usuario com log do servidor.
- Erros intermitentes de rede/bridge/GLPI ficam caros de diagnosticar.

Direcao:
- Gerar `request_id` por requisicao AJAX.
- Retornar `trace_id` no JSON em erro 5xx.
- Logar: endpoint, action, usuario, entidade, status, duracao e excecao.
- Nao logar senha, token, conteudo sensivel ou anexos.

### P2 - Validacao automatizada ainda nao cobre contratos de resiliencia

Ha testes especificos para atendimento, mas faltam testes de contrato para JSON, permissao e falha controlada.

Risco:
- Refatoracoes de filtros/kanban quebram botao/modal/lista sem teste imediato.

Evidencias:
- `Apps/dashglpi/tests`

Direcao:
- Adicionar testes pequenos para:
  - endpoint retorna JSON em erro;
  - endpoint sem auth retorna `401`;
  - CSRF invalido retorna `403`;
  - filtro catalog falho nao quebra render;
  - conflito de revisao retorna `409`.

## Plano de Execucao

### Fase 1 - Contratos e seguranca de erro

Prioridade: P0

Tarefas:
- Criar helper comum para respostas JSON de erro com status derivado da excecao.
- Criar helper GET/POST unificado para endpoints AJAX.
- Migrar primeiro endpoints de maior uso:
  - `dashboard.php`
  - `kanban_tasks.php`
  - `ticket_attendance.php`
  - `ticket_create.php`
- Garantir que nenhuma excecao retorne HTML para chamadas AJAX.

Criterios de aceite:
- Todo endpoint migrado retorna `Content-Type: application/json`.
- Erros de permissao usam `403`.
- Erros de validacao usam `422`.
- Conflitos usam `409`.
- Falhas inesperadas usam `500` com mensagem segura.

### Fase 2 - Fetch resiliente no frontend

Prioridade: P0

Tarefas:
- Criar `dashglpiFetchJson()`.
- Migrar chamadas de Kanban, lista de tickets, filtro catalog, atendimento e admin para o helper.
- Tratar `401` com aviso de sessao expirada e opcao de recarregar/login.
- Tratar resposta HTML como erro de sessao/servidor, nao como JSON invalido cru.
- Adicionar fallback visual quando uma area falhar.

Criterios de aceite:
- Falha de rede nao deixa tela em branco.
- Botao de filtros permanece clicavel mesmo sem catalogo GLPI.
- Listas exibem estado de erro controlado e acao de tentar novamente.

### Fase 3 - Escritas criticas e concorrencia

Prioridade: P1

Tarefas:
- Mapear toda escrita em ticket, tarefa Dash, import e configuracao.
- Classificar operacoes em:
  - idempotentes;
  - requerem transacao;
  - requerem lock;
  - requerem preview/confirmacao.
- Reaproveitar o padrao de `dashglpi_attendance_mutate()` onde fizer sentido.
- Avaliar idempotency key para criacao de chamado e imports.

Criterios de aceite:
- Reenvio acidental nao duplica import/chamado quando houver chave de idempotencia.
- Atualizacao concorrente em ticket informa conflito, nao sobrescreve silenciosamente.
- Operacao parcialmente falha faz rollback quando aplicavel.

### Fase 4 - Entidades, filtros e escopo

Prioridade: P1

Tarefas:
- Criar matriz de usuarios:
  - admin;
  - usuario com uma entidade;
  - usuario com entidade padrao;
  - usuario com entidades recursivas;
  - usuario com restricao "Minhas Tarefas".
- Validar que filtros Dash e GLPI usam universos corretos.
- Sanitizar localStorage quando catalogo muda.
- Garantir que busca/lista/detalhe/atendimento concordam sobre acesso.

Criterios de aceite:
- Usuario nunca ve entidade fora do seu escopo.
- Filtros salvos invalidos sao descartados sem quebrar UI.
- Tarefas do Dash ficam fora dos filtros avancados GLPI, conforme regra atual.

### Fase 5 - Observabilidade

Prioridade: P2

Tarefas:
- Adicionar `request_id` por requisicao.
- Registrar duracao e status dos endpoints principais.
- Retornar `trace_id` em erros inesperados.
- Criar convencao de log sem dados sensiveis.

Criterios de aceite:
- Um erro reportado na UI pode ser localizado no log por `trace_id`.
- Logs nao exibem tokens, senhas, conteudo de anexo ou payload sensivel.

### Fase 6 - Testes de resiliencia

Prioridade: P2

Tarefas:
- Criar testes de contrato para endpoints AJAX.
- Criar testes JS de helpers de fetch com resposta JSON, HTML, timeout e status HTTP.
- Criar roteiro Playwright minimo para:
  - abrir Kanban;
  - abrir filtro;
  - alternar TabCards;
  - simular catalogo vazio;
  - confirmar que tela nao fica branca.

Criterios de aceite:
- `node --check` passa nos JS alterados.
- Testes de contrato cobrem pelo menos os endpoints P0.
- Roteiro visual cobre Kanban e modal de filtros.

## Matriz de Avaliacao de Execucao

| Item | Evidencia esperada | Status |
| --- | --- | --- |
| Helper de erro backend criado | Funcao comum em `inc/ajax_endpoint.php` ou novo helper | Concluido - `dashglpi_ajax_error_response`, status HTTP e `trace_id` 5xx |
| `dashboard.php` migrado | GET actions com JSON/status padrao | Parcial - catch central usa helper resiliente |
| `kanban_tasks.php` migrado | Erros de tarefa com status correto | Parcial - GET e POST usam helpers comuns |
| `ticket_attendance.php` preserva 409/422 | Conflito/validacao nao viram 403 generico | Parcial - catalogos/listas migrados; endpoints de escrita usam wrapper comum atualizado |
| Fetch helper frontend criado | `dashglpiFetchJson()` com timeout e Content-Type guard | Concluido - helper criado em `public/js/script.js` |
| Kanban nao fica branco em erro | Estado visual de erro + tentar novamente | Parcial - lista de chamados e tarefas Dash mostram retry; falta teste navegador |
| Filtros toleram catalogo vazio | Modal abre e aplicar funciona | Parcial - catalogo usa helper resiliente, aviso na modal e retry |
| LocalStorage sanitizado | IDs invalidos removidos apos catalogo carregar | Parcial - filtros avancados sao sanitizados apos catalogo GLPI carregar com sucesso |
| Testes de contrato AJAX | Casos 401/403/409/422/500 | Pendente |
| Trace ID em erro 5xx | ID na resposta e no log | Parcial - helper backend retorna/loga; falta padronizar todos endpoints |

## Execucao 2026-10-08 - Fase 1/P0 Parcial

Alteracoes aplicadas:
- `Apps/dashglpi/inc/ajax_endpoint.php`: adicionados helpers comuns para `request_id`, status HTTP derivado da excecao, resposta JSON de erro, sucesso JSON, metodo esperado e GET endpoint.
- `Apps/dashglpi/ajax/dashboard.php`: catch final passou a usar resposta resiliente central.
- `Apps/dashglpi/ajax/kanban_tasks.php`: listagem GET migrou para helper comum; POST preserva status HTTP pelo wrapper atualizado.
- `Apps/dashglpi/ajax/ticket_attendance.php`: catalogo GET migrou para helper comum e deixou de converter todo erro em `403`.
- `Apps/dashglpi/ajax/ticket_create.php`: dependencia de `ajax_endpoint.php` explicitada; erros GET usam helper comum.
- `Apps/dashglpi/public/js/script.js`: criado `dashglpiFetchJson()` com timeout, validacao de `Content-Type`, tratamento de `401`, HTML inesperado e `trace_id`.
- `Apps/dashglpi/public/js/script.js`: migrados lista de chamados, catalogo de filtros e tarefas Kanban.
- `Apps/dashglpi/public/js/script.kanban.js`: catalogo de entidades do modal de tarefa passou a usar o helper quando disponivel.
- `Apps/dashglpi/ajax/ticket_followup.php`: historico GET migrou para helper comum e deixou de converter todo erro em `403`.
- `Apps/dashglpi/ajax/ticket_satisfaction.php`: catalogo GET migrou para helper comum; nota ausente retorna `422`.
- `Apps/dashglpi/ajax/ticket_assignment.php`: GET usa helper comum; validacoes basicas retornam `400/422`.
- `Apps/dashglpi/ajax/ticket_cancel.php`, `ticket_solution.php`, `ticket_task.php`, `ticket_update.php`: dependencia de `ajax_endpoint.php` explicitada para evitar acoplamento implicito.

Validacao executada:
- `node --check Apps/dashglpi/public/js/script.js`: OK.
- `node --check Apps/dashglpi/public/js/script.kanban.js`: OK.
- `git diff --check`: OK, apenas avisos LF/CRLF.

Validacao pendente:
- `php -l` nao executado porque `php` nao esta disponivel no PATH deste ambiente.
- Testes manuais no navegador para sessao expirada, catalogo GLPI indisponivel e erro 5xx.

## Execucao 2026-10-08 - Fase 2/Filtros Resilientes Parcial

Alteracoes aplicadas:
- `Apps/dashglpi/front/dashboard.php`: adicionada area de status do catalogo na modal de filtros.
- `Apps/dashglpi/public/js/script.js`: adicionados estados `ticketFilterCatalogLoaded` e `ticketFilterCatalogError`.
- `Apps/dashglpi/public/js/script.js`: filtros avancados salvos no `localStorage` agora sao sanitizados contra o catalogo carregado com sucesso, removendo IDs que nao existem mais.
- `Apps/dashglpi/public/js/script.js`: falha ao carregar catalogo GLPI nao apaga filtros salvos; mostra aviso na modal e oferece `Tentar novamente`.
- `Apps/dashglpi/public/js/script.js`: controles avancados ficam desabilitados quando o catalogo esta indisponivel ou sem opcoes.
- `Apps/dashglpi/public/css/style.css`: estilo para o aviso resiliente do catalogo.

Validacao executada:
- `node --check Apps/dashglpi/public/js/script.js`: OK.
- `git diff --check`: OK, apenas avisos LF/CRLF.

Validacao pendente:
- Testar no navegador simulando falha de `ticket_filter_catalog`.
- Confirmar UX da modal em light/dark mode com catalogo indisponivel.

## Execucao 2026-10-08 - Fase 2/Retry Visual Parcial

Alteracoes aplicadas:
- `Apps/dashglpi/public/js/script.js`: erros de carregamento da lista de chamados agora renderizam uma acao `Tentar novamente`.
- `Apps/dashglpi/public/js/script.js`: falhas ao carregar tarefas do Dash no Kanban ficam registradas em `kanbanTasksLoadError`, sem derrubar os chamados GLPI.
- `Apps/dashglpi/public/js/script.kanban.js`: Kanban exibe aviso recuperavel quando as tarefas do Dash falham.
- `Apps/dashglpi/public/css/style.css`: estilos para erro recuperavel de tickets/Kanban.

Validacao executada:
- `node --check Apps/dashglpi/public/js/script.js`: OK.
- `node --check Apps/dashglpi/public/js/script.kanban.js`: OK.
- `git diff --check`: OK, apenas avisos LF/CRLF.

Validacao pendente:
- Testar no navegador com `ajax/kanban_tasks.php` falhando.
- Testar no navegador com `dashboard.php?action=tickets_list` falhando.

## Encerramento 2026-10-08

Status da execucao:
- Implementacao principal de resiliencia concluida para os pontos P0/P1 definidos nesta rodada:
  - contrato JSON/backend central;
  - preservacao de status HTTP em endpoints migrados;
  - `trace_id` em erro 5xx;
  - fetch resiliente no frontend;
  - fallback recuperavel em lista, Kanban e filtros;
  - sanitizacao de filtros avancados salvos apos carga de catalogo GLPI.
- Itens mantidos como validacao pendente porque dependem de ambiente com PHP/navegador:
  - `php -l` dos arquivos PHP alterados;
  - teste visual/manual da modal de filtros em light/dark mode;
  - simulacao real de falha em `ticket_filter_catalog`, `tickets_list` e `kanban_tasks`.

Validacao final executada:
- `node --check Apps/dashglpi/public/js/script.js`: OK.
- `node --check Apps/dashglpi/public/js/script.kanban.js`: OK.
- `node --check Apps/dashglpi/public/js/script.ticket-attendance.js`: OK.
- `node --check Apps/dashglpi/public/js/script.admin.js`: OK.
- `git diff --check`: OK, apenas avisos LF/CRLF.

Bloqueio conhecido:
- `php -l` nao executa nesta sessao porque `php` nao esta disponivel no PATH.

## Comandos de Validacao Sugeridos

```powershell
node --check Apps/dashglpi/public/js/script.js
node --check Apps/dashglpi/public/js/script.kanban.js
node --check Apps/dashglpi/public/js/script.ticket-attendance.js
node --check Apps/dashglpi/public/js/script.admin.js
```

Quando `php` estiver disponivel no ambiente:

```powershell
php -l Apps/dashglpi/ajax/dashboard.php
php -l Apps/dashglpi/ajax/kanban_tasks.php
php -l Apps/dashglpi/ajax/ticket_attendance.php
php -l Apps/dashglpi/ajax/ticket_create.php
php -l Apps/dashglpi/inc/ajax_endpoint.php
php -l Apps/dashglpi/inc/dashboard.class.php
```

## Ordem Recomendada

1. Corrigir preservacao de status HTTP das excecoes.
2. Migrar endpoints P0 para contrato JSON comum.
3. Criar fetch helper resiliente e migrar Kanban/filtros.
4. Sanitizar filtros salvos e catalogos GLPI.
5. Expandir testes de contrato.
6. Adicionar observabilidade com `trace_id`.

## Resultado Esperado

Ao final, o DashGLPI deve degradar de forma previsivel: quando GLPI, rede, sessao, permissao, catalogo ou concorrencia falharem, o usuario deve receber um estado claro e recuperavel, sem tela em branco, sem perda silenciosa de dados e sem duplicidade causada por reenvio.
