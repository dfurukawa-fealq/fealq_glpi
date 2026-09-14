# SLA Triagem Omnicanal

**Status:** pronto para importar/testar (PLAN-20260704-016)
**Arquivo:** `sla-triagem-omnicanal.json`

## Gatilho

Webhook n8n (`POST /webhook/dashglpi-sla-triagem`), chamado pelo canal `n8n` do
`DashglpiNotificationDispatcher` (`Apps/dashglpi/inc/messaging/n8n_channel.php`,
PLAN-20260704-015) — qualquer alerta despachado pelos workers do dashglpi (SLA, nova tarefa,
solução pendente, regras dinâmicas) que tenha o canal `n8n` habilitado em
`front/settings.php?section=alerting` chega aqui.

O node Webhook responde **imediatamente** (modo "Immediately", HTTP 200) antes de processar o
roteamento — o dashglpi usa timeout de 10s por chamada de canal
(`inc/messaging/channel_interface.php:68`); se o workflow demorasse mais que isso para responder,
o dashglpi registraria erro mesmo com o n8n processando corretamente depois.

## Payload recebido (contrato do canal `n8n`)

Mesmo `$alert` que os canais Teams/WhatsApp/Telegram diretos já recebem (ver
`inc/messaging/channel_interface.php`): `ticket_id`, `title`, `category`, `entity_name`,
`entities_id`, `priority`, `minutes_overdue`, `threshold`, `level` (`warn`|`breach`|`info`),
`created_at`, `created_at_formatted`, `event_at_label` (opcional), `url`, `dashboard_url`, `note`
(opcional).

## O que o workflow faz

1. **Resolve VIP** (node Code) — decide `is_vip` comparando `entities_id` contra uma lista
   estática `VIP_ENTITIES` **editável direto no código do node** (não precisa reimportar o
   workflow para adicionar/remover um cliente VIP). Fail-safe: `entities_id` ausente/zero →
   nunca VIP.
2. **Build Teams Card** (node Code) — monta o mesmo formato de `MessageCard` usado pelo canal
   Teams direto do dashglpi (cor/emoji por `level`, fact "Ocorrido em" só se
   `created_at_formatted` vier preenchido, "Detalhe" só se `note` vier preenchido).
3. **Enviar Teams** (HTTP Request) — sempre dispara, independente de VIP.
4. **É VIP?** (IF) — só o ramo `true` continua.
5. **Build WhatsApp Text** + **Enviar WhatsApp VIP** (Code + HTTP Request) — só para chamados
   VIP, via Evolution API.

## Variáveis de ambiente esperadas (no container `kawa_n8n`)

Todas já adicionadas a `docker-compose.yml`/`.env.example` neste plano — **nenhuma credencial
literal no `.json`**:

| Variável | Uso |
|---|---|
| `TEAMS_WEBHOOK_URL` | URL do Incoming Webhook do Teams (node "Enviar Teams") |
| `EVOLUTION_API_URL` | Base da Evolution API (já reaproveitada do serviço `evolution-api` deste compose) |
| `EVOLUTION_API_INSTANCE` | Instância conectada da Evolution API |
| `AUTHENTICATION_API_KEY` | API key da Evolution API |
| `N8N_VIP_WHATSAPP_NUMBER` | Número (DDI+DDD+número, só dígitos) do plantonista/coordenador que recebe o alerta VIP |

Essas variáveis são lidas via expressions `{{ $env.NOME_DA_VARIAVEL }}` dentro dos nós HTTP
Request — isso exige `N8N_BLOCK_ENV_ACCESS_IN_NODE=false` no serviço `n8n` (já configurado em
`docker-compose.yml`, decisão deliberada documentada lá: mesma rede de confiança interna já usada
pelo restante do ecossistema).

## Checklist de import

1. `docker compose up -d n8n` (garanta que `TEAMS_WEBHOOK_URL`/`EVOLUTION_API_*`/
   `N8N_VIP_WHATSAPP_NUMBER` estão preenchidos no `.env` real antes de subir).
2. Importar `sla-triagem-omnicanal.json` (**Import from File** na UI do n8n).
3. Abrir cada node e conferir que não há indicador de erro/"issue" — este JSON foi escrito à mão
   (não exportado de uma instância n8n real), então **revise node a node antes do primeiro
   teste em produção**, especialmente os nós `HTTP Request` e `IF`.
4. Editar `VIP_ENTITIES` no node "Resolve VIP" com os `entities_id` reais que devem ser tratados
   como VIP (IDs de entidade do GLPI — ver `front/settings.php` no dashglpi, ou consultar
   `glpi_entities`).
5. Ativar o workflow (toggle "Active") e copiar a URL do node Webhook.
6. Configurar essa URL como `webhook_url` do canal **n8n** em
   `Apps/dashglpi/front/settings.php?section=alerting` (toggle "n8n" habilitado).
7. Testar: `worker/send_test.php n8n` (dentro do container `kawa_dashglpi_worker`) ou o botão
   "Testar n8n" da UI do dashglpi — conferir a execução na aba "Executions" do n8n.

## Limitação conhecida desta v1

A lista de VIP é estática (hardcoded no node), sem UI própria — para o volume atual de clientes
é suficiente; se crescer, vale puxar de uma fonte dinâmica (fora do escopo deste plano).
