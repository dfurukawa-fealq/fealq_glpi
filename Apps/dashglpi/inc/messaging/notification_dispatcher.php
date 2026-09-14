<?php

require_once __DIR__ . '/channel_interface.php';
require_once __DIR__ . '/teams_channel.php';
require_once __DIR__ . '/whatsapp_channel.php';
require_once __DIR__ . '/telegram_channel.php';
require_once __DIR__ . '/n8n_channel.php';

/**
 * Orquestrador central de mensageria (Step 6 do .Stack.md).
 *
 * Resolve a configuração efetiva dos canais (settings do plugin + fallback de
 * variáveis de ambiente) e despacha um alerta para todos os canais habilitados,
 * devolvendo o resultado por canal.
 */
class DashglpiNotificationDispatcher
{
    /** @var DashglpiMessagingChannel[] */
    private array $channels;

    public function __construct(?array $channels = null)
    {
        $this->channels = $channels ?? [
            new DashglpiTeamsChannel(),
            new DashglpiWhatsappChannel(),
            new DashglpiTelegramChannel(),
            new DashglpiN8nChannel(),
        ];
    }

    /** @return DashglpiMessagingChannel[] */
    public function channels(): array
    {
        return $this->channels;
    }

    public function channel(string $key): ?DashglpiMessagingChannel
    {
        foreach ($this->channels as $channel) {
            if ($channel->key() === $key) {
                return $channel;
            }
        }

        return null;
    }

    /**
     * Despacha o alerta para todos os canais habilitados.
     *
     * @return array<string,array> resultado por chave de canal
     */
    public function dispatch(array $alert, ?array $config = null): array
    {
        $config = $config ?? dashglpi_alerting_config();
        $results = [];
        foreach ($this->channels as $channel) {
            if (!$channel->isConfigured($config)) {
                $results[$channel->key()] = [
                    'ok' => false,
                    'channel' => $channel->key(),
                    'label' => $channel->label(),
                    'status' => 0,
                    'error' => 'Canal não configurado.',
                    'skipped' => true,
                ];
                continue;
            }

            try {
                $results[$channel->key()] = $channel->send($alert, $config);
            } catch (Throwable $e) {
                error_log('[DashGLPI][messaging] ' . $channel->key() . ': ' . $e->getMessage());
                $results[$channel->key()] = [
                    'ok' => false,
                    'channel' => $channel->key(),
                    'label' => $channel->label(),
                    'status' => 0,
                    'error' => $e->getMessage(),
                    'skipped' => false,
                ];
            }
        }

        return $results;
    }

    /** Envia para um único canal (usado pelo botão "Testar canal"). */
    public function sendTo(string $key, array $alert, ?array $config = null): array
    {
        $channel = $this->channel($key);
        if (!$channel) {
            return ['ok' => false, 'channel' => $key, 'status' => 0, 'error' => 'Canal desconhecido.', 'skipped' => false];
        }

        $config = $config ?? dashglpi_alerting_config();
        try {
            return $channel->send($alert, $config);
        } catch (Throwable $e) {
            return ['ok' => false, 'channel' => $key, 'label' => $channel->label(), 'status' => 0, 'error' => $e->getMessage(), 'skipped' => false];
        }
    }
}

/**
 * Config efetiva de alerta = settings persistidas ('alerting') com fallback nas
 * variáveis de ambiente do container (worker/dashglpi). Suporta override de
 * destinatários/limite por entidade.
 */
function dashglpi_alerting_config(?int $entitiesId = null): array
{
    $settings = function_exists('dashglpi_get_settings')
        ? dashglpi_get_settings('alerting')
        : dashglpi_alerting_defaults();

    $config = [
        'sla_threshold_minutes' => (int) ($settings['sla_threshold_minutes'] ?? 15),
        'warn_threshold_minutes' => (int) ($settings['warn_threshold_minutes'] ?? 0),
        // Trava de idade (PLAN-20260704-014): ver dashglpi_validate_alerting_settings() em inc/settings.php.
        'max_age_hours' => (int) ($settings['max_age_hours'] ?? 0),
        'since_date' => (string) ($settings['since_date'] ?? ''),
        'teams' => [
            'enabled' => !empty($settings['teams']['enabled']),
            'webhook_url' => (string) ($settings['teams']['webhook_url'] ?? dashglpi_env('TEAMS_WEBHOOK_URL', '')),
        ],
        'whatsapp' => [
            'enabled' => !empty($settings['whatsapp']['enabled']),
            'api_url' => (string) ($settings['whatsapp']['api_url'] ?? dashglpi_env('EVOLUTION_API_URL', '')) ?: (string) dashglpi_env('EVOLUTION_API_URL', ''),
            'api_key' => (string) ($settings['whatsapp']['api_key'] ?? '') ?: (string) dashglpi_env('AUTHENTICATION_API_KEY', dashglpi_env('EVOLUTION_API_KEY', '')),
            'instance' => (string) ($settings['whatsapp']['instance'] ?? '') ?: (string) dashglpi_env('EVOLUTION_API_INSTANCE', ''),
            'recipients' => $settings['whatsapp']['recipients'] ?? [],
        ],
        'telegram' => [
            'enabled' => !empty($settings['telegram']['enabled']),
            'bot_token' => (string) ($settings['telegram']['bot_token'] ?? '') ?: (string) dashglpi_env('TELEGRAM_BOT_TOKEN', ''),
            'chat_ids' => $settings['telegram']['chat_ids'] ?? array_filter([(string) dashglpi_env('TELEGRAM_CHAT_ID', '')]),
        ],
        // Canal n8n (PLAN-20260704-015): repassa o $alert bruto para um Webhook trigger do n8n.
        'n8n' => [
            'enabled' => !empty($settings['n8n']['enabled']),
            'webhook_url' => (string) ($settings['n8n']['webhook_url'] ?? dashglpi_env('N8N_ALERT_WEBHOOK_URL', '')),
        ],
        'entities' => is_array($settings['entities'] ?? null) ? $settings['entities'] : [],
        'groups' => is_array($settings['groups'] ?? null) ? $settings['groups'] : [],
    ];

    if ($entitiesId !== null) {
        $config = dashglpi_alerting_apply_entity_override($config, $entitiesId);
    }

    return $config;
}

/**
 * Aplica override por entidade (limite e destinatários específicos), se houver.
 */
function dashglpi_alerting_apply_entity_override(array $config, int $entitiesId): array
{
    $override = $config['entities'][(string) $entitiesId] ?? ($config['entities'][$entitiesId] ?? null);
    if (!is_array($override)) {
        return $config;
    }

    if (isset($override['sla_threshold_minutes']) && (int) $override['sla_threshold_minutes'] > 0) {
        $config['sla_threshold_minutes'] = (int) $override['sla_threshold_minutes'];
    }
    if (!empty($override['whatsapp_recipients'])) {
        $config['whatsapp']['recipients'] = $override['whatsapp_recipients'];
    }
    if (!empty($override['telegram_chat_ids'])) {
        $config['telegram']['chat_ids'] = $override['telegram_chat_ids'];
    }
    if (!empty($override['teams_webhook_url'])) {
        $config['teams']['webhook_url'] = (string) $override['teams_webhook_url'];
    }

    return $config;
}

/**
 * Webhook Teams configurado para um grupo/fila GLPI (roteamento do TaskWorker).
 * Retorna string vazia se o grupo não tiver override configurado.
 */
function dashglpi_alerting_group_teams_webhook(array $config, int $groupId): string
{
    $groups = is_array($config['groups'] ?? null) ? $config['groups'] : [];
    $override = $groups[(string) $groupId] ?? ($groups[$groupId] ?? null);
    if (!is_array($override)) {
        return '';
    }

    return (string) ($override['teams_webhook_url'] ?? '');
}

/** Defaults da configuração de alerta (namespace 'alerting'). */
function dashglpi_alerting_defaults(): array
{
    return [
        'sla_threshold_minutes' => 15,
        'warn_threshold_minutes' => 0,
        'max_age_hours' => 0,
        'since_date' => '',
        'teams' => ['enabled' => false, 'webhook_url' => ''],
        'whatsapp' => ['enabled' => false, 'api_url' => '', 'api_key' => '', 'instance' => '', 'recipients' => []],
        'telegram' => ['enabled' => false, 'bot_token' => '', 'chat_ids' => []],
        'entities' => [],
    ];
}
