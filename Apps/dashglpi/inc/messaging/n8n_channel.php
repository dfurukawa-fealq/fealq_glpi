<?php

require_once __DIR__ . '/channel_interface.php';

/**
 * n8n via Webhook trigger (PLAN-20260704-015). Repassa o mesmo `$alert` que já vai
 * para Teams/WhatsApp/Telegram — o roteamento/decisão fica dentro do workflow n8n
 * (Switch/If, VIP, etc.), não neste canal.
 *
 * Config esperada em $config['n8n']:
 *   ['enabled' => bool, 'webhook_url' => string]
 */
class DashglpiN8nChannel extends DashglpiMessagingChannelBase
{
    public function key(): string
    {
        return 'n8n';
    }

    public function label(): string
    {
        return 'n8n';
    }

    public function isConfigured(array $config): bool
    {
        $n8n = is_array($config['n8n'] ?? null) ? $config['n8n'] : [];
        return !empty($n8n['enabled'])
            && $this->isValidHttpUrl((string) ($n8n['webhook_url'] ?? ''));
    }

    public function send(array $alert, array $config): array
    {
        if (!$this->isConfigured($config)) {
            return $this->result(false, 0, 'Canal n8n não configurado.', true);
        }

        $webhook = (string) $config['n8n']['webhook_url'];

        $response = $this->httpPost(
            $webhook,
            $alert,
            ['Content-Type: application/json']
        );

        return $this->result($response['ok'], $response['status'], $response['error']);
    }
}
