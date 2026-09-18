<?php

/**
 * DashGLPI - Camada de mensageria (Step 6 do .Stack.md).
 *
 * Contrato comum a todos os canais de alerta (Teams, WhatsApp, Telegram).
 *
 * Formato do "alert" (array associativo) consumido por send():
 *   [
 *     'ticket_id'       => int,
 *     'title'           => string,
 *     'category'        => string,
 *     'entity_name'     => string,
 *     'priority'        => int,     // 1..6 (GLPI)
 *     'minutes_overdue' => int,     // quanto passou da janela limite
 *     'threshold'       => int,     // janela limite configurada (min)
 *     'level'           => string,  // 'warn' | 'breach'
 *     'created_at'          => string, // opcional (PLAN-20260704-014) — data/hora bruta do evento
 *     'created_at_formatted'=> string, // opcional — data/hora formatada (d/m/Y H:i) exibida no canal
 *     'event_at_label'      => string, // opcional — rótulo do fact/linha de data (default 'Ocorrido em')
 *     'url'             => string,  // link para abrir o chamado no GLPI
 *     'dashboard_url'   => string,  // link para abrir o chamado no DashGLPI (opcional,
 *                                   // vazio quando não há ticket_id ou DASHGLPI_PUBLIC_URL
 *                                   // não está configurada)
 *     'template_lines'  => string[],// opcional (PLAN-20260703-010) — linhas já renderizadas/
 *                                   // escapadas de dashglpi_rule_render_message_template().
 *                                   // Quando presente, o canal usa essas linhas no lugar do
 *                                   // texto/card hardcoded (fallback quando ausente/vazio).
 *   ]
 *
 * Formato do retorno de send():
 *   ['ok' => bool, 'channel' => string, 'status' => int, 'error' => ?string, 'skipped' => bool]
 */
interface DashglpiMessagingChannel
{
    /** Identificador curto e estável do canal (ex.: 'teams'). */
    public function key(): string;

    /** Nome legível (ex.: 'Microsoft Teams'). */
    public function label(): string;

    /** Indica se o canal está habilitado e configurado o suficiente para enviar. */
    public function isConfigured(array $config): bool;

    /** Envia o alerta. Deve capturar exceções e devolvê-las em ['error']. */
    public function send(array $alert, array $config): array;
}

/**
 * Base com utilitários compartilhados (HTTP via cURL, formatação de texto).
 */
abstract class DashglpiMessagingChannelBase implements DashglpiMessagingChannel
{
    protected function result(bool $ok, int $status = 0, ?string $error = null, bool $skipped = false): array
    {
        return [
            'ok' => $ok,
            'channel' => $this->key(),
            'label' => $this->label(),
            'status' => $status,
            'error' => $error,
            'skipped' => $skipped,
        ];
    }

    /**
     * POST genérico. $body pode ser array (JSON) ou string já serializada.
     *
     * @return array{ok:bool,status:int,body:string,error:?string}
     */
    protected function httpPost(string $url, $body, array $headers = [], int $timeout = 10): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Extensão cURL indisponível.'];
        }

        $payload = is_string($body) ? $body : json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($payload === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'Falha ao serializar payload: ' . json_last_error_msg()];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $curlError ?: 'Falha na requisição HTTP.'];
        }

        $ok = $status >= 200 && $status < 300;
        return [
            'ok' => $ok,
            'status' => $status,
            'body' => (string) $responseBody,
            'error' => $ok ? null : ('HTTP ' . $status . ($curlError !== '' ? ' - ' . $curlError : '')),
        ];
    }

    /**
     * 'warn'/'breach' são eventos de SLA (Step 5); 'info' é usado por eventos
     * genéricos do orquestrador (TaskWorker, SolutionWorker — PLAN-007) que não
     * têm semântica de estouro de tempo.
     */
    protected function levelLabel(string $level): string
    {
        return match ($level) {
            'warn' => 'Atenção de SLA',
            'info' => 'Novo Evento',
            default => 'SLA estourado',
        };
    }

    protected function levelEmoji(string $level): string
    {
        return match ($level) {
            'warn' => '⚠️',
            'info' => '📌',
            default => '🚨',
        };
    }

    /**
     * Valida esquema http(s) + host, aceitando underscore no hostname (nomes de
     * containers Docker deste ecossistema, ex.: kawa_evolutionapi, kawa_mysql).
     * filter_var(FILTER_VALIDATE_URL) rejeita underscore e não serve aqui.
     */
    protected function isValidHttpUrl(string $url): bool
    {
        $url = trim($url);
        if ($url === '' || !preg_match('/^https?:\/\//i', $url)) {
            return false;
        }

        $parts = parse_url($url);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        return $host !== '' && preg_match('/^[a-zA-Z0-9_.-]+$/', $host) === 1;
    }

    protected function str(array $alert, string $key, string $default = ''): string
    {
        $value = $alert[$key] ?? $default;
        return is_scalar($value) ? (string) $value : $default;
    }

    protected function int(array $alert, string $key, int $default = 0): int
    {
        return (int) ($alert[$key] ?? $default);
    }
}
