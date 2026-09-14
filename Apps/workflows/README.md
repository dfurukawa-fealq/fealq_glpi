# Apps/workflows

Workflows do n8n (PLAN-20260704-015/016), versionados via Git. Não é um plugin GLPI e não roda
código PHP — é um diretório de configuração/dados consumido pela instância `kawa_n8n`
(`docker-compose.yml`).

Governança: [`/.Agent/workflows/.Governance.md`](../../.Agent/workflows/.Governance.md) e
[`.Stack.md`](../../.Agent/workflows/.Stack.md).

## Convenção

Um workflow = dois arquivos com o mesmo nome-base, em `kebab-case`:

- `<nome-do-workflow>.json` — export nativo do n8n (menu **Download** na UI, ou
  `n8n export:workflow`). Importar de volta via menu **Import from File**.
- `<nome-do-workflow>.md` — gatilho, credenciais/variáveis esperadas (por nome, nunca o valor),
  e para qual canal/app o workflow reage.

**Nunca commitar credenciais.** n8n permite referenciar credenciais por nome/id no export sem
embutir o segredo — se um `.json` aparecer com token/senha/API key literal, é vazamento, não
configuração (ver critério de HALT em `.Governance.md`).

## Checklist antes de importar um workflow novo no `kawa_n8n`

1. Subir o serviço: `docker compose up -d n8n` (ver `docker-compose.yml`, PLAN-20260704-015).
2. Cadastrar na UI do n8n (**Credentials**) todas as credenciais listadas no `.md` do workflow.
3. Importar o `.json` (**Import from File**).
4. Ativar o workflow (toggle "Active") e copiar a URL do node Webhook, se houver.
5. Testar com um payload real antes de considerar em produção (ex.: `worker/send_test.php n8n`
   em `Apps/dashglpi`, para workflows que recebem o canal `n8n` do dispatcher).

## Workflows

| Arquivo | Status | Depende de |
|---|---|---|
| `sla-triagem-omnicanal` | ✅ Pronto para importar/testar | Canal `n8n` do dashglpi (já existe) |
| Onboarding B2B | 🔒 Backlog | Token OAuth2 Bearer da API nativa do GLPI 11 (não validado) |
| CSAT via WhatsApp | 🔒 Backlog | Webhook de entrada da Evolution API (não mapeado) |
| Menções por técnico | 🔒 Backlog | Bot Framework/Graph API para DM no Teams |
| Digest gerencial semanal | 🔒 Backlog | Usuário MySQL somente-leitura dedicado |
