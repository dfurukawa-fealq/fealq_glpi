<?php

require_once __DIR__ . '/channel_interface.php';

/**
 * Microsoft Teams via Incoming Webhook (Adaptive Card).
 * Comunicação interna da equipe técnica (Step 6.A do .Stack.md).
 *
 * Config esperada em $config['teams']:
 *   ['enabled' => bool, 'webhook_url' => string]
 */
class DashglpiTeamsChannel extends DashglpiMessagingChannelBase
{
    public function key(): string
    {
        return 'teams';
    }

    public function label(): string
    {
        return 'Microsoft Teams';
    }

    public function isConfigured(array $config): bool
    {
        $teams = is_array($config['teams'] ?? null) ? $config['teams'] : [];
        return !empty($teams['enabled'])
            && $this->isValidHttpUrl((string) ($teams['webhook_url'] ?? ''));
    }

    public function send(array $alert, array $config): array
    {
        if (!$this->isConfigured($config)) {
            return $this->result(false, 0, 'Canal Teams não configurado.', true);
        }

        $webhook = (string) $config['teams']['webhook_url'];
        $level = $this->str($alert, 'level', 'breach');
        $ticketId = $this->int($alert, 'ticket_id');
        $title = $this->str($alert, 'title', 'Chamado #' . $ticketId);
        $url = $this->str($alert, 'url');
        $dashboardUrl = $this->str($alert, 'dashboard_url');

        $threshold = $this->int($alert, 'threshold');

        // template_lines (PLAN-20260703-010): quando a regra tem message_template configurado,
        // o motor de regras já vem com as linhas renderizadas/escapadas — troca o CORPO do card
        // (activityTitle/facts) por elas, mantendo title/themeColor/potentialAction como estão.
        $templateLines = $alert['template_lines'] ?? null;
        if (is_array($templateLines) && $templateLines !== []) {
            $section = [
                'text' => implode("\n\n", $templateLines),
                'markdown' => true,
            ];
        } else {
            $facts = [
                ['title' => 'Chamado', 'value' => '#' . $ticketId],
                ['title' => 'Entidade', 'value' => $this->str($alert, 'entity_name', '—')],
                ['title' => 'Categoria', 'value' => $this->str($alert, 'category', '—')],
            ];
            $eventAt = $this->str($alert, 'created_at_formatted');
            if ($eventAt !== '') {
                $facts[] = ['title' => $this->str($alert, 'event_at_label', 'Ocorrido em'), 'value' => $eventAt];
            }
            $facts[] = $threshold > 0
                ? ['title' => 'Sem ação há', 'value' => $this->int($alert, 'minutes_overdue') . ' min (limite: ' . $threshold . ' min)']
                : ['title' => 'Detectado há', 'value' => $this->int($alert, 'minutes_overdue') . ' min'];
            $note = $this->str($alert, 'note');
            if ($note !== '') {
                $facts[] = ['title' => 'Detalhe', 'value' => $note];
            }

            $section = [
                'activityTitle' => '**' . $this->escapeMd($title) . '**',
                'activitySubtitle' => 'Fealq · Orquestrador de Eventos',
                'facts' => $facts,
                'markdown' => true,
            ];
        }

        // Estrutura MessageCard clássica (aceita em Incoming Webhooks do Teams),
        // com actions de abertura do chamado.
        $card = [
            '@type' => 'MessageCard',
            '@context' => 'https://schema.org/extensions',
            'themeColor' => $level === 'warn' ? 'FFB020' : ($level === 'info' ? '2A9D8F' : 'D2314B'),
            'summary' => $this->levelLabel($level) . ' - Chamado #' . $ticketId,
            'sections' => [$section],
        ];

        // Com template_lines, o título fixo ("🚨 SLA estourado" etc.) não entra — só o texto
        // configurado pelo usuário deve aparecer no card.
        if (!(is_array($templateLines) && $templateLines !== [])) {
            $card['title'] = $this->levelEmoji($level) . ' ' . $this->levelLabel($level);
        }

        $actions = [];
        if ($url !== '') {
            $actions[] = [
                '@type' => 'OpenUri',
                'name' => 'Visualizar Chamado (GLPI)',
                'targets' => [['os' => 'default', 'uri' => $url]],
            ];
        }
        if ($dashboardUrl !== '') {
            $actions[] = [
                '@type' => 'OpenUri',
                'name' => 'Abrir no DashGLPI',
                'targets' => [['os' => 'default', 'uri' => $dashboardUrl]],
            ];
        }
        if ($actions !== []) {
            $card['potentialAction'] = $actions;
        }

        // PLAN-20260713-020: quando o alert vem do trigger ticket_assigned, carrega a
        // identidade do técnico atribuído como campo extra no corpo JSON. Ignorado por
        // um Incoming Webhook nativo do Teams (MessageCard não tem menção real — ver
        // inc/task_worker.php:8-10), mas lido por um flow HTTP-trigger no Power
        // Automate/Logic Apps (hosts já na allowlist) para montar o <at> de menção do
        // lado de lá. Não altera o formato do card para nenhum outro trigger/destinatário.
        $assignedToName = $this->str($alert, 'assigned_to_name');
        $assignedToEmail = $this->str($alert, 'assigned_to_email');
        if ($assignedToName !== '' || $assignedToEmail !== '') {
            $card['dashglpiMention'] = [
                'name' => $assignedToName,
                'email' => $assignedToEmail,
            ];
        }

        $response = $this->httpPost(
            $webhook,
            $card,
            ['Content-Type: application/json']
        );

        return $this->result($response['ok'], $response['status'], $response['error']);
    }

    private function escapeMd(string $text): string
    {
        return str_replace(['*', '_', '#'], ['\\*', '\\_', '\\#'], $text);
    }
}
