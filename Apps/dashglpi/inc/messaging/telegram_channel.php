<?php

require_once __DIR__ . '/channel_interface.php';

/**
 * Telegram via Bot API (sendMessage).
 * Canal adicional citado no cabeçalho do Step 6 do .Stack.md.
 *
 * Config esperada em $config['telegram']:
 *   [
 *     'enabled'   => bool,
 *     'bot_token' => string,
 *     'chat_ids'  => string[], // ou string separada por vírgula
 *   ]
 */
class DashglpiTelegramChannel extends DashglpiMessagingChannelBase
{
    public function key(): string
    {
        return 'telegram';
    }

    public function label(): string
    {
        return 'Telegram';
    }

    public function isConfigured(array $config): bool
    {
        $tg = is_array($config['telegram'] ?? null) ? $config['telegram'] : [];
        return !empty($tg['enabled'])
            && trim((string) ($tg['bot_token'] ?? '')) !== ''
            && !empty($this->chatIds($tg));
    }

    public function send(array $alert, array $config): array
    {
        if (!$this->isConfigured($config)) {
            return $this->result(false, 0, 'Canal Telegram não configurado.', true);
        }

        $tg = $config['telegram'];
        $endpoint = 'https://api.telegram.org/bot' . trim((string) $tg['bot_token']) . '/sendMessage';
        $text = $this->buildText($alert);
        $mention = $this->buildMentionPayload($alert);

        $lastStatus = 0;
        $errors = [];
        foreach ($this->chatIds($tg) as $chatId) {
            // PLAN-20260714-023 (Caminho A): menção real vai como mensagem própria,
            // ANTES do corpo do alerta — evita misturar 'entities' (exigido pra
            // text_mention) com 'parse_mode' (usado no corpo abaixo), que a API do
            // Telegram trata como mutuamente exclusivos no mesmo sendMessage.
            if ($mention !== null) {
                $mentionBody = ['chat_id' => $chatId, 'text' => $mention['text']];
                // Formato "@usuario": Telegram já auto-detecta a menção no texto puro —
                // 'entities' só entra quando é o text_mention por user_id numérico.
                if ($mention['entities'] !== null) {
                    $mentionBody['entities'] = $mention['entities'];
                }
                $mentionResponse = $this->httpPost($endpoint, $mentionBody, ['Content-Type: application/json']);
                if (!$mentionResponse['ok']) {
                    $errors[] = $chatId . ' (menção): ' . ($mentionResponse['error'] ?? 'erro');
                }
            }

            $response = $this->httpPost($endpoint, [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown',
                'disable_web_page_preview' => true,
            ], ['Content-Type: application/json']);
            $lastStatus = $response['status'];
            if (!$response['ok']) {
                $errors[] = $chatId . ': ' . ($response['error'] ?? 'erro');
            }
        }

        if ($errors) {
            return $this->result(false, $lastStatus, implode('; ', $errors));
        }

        return $this->result(true, $lastStatus);
    }

    /**
     * PLAN-20260714-023 (Caminho A): menção real automática do técnico atribuído,
     * só quando ele tem `assigned_to_telegram_user_id` cadastrado (degrada em
     * silêncio quando ausente — não é erro, é o caso normal de quem não cadastrou).
     * Texto 100% fixo/controlado por este método (nunca pelo template do usuário),
     * para o offset/length do `text_mention` ser sempre determinístico.
     *
     * Aceita 2 formatos de identidade (cadastrados em glpi_plugin_dashglpi_user_channel_ids):
     *  - "@usuario": o próprio Telegram já linka "@usuario" como menção clicável de
     *    forma automática — não precisa de 'entities'. Só funciona se a pessoa tiver
     *    username público configurado no Telegram.
     *  - dígitos (user_id numérico): precisa do MessageEntity "text_mention" explícito
     *    (offset/length) — funciona mesmo sem username público, mas é mais raro o
     *    técnico saber esse número de cabeça (normalmente descoberto via @userinfobot).
     */
    private function buildMentionPayload(array $alert): ?array
    {
        $telegramTarget = trim($this->str($alert, 'assigned_to_telegram_user_id'));
        if ($telegramTarget === '') {
            return null;
        }

        $name = trim($this->str($alert, 'assigned_to_name'));
        if ($name === '') {
            return null;
        }

        if ($telegramTarget[0] === '@') {
            return [
                'text' => $telegramTarget . ', 🔔 você foi atribuído a este chamado.',
                'entities' => null,
            ];
        }

        if (!ctype_digit($telegramTarget)) {
            return null;
        }

        // offset/length do Telegram contam unidades UTF-16, não caracteres Unicode
        // nem bytes — nome sempre no offset 0 pra não precisar medir nada antes dele
        // (ex.: emoji fora do BMP ocupa 2 unidades UTF-16, não 1).
        $nameUtf16Length = (int) (strlen(mb_convert_encoding($name, 'UTF-16LE', 'UTF-8')) / 2);

        return [
            'text' => $name . ', 🔔 você foi atribuído a este chamado.',
            'entities' => [[
                'type' => 'text_mention',
                'offset' => 0,
                'length' => $nameUtf16Length,
                'user' => ['id' => (int) $telegramTarget],
            ]],
        ];
    }

    /** @return string[] */
    private function chatIds(array $tg): array
    {
        $raw = $tg['chat_ids'] ?? ($tg['chat_id'] ?? []);
        if (is_scalar($raw)) {
            $raw = preg_split('/[\s,;]+/', (string) $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $item) {
            $id = trim((string) $item);
            if ($id !== '' && preg_match('/^-?\d+$/', $id) && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function buildText(array $alert): string
    {
        $templateLines = $alert['template_lines'] ?? null;
        if (is_array($templateLines) && $templateLines !== []) {
            return implode("\n", $templateLines);
        }

        $level = $this->str($alert, 'level', 'breach');
        $lines = [
            $this->levelEmoji($level) . ' *' . $this->escapeMd($this->levelLabel($level)) . '*',
            '',
            '*Chamado:* #' . $this->int($alert, 'ticket_id'),
            '*Título:* ' . $this->escapeMd($this->str($alert, 'title', '—')),
            '*Entidade:* ' . $this->escapeMd($this->str($alert, 'entity_name', '—')),
            '*Categoria:* ' . $this->escapeMd($this->str($alert, 'category', '—')),
        ];
        $eventAt = $this->str($alert, 'created_at_formatted');
        if ($eventAt !== '') {
            $lines[] = '*' . $this->escapeMd($this->str($alert, 'event_at_label', 'Ocorrido em')) . ':* ' . $this->escapeMd($eventAt);
        }
        $lines[] = '*Sem ação há:* ' . $this->int($alert, 'minutes_overdue') . ' min (limite ' . $this->int($alert, 'threshold') . ' min)';

        $url = $this->str($alert, 'url');
        if ($url !== '') {
            $lines[] = '';
            $lines[] = 'GLPI: ' . $url;
        }
        $dashboardUrl = $this->str($alert, 'dashboard_url');
        if ($dashboardUrl !== '') {
            $lines[] = 'DashGLPI: ' . $dashboardUrl;
        }

        return implode("\n", $lines);
    }

    private function escapeMd(string $text): string
    {
        return str_replace(['_', '*', '`', '['], ['\\_', '\\*', '\\`', '\\['], $text);
    }
}
