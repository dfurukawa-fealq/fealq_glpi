# Plano de implementacao baseado no historico desde `add compose`

Documento gerado em 2026-09-30 a partir de `.Agents/docs/historico-atualizacoes-desde-add-compose.md`.

## Objetivo

Usar a trilha de alteracoes recentes do DashGLPI como base para executar uma nova solicitacao de forma controlada, preservando os fluxos ja implementados e reduzindo riscos relacionados a importacao CSV, chamados, anexos, templates, tema claro e troca de senha.

Este plano deve orientar a proxima etapa de desenvolvimento antes de qualquer alteracao funcional grande.

## Premissas

- O marco tecnico considerado e `7b1208f add compose`.
- Os arquivos CSV reais em `data/` foram removidos do workspace.
- A regra `data/*.csv` foi adicionada ao `.gitignore` para evitar novos dumps sensiveis.
- O documento historico continua sendo a referencia de contexto e auditoria.
- Alteracoes locais existentes em PHP/JS devem ser preservadas e analisadas antes de qualquer edicao nos mesmos arquivos.

## Frentes de trabalho

### 1. Preparacao e higiene do repositorio

1. Confirmar o estado atual com `git status --short`.
2. Separar mentalmente as alteracoes ja existentes das alteracoes da nova solicitacao.
3. Confirmar que nao existem arquivos CSV remanescentes com `rg --files -g "*.csv" -g "*.CSV"`.
4. Validar que `.Agents/docs/` esta versionavel e que `data/*.csv` esta ignorado.
5. Evitar abrir ou reproduzir conteudo de dumps sensiveis caso reaparecam.

Criterios de aceite:

- Nenhum CSV real permanece no workspace.
- O novo documento de plano aparece em `.Agents/docs/`.
- O `.gitignore` protege `data/*.csv`.

### 2. Leitura tecnica antes da nova implementacao

Antes de editar codigo, revisar os pontos mais provaveis de impacto conforme a natureza da nova solicitacao.

Para importacoes:

- `Apps/dashglpi/ajax/admin_batch_lib.php`
- `Apps/dashglpi/ajax/admin_bridge_common.php`
- `Apps/dashglpi/inc/glpi_admin_import.php`
- `Apps/dashglpi/ajax/computer_config.php`
- `Apps/dashglpi/ajax/monitor_config.php`
- `Apps/dashglpi/ajax/ticket_import_config.php`
- `Apps/dashglpi/public/js/script.admin.js`
- `Apps/dashglpi/front/dashboard.php`

Para chamados, anexos e atendimento:

- `Apps/dashglpi/ajax/ticket_create.php`
- `Apps/dashglpi/ajax/ticket_create_config.php`
- `Apps/dashglpi/inc/ticket_create.php`
- `Apps/dashglpi/inc/ticket_attendance_write.php`
- `Apps/dashglpi/public/js/script.ticket-create.js`
- `Apps/dashglpi/public/js/script.js`
- `Apps/dashglpi/front/dashboard.php`

Para layout, tema e navegacao:

- `Apps/dashglpi/inc/layout.php`
- `Apps/dashglpi/public/css/style.css`
- `Apps/dashglpi/public/js/script.js`
- `Apps/dashglpi/front/dashboard.php`
- `Apps/dashglpi/front/settings.php`

Para templates e notificacoes:

- `Apps/dashglpi/templates/`
- `Apps/dashglpi/templates/Fealq/`
- `Apps/dashglpi/inc/notification_followup_presets.php`

Criterios de aceite:

- Arquivos diretamente relacionados a nova solicitacao foram lidos antes da edicao.
- A implementacao segue padroes ja presentes, especialmente bridge administrativa, previa/confirmacao e validacoes server-side.

### 3. Estrategia de implementacao

A nova solicitacao deve seguir a arquitetura ja consolidada nos commits analisados.

Diretrizes:

- Preferir endpoints pequenos em `Apps/dashglpi/ajax/` para a borda HTTP.
- Manter regras de negocio em helpers ou arquivos de configuracao especificos, quando ja houver padrao equivalente.
- Para importacoes, manter sempre fluxo em duas fases: previa e confirmacao.
- Para uploads, validar tamanho, tipo, erro de upload e retorno do GLPI no servidor.
- Para telas, preservar navegacao existente por secoes do dashboard.
- Para perfil de usuario, respeitar as funcoes de acesso em `Apps/dashglpi/inc/access.php`.
- Para mensagens e templates, preservar identidade FEALQ e evitar duplicar HTML sem necessidade.

Criterios de aceite:

- A nova funcionalidade nao quebra importacoes existentes de computadores, monitores e tickets.
- A nova funcionalidade nao quebra criacao, detalhe, acompanhamento ou anexos de chamados.
- O comportamento para usuario comum e administrador permanece separado por permissao.
- Qualquer novo arquivo sensivel, dump ou amostra real fica fora do Git.

### 4. Validacao tecnica

Validacoes minimas apos a implementacao:

- `php -l` nos arquivos PHP alterados.
- `node --check` nos arquivos JS alterados.
- `git diff --stat` para revisar impacto geral.
- `git diff --check` para identificar whitespace problematico.
- Verificacao manual dos fluxos afetados no navegador quando a mudanca tocar UI.

Validacoes funcionais recomendadas:

- Importacao: gerar previa, conferir resumo, confirmar lote e validar resposta JSON.
- Chamados: criar chamado, anexar arquivo, abrir detalhe e registrar acompanhamento.
- Tema claro: alternar visualizacao e conferir contraste/legibilidade.
- Troca de senha: testar sucesso, erro de senha atual e validacao de nova senha.
- Templates: confirmar que variaveis dinamicas continuam preservadas.

Criterios de aceite:

- Sem erro de sintaxe nos arquivos alterados.
- Sem CSV real recriado no workspace.
- Fluxo principal afetado pela nova solicitacao testado com sucesso.
- Falhas conhecidas documentadas no fechamento da atividade.

### 5. Seguranca e dados sensiveis

Cuidados obrigatorios:

- Nao commitar CSVs reais, exports de GLPI, dumps de usuarios, tickets, grupos, entidades, computadores ou monitores.
- Usar apenas amostras anonimizadas quando for necessario documentar formato.
- Se a nova solicitacao exigir exemplo de CSV, criar `*.sample.csv` sem dados reais.
- Nao reproduzir conteudo sensivel em documentacao, logs ou mensagens de commit.
- Se dados sensiveis ja tiverem ido para remoto, tratar como incidente de historico Git, nao apenas como delecao local.

Criterios de aceite:

- `rg --files -g "*.csv" -g "*.CSV"` nao retorna dumps reais.
- Qualquer exemplo usado e anonimo e explicitamente marcado como amostra.

### 6. Ordem sugerida para a proxima solicitacao

1. Ler este plano e o documento historico.
2. Identificar qual frente sera impactada: importacao, chamados, layout, templates, senha ou infraestrutura.
3. Mapear arquivos relevantes com `rg`.
4. Ler os arquivos afetados antes de editar.
5. Implementar mudanca pequena e coesa.
6. Rodar validacoes de sintaxe e checks aplicaveis.
7. Revisar `git diff` e confirmar que nao houve vazamento de CSV/dados.
8. Atualizar documentacao em `.Agents/docs/` se a mudanca alterar comportamento operacional.

## Backlog derivado do historico

Itens que podem virar solicitacoes futuras:

- Criar exemplos anonimizados de CSV para cada importador.
- Documentar contrato de colunas esperado por computadores, monitores e tickets.
- Consolidar scripts auxiliares de importacao e remover diagnosticos temporarios da raiz quando nao forem mais necessarios.
- Criar uma rotina de validacao automatica para impedir commit de CSV real.
- Revisar se os templates duplicados entre raiz e `templates/Fealq/` podem ser reduzidos ou sincronizados.
- Criar checklist funcional de chamados com anexos para regressao manual.

## Definicao de pronto

Uma nova solicitacao baseada neste plano sera considerada pronta quando:

- O escopo estiver implementado nos arquivos corretos.
- As validacoes tecnicas tiverem sido executadas ou a impossibilidade estiver documentada.
- O diff nao incluir dados sensiveis.
- O comportamento alterado estiver descrito de forma curta no fechamento.
- A documentacao em `.Agents/docs/` estiver atualizada quando houver impacto operacional.
