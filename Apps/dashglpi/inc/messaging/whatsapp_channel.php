<?php

require_once __DIR__ . '/channel_interface.php';

/**
 * WhatsApp via Evolution API (container kawa_evolutionapi na fealq-network).
 * Alertas críticos móveis (Step 6.B do .Stack.md).
 *
 * Config esperada em $config['whatsapp']:
 *   [
 *     'enabled'    => bool,
 *     'api_url'    => string,   // ex.: http://kawa_evolutionapi:8080
 *     'api_key'    => string,   // AUTHENTICATION_API_KEY
 *     'instance'   => string,   // nome da instância conectada
 *     'recipients' => string[], // números destino (E.164 sem '+', ex.: 5511999998888)
 *   ]
 */
class DashglpiWhatsappChannel extends DashglpiMessagingChannelBase
{
    public function key(): string
    {
        return 'whatsapp';
    }

    public function label(): string
    {
        return 'WhatsApp (Evolution)';
    }

    public function isConfigured(array $config): bool
    {
        $wa = is_array($config['whatsapp'] ?? null) ? $config['whatsapp'] : [];
        return !empty($wa['enabled'])
            && $this->isValidHttpUrl((string) ($wa['api_url'] ?? ''))
            && trim((string) ($wa['api_key'] ?? '')) !== ''
            && trim((string) ($wa['instance'] ?? '')) !== ''
            && !empty($this->recipients($wa));
    }

    public function send(array $alert, array $config): array
    {
        if (!$this->isConfigured($config)) {
            return $this->result(false, 0, 'Canal WhatsApp não configurado.', true);
        }

        $wa = $config['whatsapp'];
        $endpoint = rtrim((string) $wa['api_url'], '/')
            . '/message/sendText/' . rawurlencode((string) $wa['instance']);
        $headers = [
            'Content-Type: application/json',
            'apikey: ' . (string) $wa['api_key'],
        ];
        $text = $this->buildText($alert);

        $lastStatus = 0;
        $errors = [];
        foreach ($this->recipients($wa) as $number) {
            $response = $this->httpPost($endpoint, [
                'number' => $number,
                'text' => $text,
            ], $headers);
            $lastStatus = $response['status'];
            if (!$response['ok']) {
                $errors[] = $number . ': ' . ($response['error'] ?? 'erro');
            }
        }

        if ($errors) {
            return $this->result(false, $lastStatus, implode('; ', $errors));
        }

        return $this->result(true, $lastStatus);
    }

    /** @return string[] */
    private function recipients(array $wa): array
    {
        $raw = $wa['recipients'] ?? [];
        if (is_string($raw)) {
            $raw = preg_split('/[\s,;]+/', $raw) ?: [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $numbers = [];
        foreach ($raw as $item) {
            $digits = preg_replace('/\D+/', '', (string) $item);
            if ($digits !== '' && strlen($digits) >= 10 && !in_array($digits, $numbers, true)) {
                $numbers[] = $digits;
            }
        }

        return $numbers;
    }

    private function buildText(array $alert): string
    {
        $templateLines = $alert['template_lines'] ?? null;
        if (is_array($templateLines) && $templateLines !== []) {
            return implode("\n", $templateLines);
        }

        $level = $this->str($alert, 'level', 'breach');
        $threshold = $this->int($alert, 'threshold');
        $lines = [
            $this->levelEmoji($level) . ' *' . $this->levelLabel($level) . '*',
            '',
            '*Chamado:* #' . $this->int($alert, 'ticket_id'),
            '*Título:* ' . $this->str($alert, 'title', '—'),
            '*Entidade:* ' . $this->str($alert, 'entity_name', '—'),
            '*Categoria:* ' . $this->str($alert, 'category', '—'),
        ];
        $eventAt = $this->str($alert, 'created_at_formatted');
        if ($eventAt !== '') {
            $lines[] = '*' . $this->str($alert, 'event_at_label', 'Ocorrido em') . ':* ' . $eventAt;
        }
        $lines[] = $threshold > 0
            ? '*Sem ação há:* ' . $this->int($alert, 'minutes_overdue') . ' min (limite ' . $threshold . ' min)'
            : '*Detectado há:* ' . $this->int($alert, 'minutes_overdue') . ' min';
        $note = $this->str($alert, 'note');
        if ($note !== '') {
            $lines[] = '*Detalhe:* ' . $note;
        }

        $url = $this->str($alert, 'url');
        if ($url !== '') {
            $lines[] = '';
            $lines[] = '🔗 GLPI: ' . $url;
        }
        $dashboardUrl = $this->str($alert, 'dashboard_url');
        if ($dashboardUrl !== '') {
            $lines[] = '🔗 DashGLPI: ' . $dashboardUrl;
        }

        return implode("\n", $lines);
    }
}
