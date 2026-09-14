# DashGLPI — Dashboard Pro para GLPI 11

Dashboard avançado e independente para visualização de dados do GLPI, com interface glassmorphism, modo escuro/claro, modo TV e tema cyberpunk para inventário de ativos.

**Autor:** Fealq
**Licença:** GPLv3+
**Compatibilidade:** GLPI 11.0.x
**Repositório:** https://github.com/diberlanda95/dashglpi

---

## Funcionalidades

### Notificações Push de novos chamados

O DashGLPI possui Web Push real para avisar sobre novos chamados mesmo quando o
WebApp está em segundo plano. A inscrição é opcional por dispositivo e só é
aceita para usuário ativo com acesso à tela de Chamados. No envio, o worker
revalida o perfil técnico, o direito nativo de leitura de chamados e a entidade
do ticket; endpoints expirados são desativados automaticamente.

Configure no ambiente do `dashglpi` e do `dashglpi-worker`:

```env
DASHGLPI_PUSH_ENABLED=true
DASHGLPI_PUSH_VAPID_SUBJECT=mailto:admin@example.com
DASHGLPI_PUSH_VAPID_PUBLIC_KEY=<chave-publica>
DASHGLPI_PUSH_VAPID_PRIVATE_KEY=<chave-privada>
```

Para gerar o par uma única vez no ambiente Docker:

```bash
docker compose run --rm --no-deps dashglpi-worker php -r 'require "/var/www/html/php-vendor/autoload.php"; print_r(\Minishlink\WebPush\VAPID::createVapidKeys());'
```

As chaves VAPID devem ser geradas uma única vez e preservadas. Sem elas, o
dashboard permanece funcional e exibe o Push como indisponível, sem falhar o
worker de SLA.

### Visão Geral (Dashboard)
- **6 KPIs em tempo real:** Total de chamados, Em andamento, Taxa de conclusão, SLA vencido, Tempo médio de resolução, Reabertos
- **4 cards de detalhe:** Novos chamados, Com técnico, Aguardando, Resolvidos
- **3 gráficos interativos:**
  - Fluxo de Criação (linha, últimos 30 dias)
  - Top 5 Categorias (barra horizontal)
  - Abertos vs Solucionados (barra comparativa, últimos 6 meses)
- **Tabela de Atividade Recente** com os 5 últimos chamados

### Monitor de SLA (Demonstração)
- Contadores de SLA: Crítico, Atenção, No Prazo
- Lista de chamados próximos ao vencimento com countdown em tempo real
- *Nota: seção com dados de demonstração — não conectada ao SLA real do GLPI*

### Ranking de Técnicos (Gamificação)
- Ranking mensal baseado em chamados resolvidos
- Sistema de pontos (10 pts por chamado resolvido)
- Destaque visual para o Técnico do Mês
- Barras de progresso animadas

### Inventário de Ativos (Grid Cyberpunk)
- Grid de cards com estilo cyberpunk/neon
- Indicador visual de disco (barra de uso com cores por criticidade)
- Modal detalhado com: OS, CPU, RAM, Disco, Serial, Localização
- Detecção automática de ícone por tipo (desktop, laptop, servidor)

### Problemas e Manutenções (PLAN-20260709-019)
- **Objetos ITIL além do chamado:** Problema (`glpi_problems`) e Manutenção (objeto *Mudança* do GLPI, `glpi_changes`) entram no mesmo pipeline de leitura, sempre em **modo somente leitura** (ações de escrita continuam exclusivas dos chamados).
- **Registry central** em `inc/itil_types.php`: tabelas, atores, mapa de status e capacidades por objeto — nenhuma query hardcodeia tabelas desses objetos fora dele.
- **Visibilidade por direito GLPI:** só perfis com o direito nativo `problem`/`change` (bit de leitura) enxergam os novos objetos; perfis Helpdesk não os veem (espelha o back-office do GLPI).
- **Dashboard:** KPIs segmentados (em aberto / prazo vencido / solucionados) e séries mensais próprias — os números de chamados não mudam.
- **Lista/Kanban:** chips "Chamados | Problemas | Manutenções" na tela de atendimento; cards de Problema/Manutenção não são arrastáveis.
- **Detalhe e relatório de auditoria:** o modal e o `ticket-report.php` aceitam `?itemtype=problem|change` (whitelist), com seções condicionais (satisfação só em chamado; sem SLA nativo — prazo usa `time_to_resolve` quando preenchido).
- **Workers:** tarefas novas e soluções em Problema/Manutenção também disparam alertas; dedupe com `event_type` namespaceado (`task_assigned:problem` etc.) para não colidir com ids de chamados.
- **Regras dinâmicas:** novos gatilhos `new_task_problem` e `new_task_change` na tela de regras.

### Regras — Chamado Atribuído (PLAN-20260713-020)
- **Gatilho `ticket_assigned`:** dispara quando um chamado é atribuído a um técnico (detectado via `glpi_logs`, dedupe por `id` do log).
- **Menção no Teams:** a ação `teams` passa o nome/e-mail do técnico atribuído no payload (`dashglpiMention`); a menção real (`@usuário`) depende de um flow externo no Power Automate/Logic Apps que leia esse campo e monte o Adaptive Card — o Incoming Webhook nativo do Teams não suporta menção.

### Credenciais nomeadas de canal (PLAN-20260714-022)

- **Problema resolvido:** antes, toda ação de regra (Teams/WhatsApp/Telegram) dependia implicitamente da config global em **Canais de Alerta** — não dava pra ter, por exemplo, 2 bots de Telegram diferentes para 2 regras diferentes, nem configurar uma credencial sem sair da tela de Regras.
- **Catálogo de credenciais** (`glpi_plugin_dashglpi_channel_credentials`): entidades nomeadas e reaproveitáveis por canal (ex.: "Bot TI", "Evolution Fealq"), com CRUD completo (criar/editar/marcar como padrão/excluir) tanto na tela de **Regras** (botão "+ Nova credencial" em cada card de ação, sem sair da tela) quanto na tela de **Canais de Alerta** (seção "Credenciais nomeadas (Regras)" dentro de cada canal, com contagem de uso por credencial).
- **Resolução no dispatch:** cada ação de regra pode referenciar uma `credential_id`. Quando setada, o token/webhook/api_key da credencial nomeada sobrepõe o bloco de infra do canal antes do disparo. Sem `credential_id` (opção "Usar padrão"), a regra continua caindo no comportamento de sempre — a config global de **Canais de Alerta**.
- **Os 3 workers automáticos legados (SLA monitor, TaskWorker, SolutionWorker) não têm conceito de credencial nomeada** — eles continuam lendo exclusivamente a config global de **Canais de Alerta** (`dashglpi_alerting_config()`), sem alteração. É uma decisão deliberada (esses workers não têm o conceito de "regra" para associar uma credencial), não uma inconsistência: os campos de credencial em Canais de Alerta permanecem como a fonte que esses workers leem, mesmo depois da reforma da tela.
- **Migração automática:** na primeira execução pós-deploy, se a config global de um canal já tinha valor preenchido, uma credencial "Credencial padrão (migrada)" é criada automaticamente (idempotente) — nenhuma regra existente quebra nem precisa de reconfiguração manual.

### Menção real do técnico atribuído no Telegram (PLAN-20260714-023)

- **Diferente do Teams, o Telegram não precisa de flow externo:** a Bot API suporta menção real nativamente via `text_mention` no `sendMessage` — o DashGLPI monta a menção sozinho, sem Power Automate/Logic Apps.
- **Cadastro (@usuário ou ID numérico):** campo opcional "Telegram (@usuário ou ID)" na tela de usuário (Configurações → Usuários → Identidade), salvo numa tabela própria do plugin (`glpi_plugin_dashglpi_user_channel_ids`, `users_id` sem `FOREIGN KEY` real pra `glpi_users` — mesmo precedente já usado no log da SQL console). Só é editável depois que o usuário já existe (não faz parte do cadastro inicial de `glpi_users`, então não passa pelo bridge GLPI). Aceita **`@usuário`** (o que a maioria sabe de cabeça, normalizado com `@` na gravação) ou o **ID numérico** (descoberto falando com `@userinfobot`).
- **Menção automática (Caminho A):** quando o técnico atribuído (`ticket_assigned`) tem esse dado cadastrado, `DashglpiTelegramChannel::send()` manda **uma mensagem curta e fixa só com a menção**, separada do corpo normal do alerta — evita ter que calcular a posição do nome dentro de um template livre editado pelo usuário (frágil) e evita o conflito entre `parse_mode` e `entities` da API do Telegram (mutuamente exclusivos no mesmo `sendMessage`). Formato `@usuário`: o próprio Telegram já linka o texto como menção automaticamente (sem `entities`), mas só funciona se a pessoa tiver username público configurado. Formato ID numérico: usa o `MessageEntity` `text_mention` explícito — funciona mesmo sem username público.
- **Degradação:** técnico sem `telegram_user_id` cadastrado → mensagem sai normalmente, sem a menção extra, sem erro.
- **Schema pensado para o futuro:** a tabela usa uma coluna `channel` genérica (não só "telegram") — candidata natural pra também guardar o AAD Object ID do Teams e resolver a fragilidade do PLAN-20260713-020 (hoje a menção do Teams depende do e-mail do GLPI bater com o UPN do Azure AD). Não implementado ainda, só o schema já compatível.

### Recursos Gerais
- **Modo Escuro/Claro** com persistência via localStorage
- **Modo TV** com fullscreen e rotação automática entre seções (15s)
- **Atualização automática** dos dados a cada 60 segundos
- **Relógio** em tempo real no header
- **Painel de Notificações** (demonstração)

### WebApp instalável (PWA)

O DashGLPI também pode ser instalado como aplicativo no navegador. O dashboard publica um
manifest, ícones e um service worker que mantém os arquivos estáticos disponíveis em cache,
sem armazenar respostas AJAX ou páginas autenticadas.

Para instalar, abra o dashboard em uma conexão HTTPS (ou em `localhost`) e use a opção
**Instalar aplicativo** do navegador. No deploy como plugin, os caminhos são automaticamente
ajustados para `/plugins/dashglpi/`; no container standalone, continuam na raiz da aplicação.

### Importação CSV (cadastros administrativos)

Fluxo prévia → confirmação (mesmo padrão para todos os tipos), aceitando o CSV
**exportado pelo próprio GLPI** (separador `;`, cabeçalho PT-BR, BOM tolerado).
Idempotente: reimportar o mesmo arquivo resulta em "existente", nunca em duplicata.
Limites: 2.000 linhas por arquivo; envio ao bridge em blocos de 200.

| Tipo | Colunas obrigatórias | Colunas opcionais | Colunas ignoradas |
| --- | --- | --- | --- |
| Categorias ITIL | `Nome completo`, `Entidade` | — | — |
| Entidades | `Nome completo` (hierarquia com `>`; segmento da raiz é removido) | — | — |
| Grupos | `Nome completo` (ou `Nome`) | `Entidade`, `Recursivo`, `Comentários` | — |
| Usuários | `Usuário` (ou `Login`) | `Último nome`/`Sobrenome`, `Nome`, `E-mails`, `Telefone`, `Entidade`, `Perfil`, `Grupo`, `Ativo`, `Senha` | `Localização` |
| Perfis | `Nome` | `Interface` (Central/Helpdesk), `Comentários` | `ID`, `Perfil padrão`, `Última atualização` |

Os cabeçalhos aceitam os nomes da exportação PT-BR e EN do GLPI (`Users`, `Last name`,
`Complete name`, `Active`, …) — a exportação usa o idioma da sessão de quem exportou e
as colunas visíveis na lista naquele momento.

Regras de destaque na prévia: entidade não encontrada → importa na raiz com aviso
(`Raiz`); perfil não encontrado → linha em erro (não importa); usuário sem coluna
`Perfil` → usa o perfil selecionado no formulário de import (default Self-Service);
usuário sem coluna `Senha` → senha temporária forte gerada no bridge (nunca
exibida/logada); logins já existentes (inclusive na lixeira do GLPI) aparecem como
existentes e são pulados; perfil novo em CSV sem coluna `Interface` → aviso na prévia
e criação como Helpdesk (a exportação do GLPI não inclui essa coluna).

---

## Instalação

### Pré-requisitos
- GLPI 11.0.x instalado e funcionando
- Acesso de administrador ao GLPI

### Passos

1. Copie a pasta `dashglpi/` para o diretório de plugins do GLPI:
   ```bash
   cp -r dashglpi/ /usr/share/glpi/plugins/
   ```

2. Acesse o GLPI como administrador

3. Vá em **Configurar > Plugins**

4. Localize "Dashboard GLPI Pro" e clique em **Instalar**, depois **Ativar**

5. O dashboard aparecerá no menu **Assistência > Dashboard Pro**

> **Nota:** Se o ambiente usa OPcache com `validate_timestamps=Off`, reinicie o PHP-FPM após copiar os arquivos:
> ```bash
> pkill -USR2 php-fpm
> ```

---

## Estrutura de Arquivos

```
dashglpi/
├── setup.php                  # Registro do plugin, versão, hooks
├── hook.php                   # Install/uninstall, menu (redefine_menus)
├── front/
│   └── dashboard.php          # Página standalone (HTML completo, autenticada)
├── ajax/
│   └── dashboard.php          # Endpoint AJAX (4 actions, retorna JSON)
├── inc/
│   └── dashboard.class.php    # Backend: queries SQL via $DB->request()
├── public/
│   ├── css/
│   │   └── style.css          # Estilos (glassmorphism, cyberpunk, dark/light)
│   ├── js/
│   │   ├── script.js          # Lógica frontend (charts, modals, AJAX)
│   │   └── menu.js            # Script mínimo: força links em nova aba
│   └── vendor/
│       ├── css/
│       │   ├── bootstrap.min.css
│       │   └── fontawesome.min.css
│       ├── js/
│       │   └── chart.umd.min.js
│       └── webfonts/          # FontAwesome (woff2, ttf)
└── README.md
```

---

## Arquitetura

### Fluxo de Dados

```
Navegador (front/dashboard.php)
    │
    ├─ JS (script.js) ──→ AJAX GET /ajax/dashboard.php?action=...
    │                           │
    │                           └─→ PluginDashglpiDashboard (inc/)
    │                                   │
    │                                   └─→ $DB->request() (tabelas GLPI nativas)
    │
    └─ Resposta JSON ──→ Atualiza KPIs, gráficos, tabelas
```

### Design Standalone

O dashboard renderiza seu próprio HTML completo — **não usa** o header/footer do GLPI. Isso permite:
- Layout fullscreen sem interferência visual do GLPI
- Modo TV dedicado
- Tema independente (dark/light)

A autenticação é garantida via `Session::checkLoginUser()` no topo de cada arquivo PHP.

### Isolamento de CSS/JS

O plugin **não injeta CSS** globalmente no GLPI (evita conflitos visuais). Apenas um JS mínimo (`menu.js`, 10 linhas) é carregado globalmente para forçar o link do menu a abrir em nova aba.

Bibliotecas de terceiros (Bootstrap, FontAwesome, Chart.js) são servidas localmente a partir de `public/vendor/` — sem dependência de CDN.

---

## API AJAX

Endpoint: `plugins/dashglpi/ajax/dashboard.php`

Todas as requests requerem sessão GLPI autenticada (cookie de sessão).

| Action | Método | Descrição | Resposta |
|--------|--------|-----------|----------|
| `dashboard_data` | GET | KPIs + dados dos gráficos | `{cards_top, cards_bottom, charts}` |
| `get_ranking` | GET | Ranking de técnicos do mês | `[{name, avatar, tickets, points, color}]` |
| `tickets_list` | GET | Lista de chamados ativos (máx. 100) | `[{id, name, status, date, category, user_name}]` |
| `assets_list` | GET | Lista de computadores (máx. 100) | `[{id, name, serial, location, model, os_name, cpu, ram_total, disk_total, disk_free}]` |

### Exemplo de uso

```javascript
const response = await fetch('/plugins/dashglpi/ajax/dashboard.php?action=dashboard_data');
const data = await response.json();
// data.cards_top.total → total de chamados
// data.charts.trend_line → [{dia, total}, ...]
```

### Tratamento de erros

- **400** — Action inválida: `{"error": "Ação inválida."}`
- **500** — Erro interno: `{"error": "Erro interno do servidor."}` (detalhes logados no `php-errors.log`)
- **302** — Sem autenticação: redirect para login

---

## Queries e Performance

Todas as queries usam `$DB->request()` com arrays de critérios (obrigatório no GLPI 11). Nenhum SQL raw.

### Otimização de Assets (Bulk Queries)

O endpoint `assets_list` busca dados complementares (OS, CPU, RAM, Disco) usando **4 queries bulk** em vez de queries individuais por asset:

| Dado | Tabela | Estratégia |
|------|--------|------------|
| OS | `glpi_items_operatingsystems` + `glpi_operatingsystems` | LEFT JOIN, WHERE IN |
| CPU | `glpi_items_deviceprocessors` + `glpi_deviceprocessors` | INNER JOIN, WHERE IN |
| RAM | `glpi_items_devicememories` | SUM + GROUP BY |
| Disco | `glpi_items_disks` | SUM (total + free) + GROUP BY |

Resultado: **5 queries fixas** independente do volume de assets (vs. N*4+1 na abordagem ingênua).

### Respeito a Entidades

Todas as queries usam `getEntitiesRestrictCriteria()` para filtrar dados conforme a entidade ativa do usuário logado.

---

## Temas e Personalização

### Variáveis CSS principais

O tema é controlado por CSS custom properties definidas em `style.css`:

| Variável | Uso |
|----------|-----|
| `--bg-body` | Fundo da página |
| `--card-bg` | Fundo dos cards |
| `--glass-bg` | Fundo glassmorphism (translúcido) |
| `--text-main` | Texto principal |
| `--text-sec` | Texto secundário |
| `--text-muted` | Texto desabilitado |
| `--border-color` | Bordas |
| `--primary` | Cor primária (azul) |
| `--success` | Verde |
| `--warning` | Amarelo |
| `--danger` | Vermelho |
| `--neon-blue` | Azul neon (cyberpunk) |
| `--neon-pink` | Rosa neon (cyberpunk) |

### Modo Claro

Ativado via classe `light-mode` no `<body>`. As variáveis são sobrescritas para tons claros.

---

## Seções de Demonstração

As seguintes seções usam dados estáticos de exemplo (não conectadas a dados reais do GLPI):

- **Monitor de SLA** — dados fictícios com countdown. Marcado com badge "DEMONSTRAÇÃO".
- **Notificações** — lista fixa de 3 notificações. Marcado com badge "DEMO".

Estas seções servem como placeholder para futuras integrações com SLA real e sistema de notificações do GLPI.

---

## Desenvolvimento

### Requisitos para desenvolvimento
- GLPI 11.0.x com acesso de administrador
- Navegador moderno (Chrome, Firefox, Edge)

### Limpeza de cache após alterações

Se o ambiente usa OPcache:
```bash
# PHP
pkill -USR2 php-fpm

# Nginx (se aplicável)
rm -rf /var/lib/nginx/cache/public/*
```

### Adicionando novos endpoints AJAX

1. Criar o método estático em `inc/dashboard.class.php`
2. Adicionar o `case` no `switch` de `ajax/dashboard.php`
3. Chamar via `fetch()` no `script.js`

### Segurança

- `Session::checkLoginUser()` em todos os entry points PHP
- `escHtml()` no JS para sanitização de output (prevenção de XSS)
- Sem exposição de informações de debug em respostas de erro
- CSRF compliant (declarado via `$PLUGIN_HOOKS['csrf_compliant']`)
