# Historico de atualizacoes desde `add compose`

Documento gerado em 2026-09-30 com base no historico Git a partir do commit `7b1208f add compose`, inclusive. O objetivo e registrar a trilha tecnica das mudancas recentes do DashGLPI e apontar a remocao dos CSVs que continham dados potencialmente sensiveis.

## Escopo analisado

Intervalo considerado:

- `7b1208f` - `add compose` - 2026-09-17 09:49
- `3657bb0` - `update templates fealq` - 2026-09-17 15:26
- `1920b1d` - `update url logo` - 2026-09-17 16:02
- `57d3baf` - `Update importacao` - 2026-09-18 14:26
- `3afd684` - `MELHORIAS EM IMPORTACOES` - 2026-09-22 16:03
- `70be41c` - `fix - mode light` - 2026-09-23 07:32
- `3574696` - `fix` - 2026-09-23 12:30
- `25345dc` - `fix - tela de chamados para user` - 2026-09-24 14:51
- `de1afda` - `fix anexo de documentos` - 2026-09-25 09:49
- `bebb3c5` - `add templates` - 2026-09-25 13:35
- `b45c817` - `fix - add` - 2026-09-28 10:47

## Linha do tempo tecnica

### 1. Infraestrutura Docker

O commit `7b1208f add compose` substituiu `docker-compose.bkp.yml` por `docker-compose.yml`. Este e o marco inicial da trilha analisada e indica a consolidacao do compose principal do projeto.

No commit `3afd684`, `docker-compose.local.yml` tambem recebeu ajuste pontual, em conjunto com as melhorias de importacao.

### 2. Templates e identidade visual FEALQ

Os commits `3657bb0`, `1920b1d`, `57d3baf` e `bebb3c5` concentraram alteracoes em templates HTML de notificacao do GLPI/DashGLPI.

Principais pontos:

- Atualizacao dos templates em `Apps/dashglpi/templates/` e `Apps/dashglpi/templates/Fealq/`.
- Inclusao temporaria de `New Ticket copy.html`, depois removida em `57d3baf`.
- Atualizacao de URL de logo e presets relacionados em `Apps/dashglpi/inc/notification_followup_presets.php`.
- Inclusao de templates de senha: `Password Forget.html`, `Password Initialization.html` e `Password expires alert.html`.

Impacto: padronizacao visual das mensagens enviadas pelo sistema, com reforco da identidade FEALQ e cobertura dos fluxos de senha.

### 3. Importacoes administrativas e importacao de ativos/chamados

O commit `3afd684 MELHORIAS EM IMPORTACOES` foi o maior ponto funcional da trilha. Ele adicionou e alterou endpoints, telas e helpers para importacao em lote.

Arquivos centrais criados:

- `Apps/dashglpi/ajax/computer_config.php`
- `Apps/dashglpi/ajax/computers.php`
- `Apps/dashglpi/ajax/monitor_config.php`
- `Apps/dashglpi/ajax/monitors.php`
- `Apps/dashglpi/ajax/ticket_import_config.php`
- `Apps/dashglpi/ajax/tickets_import.php`

Arquivos centrais alterados:

- `Apps/dashglpi/ajax/admin_batch_lib.php`
- `Apps/dashglpi/ajax/admin_bridge_common.php`
- `Apps/dashglpi/inc/glpi_admin.php`
- `Apps/dashglpi/inc/glpi_admin_import.php`
- `Apps/dashglpi/inc/layout.php`
- `Apps/dashglpi/front/dashboard.php`
- `Apps/dashglpi/front/dashboard.html`
- `Apps/dashglpi/public/js/script.admin.js`
- `Apps/dashglpi/public/js/script.js`

Comportamento observado no codigo:

- Criacao de fluxo de previa e confirmacao para CSV antes de gravar no GLPI.
- Rotas separadas para computadores, monitores e tickets.
- Uso de bridge administrativa para processar lotes.
- Normalizacao de campos e validacoes antes de inserir ou atualizar registros.
- Para tickets, o importador trata titulo, ID externo, entidade, solicitante, tecnico, categoria, datas, status, tipo, urgencia, impacto e prioridade.
- As telas de importacao foram expostas no dashboard, com upload CSV, previa, resumo e botao de confirmacao.

Arquivos auxiliares e diagnosticos adicionados no mesmo bloco:

- `.codex/computer_bridge_raw.php`
- `.codex/import_effective_test.php`
- `bridge_diag.php`
- `computer_import_runner.php`
- `import_effective_test.php`
- `import_effective_test_v2.php`
- `import_effective_test_v3.php`

Observacao de seguranca: neste mesmo commit entraram arquivos CSV em `data/`. Como esses arquivos podem carregar informacoes sensiveis de usuarios, tickets, ativos, grupos, entidades e localizacoes, eles foram removidos nesta atualizacao documental.

### 4. Ajustes de tema claro

O commit `70be41c fix - mode light` ajustou telas e scripts para melhorar a experiencia no modo claro.

Arquivos envolvidos:

- `Apps/dashglpi/front/dashboard.html`
- `Apps/dashglpi/front/dashboard.php`
- `Apps/dashglpi/front/settings.php`
- `Apps/dashglpi/front/sql-console.php`
- `Apps/dashglpi/public/js/script.js`
- `Apps/dashglpi/public/js/sql-console.js`

Impacto: consistencia visual e usabilidade em paginas administrativas e operacionais quando o tema claro esta ativo.

### 5. Tela de chamados, criacao e anexos

Os commits `3574696`, `25345dc` e `de1afda` evoluiram a experiencia de chamados.

Principais alteracoes:

- Ajustes no dashboard e nos estilos de chamados.
- Melhorias na tela de chamados para usuarios comuns/helpdesk.
- Ajustes em `Apps/dashglpi/ajax/ticket_create.php`, `Apps/dashglpi/ajax/ticket_create_config.php` e `Apps/dashglpi/inc/ticket_create.php`.
- Correcao do fluxo de anexo de documentos.
- Integracao com `Apps/dashglpi/inc/ticket_attendance_write.php`.

Impacto: criacao e acompanhamento de chamados ficaram mais consistentes, especialmente com envio de anexos e visualizacao por perfil de usuario.

### 6. Troca de senha e itens de layout

O commit `b45c817 fix - add` adicionou `Apps/dashglpi/ajax/change_password.php` e ajustou:

- `Apps/dashglpi/front/dashboard.php`
- `Apps/dashglpi/inc/dashboard.class.php`
- `Apps/dashglpi/inc/layout.php`
- `Apps/dashglpi/public/css/style.css`
- `Apps/dashglpi/public/js/script.js`

Impacto: inclusao de endpoint de troca de senha e ajustes de dashboard/layout para expor ou suportar o novo fluxo.

## CSVs removidos da trilha local

Os seguintes arquivos foram removidos do workspace por conterem, ou poderem conter, dados sensiveis:

- `data/COMPUTADOR.csv`
- `data/glpi.csv`
- `data/glpi_computador.csv`
- `data/glpi_entidade.csv`
- `data/glpi_grupos.csv`
- `data/glpi_localizacao.csv`
- `data/glpi_monitores.csv`
- `data/glpi_tickets.csv`
- `data/glpi_usuarios.csv`

Conteudo dos CSVs nao foi reproduzido neste documento.

## Riscos e recomendacoes

- Se estes CSVs ja foram enviados para remoto, a remocao do workspace e de um commit futuro nao elimina o historico remoto. Avaliar rotacao de credenciais/dados expostos e, se necessario, reescrita de historico com ferramenta apropriada.
- Regra `data/*.csv` adicionada ao `.gitignore` para reduzir o risco de novos dumps sensiveis entrarem por acidente.
- Manter arquivos de amostra anonimizados, quando necessario, com nomes como `*.sample.csv`.
- Revisar os scripts auxiliares de teste/importacao adicionados na raiz e em `.codex/` antes de publicar releases, pois podem conter caminhos, diagnosticos ou suposicoes de ambiente local.

## Resumo executivo

Desde `add compose`, o projeto passou por consolidacao Docker, padronizacao de templates FEALQ, grande expansao dos fluxos de importacao via CSV, ajustes de tema claro, melhorias de chamados/anexos e inclusao de troca de senha. A maior superficie de risco identificada foi a entrada de CSVs reais em `data/`, agora removidos do workspace nesta trilha de limpeza.

