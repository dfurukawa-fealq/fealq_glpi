<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/settings.php';

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

if (!empty(dashglpi_current_user_context()['has_profile_rule'])) {
    http_response_code(403);
    echo 'Relatorio indisponivel para este perfil de atendimento.';
    exit;
}

$ticketId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

// Objeto ITIL do relatório (PLAN-20260709-019, Fase D): whitelist do registry,
// default 'ticket' (URLs atuais continuam funcionando sem mudança).
$reportItemtypeKey = dashglpi_itil_normalize_type($_GET['itemtype'] ?? 'ticket');
$reportItilType = dashglpi_itil_type($reportItemtypeKey);
report_itemtype($reportItemtypeKey);
$reportArticle = (string) ($reportItilType['article'] ?? 'o');
$reportSubjectLabel = (string) $reportItilType['label'];
$reportDocTitle = 'Relatório de Auditoria d' . $reportArticle . ' ' . $reportSubjectLabel;

if (!$ticketId) {
    http_response_code(400);
    $pageError = 'ID inválido.';
} else {
    dashglpi_assert_itil_object_access($reportItemtypeKey, (int) $ticketId);
}

function report_h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Objeto ITIL corrente do relatório (evita threading por todos os helpers de imagem). */
function report_itemtype(?string $set = null): string
{
    static $key = 'ticket';
    if ($set !== null) {
        $key = $set;
    }

    return $key;
}

function report_date(?string $value): string
{
    if (!$value || $value === '0000-00-00 00:00:00') {
        return '-';
    }

    try {
        return (new DateTime($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return report_h($value);
    }
}

function report_document_id_from_src(string $src): ?int
{
    $parts = parse_url(html_entity_decode($src, ENT_QUOTES, 'UTF-8'));
    if (!$parts || empty($parts['query'])) {
        return null;
    }

    parse_str($parts['query'], $query);
    $id = $query['docid'] ?? $query['document_id'] ?? $query['documents_id'] ?? null;

    return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
}

function report_image_figure(int $ticketId, array $document): string
{
    $documentId = (int) $document['id'];
    $caption = trim((string) ($document['name'] ?: $document['filename'] ?: 'Imagem anexada'));
    $src = dashglpi_document_preview_url($ticketId, $documentId, report_itemtype());

    return '<figure class="image-preview">'
        . '<a class="image-preview-link" href="' . report_h($src) . '" target="_blank" rel="noopener">'
        . '<img src="' . report_h($src) . '" alt="' . report_h($caption) . '">'
        . '</a>'
        . '<figcaption>' . report_h($caption) . '</figcaption>'
        . '</figure>';
}

function report_html_fragment_has_content(string $html): bool
{
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
    $plain = str_replace("\xC2\xA0", ' ', $plain);
    $plain = preg_replace('/\s+/u', ' ', $plain) ?? $plain;

    return trim($plain) !== '';
}

function report_inline_image_placeholder(): string
{
    return '<span class="muted image-missing-note">Imagem indisponível no reporte.</span>';
}

function report_register_image_token(
    string $src,
    int $ticketId,
    array $imageDocuments,
    array &$imageTokens,
    array &$blockImageTokens
): string {
    $token = '%%DASHGLPI_IMAGE_' . count($imageTokens) . '%%';
    $documentId = report_document_id_from_src($src);

    if ($documentId && !empty($imageDocuments[$documentId])) {
        $imageTokens[$token] = report_image_figure($ticketId, $imageDocuments[$documentId]);
        $blockImageTokens[] = $token;
        return $token;
    }

    $imageTokens[$token] = report_inline_image_placeholder();
    return $token;
}

function report_lift_block_image_tokens(string $html, array $blockImageTokens): string
{
    foreach ($blockImageTokens as $token) {
        $quotedToken = preg_quote($token, '/');

        $html = preg_replace('/<a\b[^>]*>\s*(' . $quotedToken . ')\s*<\/a>/i', '$1', $html) ?? $html;
        $html = preg_replace_callback(
            '/<p\b[^>]*>((?:(?!<\/p>).)*?)(' . $quotedToken . ')((?:(?!<\/p>).)*?)<\/p>/is',
            static function (array $matches): string {
                $parts = [];
                $before = $matches[1] ?? '';
                $token = $matches[2] ?? '';
                $after = $matches[3] ?? '';

                if (report_html_fragment_has_content($before)) {
                    $parts[] = '<p>' . trim($before) . '</p>';
                }

                $parts[] = $token;

                if (report_html_fragment_has_content($after)) {
                    $parts[] = '<p>' . trim($after) . '</p>';
                }

                return implode('', $parts);
            },
            $html
        ) ?? $html;
    }

    return $html;
}

function report_rich(?string $html, int $ticketId = 0, ?array $imageDocuments = null): string
{
    $html = (string) $html;
    if (trim($html) === '') {
        return '<span class="muted">Sem conteúdo registrado.</span>';
    }

    $imageTokens = [];
    $blockImageTokens = [];
    $inlineImagesEnabled = $ticketId > 0 && $imageDocuments !== null;

    if ($inlineImagesEnabled) {
        $html = preg_replace_callback(
            '/<a\b[^>]*>\s*<img\b[^>]*\bsrc\s*=\s*("|\')([^"\']+)\1[^>]*>\s*<\/a>/i',
            static function (array $matches) use ($ticketId, $imageDocuments, &$imageTokens, &$blockImageTokens): string {
                return report_register_image_token(
                    $matches[2] ?? '',
                    $ticketId,
                    $imageDocuments ?? [],
                    $imageTokens,
                    $blockImageTokens
                );
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback(
            '/<img\b[^>]*\bsrc\s*=\s*("|\')([^"\']+)\1[^>]*>/i',
            static function (array $matches) use ($ticketId, $imageDocuments, &$imageTokens, &$blockImageTokens): string {
                return report_register_image_token(
                    $matches[2] ?? '',
                    $ticketId,
                    $imageDocuments ?? [],
                    $imageTokens,
                    $blockImageTokens
                );
            },
            $html
        ) ?? $html;
    }

    $html = preg_replace('/<img\b[^>]*>/i', '', $html) ?? $html;
    $allowed = '<p><br><ul><ol><li><strong><b><em><i><u><blockquote><pre><code><table><thead><tbody><tr><th><td><a>';
    $clean = strip_tags($html, $allowed);
    $clean = preg_replace('/\son[a-z]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/i', '', $clean) ?? $clean;
    $clean = preg_replace('/(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>\s]+("|\')?/i', '$1="#"', $clean) ?? $clean;
    $clean = $blockImageTokens ? report_lift_block_image_tokens($clean, $blockImageTokens) : $clean;

    return $imageTokens ? strtr($clean, $imageTokens) : $clean;
}

function report_status_label(int $status): string
{
    return [
        1 => 'Novo',
        2 => 'Em atendimento',
        3 => 'Planejado',
        4 => 'Pendente',
        5 => 'Solucionado',
        6 => 'Fechado',
    ][$status] ?? 'Status ' . $status;
}

function report_scale_label(?int $value): string
{
    return [
        1 => 'Muito baixa',
        2 => 'Baixa',
        3 => 'Média',
        4 => 'Alta',
        5 => 'Muito alta',
        6 => 'Maior',
    ][$value ?? 0] ?? '-';
}

function report_type_label(?int $type): string
{
    return [
        1 => 'Incidente',
        2 => 'Requisição',
    ][$type ?? 0] ?? '-';
}

function report_user_name(array $row): string
{
    $display = trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['realname'] ?? ''));
    if ($display !== '') {
        return $display;
    }

    return (string) ($row['name'] ?? $row['alternative_email'] ?? $row['user_name'] ?? 'Sistema');
}

function report_event_date(array $event): int
{
    $date = (string) ($event['date'] ?? '');
    return $date !== '' ? strtotime($date) ?: 0 : 0;
}

$ticket = null;
$people = [1 => [], 2 => [], 3 => []];
$followups = [];
$tasks = [];
$solutions = [];
$documents = [];
$imageDocuments = [];
$logs = [];
$timeline = [];
$glpiTicketUrl = null;
$reportSettings = dashglpi_get_settings('reports');
$reportBrand = dashglpi_report_brand($reportSettings);
$showInlineImages = ($reportSettings['attachments'] ?? 'images_inline') === 'images_inline';

if (empty($pageError)) {
    // requesttypes_id só existe em glpi_tickets; a coluna vira NULL nos demais objetos.
    $requesttypeSelect = $reportItemtypeKey === 'ticket' ? 'rt.name' : 'NULL';
    $requesttypeJoin = $reportItemtypeKey === 'ticket'
        ? 'LEFT JOIN glpi_requesttypes rt ON rt.id = t.requesttypes_id'
        : '';

    $ticket = dashglpi_fetch_one(
        "SELECT t.*,
                e.completename AS entity_name,
                c.completename AS category_name,
                $requesttypeSelect AS requesttype_name,
                loc.completename AS location_name,
                ur.name AS recipient_login,
                ur.firstname AS recipient_firstname,
                ur.realname AS recipient_realname
         FROM {$reportItilType['table']} t
         LEFT JOIN glpi_entities e ON e.id = t.entities_id
         LEFT JOIN glpi_itilcategories c ON c.id = t.itilcategories_id
         $requesttypeJoin
         LEFT JOIN glpi_locations loc ON loc.id = t.locations_id
         LEFT JOIN glpi_users ur ON ur.id = t.users_id_recipient
         WHERE t.id = ? AND t.is_deleted = 0
         LIMIT 1",
        [$ticketId]
    );

    if (!$ticket) {
        http_response_code(404);
        $pageError = ucfirst($reportSubjectLabel) . ' não encontrad' . $reportArticle . ' ou removid' . $reportArticle . '.';
    }
}

if ($ticket) {
    $glpiTicketUrl = dashglpi_itil_glpi_url($reportItilType, (int) $ticket['id']) ?: null;

    foreach (dashglpi_fetch_all(
        "SELECT tu.type, tu.alternative_email, u.name, u.firstname, u.realname
         FROM {$reportItilType['user_link_table']} tu
         LEFT JOIN glpi_users u ON u.id = tu.users_id
         WHERE tu.{$reportItilType['fk']} = ?
         ORDER BY tu.type, u.firstname, u.realname, u.name",
        [$ticketId]
    ) as $row) {
        $type = (int) $row['type'];
        if (!isset($people[$type])) {
            $people[$type] = [];
        }
        $people[$type][] = report_user_name($row);
    }

    if (empty($people[1]) && !empty($ticket['recipient_login'])) {
        $people[1][] = report_user_name([
            'name' => $ticket['recipient_login'],
            'firstname' => $ticket['recipient_firstname'],
            'realname' => $ticket['recipient_realname'],
        ]);
    }

    $followups = dashglpi_fetch_all(
        "SELECT f.*, u.name, u.firstname, u.realname, ue.name AS editor_name
         FROM glpi_itilfollowups f
         LEFT JOIN glpi_users u ON u.id = f.users_id
         LEFT JOIN glpi_users ue ON ue.id = f.users_id_editor
         WHERE f.itemtype = ? AND f.items_id = ?
         ORDER BY COALESCE(f.date, f.date_creation, f.date_mod)",
        [$reportItilType['glpi_itemtype'], $ticketId]
    );

    $tasks = dashglpi_fetch_all(
        "SELECT tt.*,
                u.name, u.firstname, u.realname,
                tech.name AS tech_name, tech.firstname AS tech_firstname, tech.realname AS tech_realname
         FROM {$reportItilType['task_table']} tt
         LEFT JOIN glpi_users u ON u.id = tt.users_id
         LEFT JOIN glpi_users tech ON tech.id = tt.users_id_tech
         WHERE tt.{$reportItilType['fk']} = ?
         ORDER BY COALESCE(tt.date, tt.date_creation, tt.date_mod)",
        [$ticketId]
    );

    $solutions = dashglpi_fetch_all(
        "SELECT s.*, u.name, u.firstname, u.realname
         FROM glpi_itilsolutions s
         LEFT JOIN glpi_users u ON u.id = s.users_id
         WHERE s.itemtype = ? AND s.items_id = ?
         ORDER BY COALESCE(s.date_creation, s.date_mod, s.date_approval)",
        [$reportItilType['glpi_itemtype'], $ticketId]
    );

    $documents = dashglpi_itil_related_documents($reportItilType, (int) $ticketId);

    foreach ($documents as $document) {
        if (dashglpi_is_image_document($document)) {
            $imageDocuments[(int) $document['id']] = $document;
        }
    }

    $logs = dashglpi_fetch_all(
        "SELECT id, itemtype_link, linked_action, user_name, date_mod, id_search_option, old_value, new_value, old_id, new_id
         FROM glpi_logs
         WHERE itemtype = ? AND items_id = ?
         ORDER BY date_mod, id",
        [$reportItilType['glpi_itemtype'], $ticketId]
    );

    $timeline[] = [
        'date' => (string) $ticket['date'],
        'type' => 'Abertura',
        'actor' => implode(', ', $people[1]) ?: 'Sistema',
        'title' => $reportSubjectLabel . ' criad' . $reportArticle,
        'content' => report_rich($ticket['content'] ?? '', (int) $ticket['id'], $showInlineImages ? $imageDocuments : null),
        'private' => false,
    ];

    foreach ($followups as $row) {
        if (($reportSettings['visibility'] ?? 'complete') === 'public_only' && !empty($row['is_private'])) {
            continue;
        }

        $timeline[] = [
            'date' => (string) ($row['date'] ?? $row['date_creation'] ?? $row['date_mod'] ?? ''),
            'type' => 'Acompanhamento',
            'actor' => report_user_name($row),
            'title' => !empty($row['is_private']) ? 'Acompanhamento privado' : 'Acompanhamento',
            'content' => report_rich($row['content'] ?? '', (int) $ticket['id'], $showInlineImages ? $imageDocuments : null),
            'private' => (bool) $row['is_private'],
        ];
    }

    foreach ($tasks as $row) {
        if (($reportSettings['visibility'] ?? 'complete') === 'public_only' && !empty($row['is_private'])) {
            continue;
        }

        $tech = report_user_name([
            'name' => $row['tech_name'] ?? '',
            'firstname' => $row['tech_firstname'] ?? '',
            'realname' => $row['tech_realname'] ?? '',
        ]);
        $timeline[] = [
            'date' => (string) ($row['date'] ?? $row['date_creation'] ?? $row['date_mod'] ?? ''),
            'type' => 'Tarefa',
            'actor' => report_user_name($row),
            'title' => 'Tarefa' . ($tech !== 'Sistema' ? ' para ' . $tech : ''),
            'content' => report_rich($row['content'] ?? '', (int) $ticket['id'], $showInlineImages ? $imageDocuments : null),
            'private' => (bool) $row['is_private'],
        ];
    }

    foreach ($solutions as $row) {
        $timeline[] = [
            'date' => (string) ($row['date_creation'] ?? $row['date_mod'] ?? $row['date_approval'] ?? ''),
            'type' => 'Solução',
            'actor' => report_user_name($row),
            'title' => (string) ($row['solutiontype_name'] ?? 'Solução registrada'),
            'content' => report_rich($row['content'] ?? '', (int) $ticket['id'], $showInlineImages ? $imageDocuments : null),
            'private' => false,
        ];
    }

    foreach ($logs as $row) {
        $old = trim((string) ($row['old_value'] ?? $row['old_id'] ?? ''));
        $new = trim((string) ($row['new_value'] ?? $row['new_id'] ?? ''));
        $content = trim(($old !== '' ? 'De: ' . $old : '') . ($new !== '' ? "\nPara: " . $new : ''));
        $timeline[] = [
            'date' => (string) ($row['date_mod'] ?? ''),
            'type' => 'Log',
            'actor' => (string) ($row['user_name'] ?? 'Sistema'),
            'title' => 'Alteração técnica #' . (int) $row['id'],
            'content' => '<pre>' . report_h($content !== '' ? $content : 'Evento registrado no histórico técnico.') . '</pre>',
            'private' => false,
        ];
    }

    usort($timeline, fn (array $a, array $b): int => report_event_date($a) <=> report_event_date($b));
}

$generatedAt = (new DateTime())->format('d/m/Y H:i');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= report_h($reportDocTitle) ?><?= $ticket ? ' #' . (int) $ticket['id'] : '' ?> - INB Tecnologia</title>
    <style>
        :root {
            --ink: #172033;
            --muted: #64748b;
            --line: #d8e0ea;
            --soft: #f5f7fb;
            --brand: <?= report_h($reportBrand['accent']) ?>;
            --brand-dark: <?= report_h($reportBrand['primary']) ?>;
            --brand-mid: #163b65;
            --danger: #dc2626;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: var(--ink);
            background: #eef2f6;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13px;
            line-height: 1.5;
        }

        .report-shell {
            max-width: 1120px;
            margin: 24px auto;
            background: #fff;
            border: 0;
            border-radius: 6px;
            box-shadow: 0 12px 35px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .report-topbar {
            padding: 0;
            color: var(--ink);
            background: #fff;
            border-top: 6px solid var(--brand-dark);
            border-bottom: 1px solid var(--line);
        }

        .masthead {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding: 26px 32px 22px;
        }

        .brand-block {
            display: grid;
            gap: 12px;
        }

        .brand-logo {
            display: block;
            width: 210px;
            max-width: 100%;
            height: auto;
            border: 0;
        }

        .document-kicker {
            color: var(--brand);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .document-meta {
            min-width: 260px;
            padding-left: 22px;
            border-left: 3px solid var(--brand);
            color: var(--muted);
            font-size: 12px;
            text-align: right;
        }

        h1, h2, h3, p { margin-top: 0; }
        h1 {
            margin-bottom: 8px;
            color: var(--brand-dark);
            font-size: 26px;
            line-height: 1.2;
        }
        h2 {
            margin-bottom: 16px;
            padding-bottom: 8px;
            color: var(--brand-dark);
            border-bottom: 1px solid var(--line);
            font-size: 15px;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        h3 { margin-bottom: 6px; color: var(--brand-dark); font-size: 14px; }

        .subtitle { margin-bottom: 0; color: var(--muted); }
        .actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            flex-wrap: wrap;
            padding: 14px 32px;
            background: #f8fafc;
            border-top: 1px solid var(--line);
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            padding: 9px 14px;
            border: 1px solid var(--line);
            border-radius: 4px;
            color: var(--brand-dark);
            background: #fff;
            text-decoration: none;
            font-weight: 700;
            cursor: pointer;
        }

        .btn.primary { color: #fff; background: var(--brand); border-color: var(--brand); }
        .report-body { padding: 28px 32px 34px; }
        .section {
            margin-bottom: 20px;
            padding: 20px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
        }

        .control-section {
            border-top: 4px solid var(--brand-dark);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 10px;
        }

        .field {
            padding: 10px 12px;
            border-radius: 3px;
            background: var(--soft);
            border: 1px solid #e7edf5;
        }

        .field-label {
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .field-value { font-weight: 700; overflow-wrap: anywhere; }
        .content-box {
            padding: 16px;
            border-radius: 3px;
            background: var(--soft);
            border-left: 4px solid var(--brand);
            overflow-wrap: anywhere;
        }

        .timeline {
            position: relative;
            display: grid;
            gap: 14px;
        }

        .timeline-item {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 16px;
            padding: 16px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: #fff;
            page-break-inside: avoid;
        }

        .timeline-date { color: var(--muted); font-weight: 700; }
        .tag {
            display: inline-flex;
            width: fit-content;
            margin-bottom: 8px;
            padding: 4px 8px;
            border-radius: 999px;
            color: var(--brand-dark);
            background: #e6f9f8;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .tag.private { color: #92400e; background: #fef3c7; }
        .muted { color: var(--muted); }
        pre {
            white-space: pre-wrap;
            margin: 0;
            font-family: Consolas, Monaco, monospace;
            font-size: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 10px 12px;
            border-bottom: 1px solid var(--line);
            text-align: left;
            vertical-align: top;
        }

        th {
            color: var(--muted);
            background: var(--soft);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        .empty {
            color: var(--muted);
            padding: 18px;
            border: 1px dashed var(--line);
            border-radius: 4px;
            background: var(--soft);
        }

        .audit-note {
            margin: 14px 0 0;
            padding: 10px 12px;
            border-left: 3px solid var(--brand);
            color: var(--muted);
            background: #fbfdff;
            font-size: 12px;
        }

        .image-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }

        .image-preview {
            margin: 0;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 4px;
            background: var(--soft);
            page-break-inside: avoid;
            break-inside: avoid;
        }

        .content-box .image-preview {
            margin: 12px 0;
        }

        .image-preview-link {
            display: block;
            color: inherit;
            text-decoration: none;
        }

        .image-preview img {
            display: block;
            max-width: 100%;
            height: auto;
            margin: 0 auto;
            border: 1px solid #e7edf5;
            background: #fff;
        }

        .image-preview figcaption {
            margin-top: 8px;
            color: var(--muted);
            font-size: 12px;
            font-weight: 700;
            text-align: center;
        }

        .image-missing-note {
            font-style: italic;
        }

        .error-card {
            max-width: 680px;
            margin: 80px auto;
            padding: 32px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: 0 20px 70px rgba(15, 23, 42, 0.12);
        }

        @media (max-width: 820px) {
            .report-shell { margin: 0; border-radius: 0; }
            .masthead, .report-body { padding: 22px; }
            .masthead { flex-direction: column; }
            .document-meta { min-width: 0; padding-left: 0; border-left: 0; text-align: left; }
            .actions { justify-content: flex-start; }
            .grid { grid-template-columns: 1fr; }
            .timeline-item { grid-template-columns: 1fr; }
        }

        @media print {
            @page {
                margin: 12mm;
            }

            * {
                print-color-adjust: exact !important;
                -webkit-print-color-adjust: exact !important;
            }

            html, body {
                background: #fff !important;
            }

            body {
                color: var(--ink);
                font-size: 12px;
            }

            .report-shell {
                width: 100%;
                max-width: none;
                margin: 0;
                border: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .report-topbar {
                color: var(--ink) !important;
                background: #fff !important;
                border-top: 5px solid var(--brand-dark);
                border-bottom: 1px solid var(--line);
            }

            .masthead {
                display: flex;
                flex-direction: row;
                justify-content: space-between;
                align-items: flex-start;
                gap: 24px;
                padding: 22px 26px 18px;
            }

            .document-meta {
                min-width: 230px;
                padding-left: 20px;
                border-left: 3px solid var(--brand);
                text-align: right;
            }

            .subtitle {
                color: var(--muted) !important;
            }

            .report-body {
                padding: 24px 26px 28px;
            }

            .section {
                margin: 0 0 18px;
                padding: 16px 0 0;
                border: 0;
                border-top: 1px solid var(--line);
                border-radius: 0;
                break-inside: auto;
                page-break-inside: auto;
            }

            .control-section {
                border-top: 3px solid var(--brand-dark);
            }

            h2 {
                break-after: avoid;
                page-break-after: avoid;
            }

            .grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }

            .timeline-item {
                grid-template-columns: 140px 1fr;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .image-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .field,
            .content-box,
            th,
            .empty {
                background: var(--soft) !important;
            }

            .tag {
                color: var(--brand-dark) !important;
                background: #e6f9f8 !important;
            }

            .tag.private {
                color: #92400e !important;
                background: #fef3c7 !important;
            }

            .actions { display: none; }
            a { color: inherit; text-decoration: none; }
        }
    </style>
</head>
<body>
<?php if (!empty($pageError)): ?>
    <main class="error-card">
        <h1>Relatório indisponível</h1>
        <p class="muted"><?= report_h($pageError) ?></p>
        <a class="btn primary" href="/front/dashboard.php">Voltar ao DASHGLPI</a>
    </main>
<?php else: ?>
    <main class="report-shell">
        <header class="report-topbar">
            <div class="masthead">
                <div class="brand-block">
                    <img
                        class="brand-logo"
                        src="<?= report_h($reportBrand['logo']) ?>"
                        alt="<?= report_h($reportBrand['name']) ?>"
                    >
                    <div class="document-kicker"><?= report_h($reportBrand['source']) ?></div>
                    <div>
                        <h1><?= report_h($reportDocTitle) ?></h1>
                        <p class="subtitle"><?= report_h($reportSubjectLabel) ?> #<?= (int) $ticket['id'] ?> - <?= report_h($ticket['name']) ?></p>
                    </div>
                </div>
                <aside class="document-meta" aria-label="Dados do documento">
                    <strong>Documento de auditoria</strong><br>
                    Gerado em <?= report_h($generatedAt) ?><br>
                    Origem: DASHGLPI / GLPI
                </aside>
            </div>
            <nav class="actions">
                <button class="btn primary" type="button" onclick="window.print()">Imprimir / Salvar PDF</button>
                <a class="btn" href="/front/dashboard.php">Voltar</a>
                <?php if ($glpiTicketUrl): ?>
                    <a class="btn" href="<?= report_h($glpiTicketUrl) ?>" target="_blank" rel="noopener">Abrir no GLPI</a>
                <?php endif; ?>
            </nav>
        </header>

        <div class="report-body">
            <section class="section control-section">
                <h2>Controle Documental</h2>
                <div class="grid">
                    <div class="field"><div class="field-label">Protocolo</div><div class="field-value">#<?= (int) $ticket['id'] ?></div></div>
                    <div class="field"><div class="field-label">Status</div><div class="field-value"><?= report_h(dashglpi_itil_status_label($reportItilType, (int) $ticket['status'])) ?></div></div>
                    <div class="field"><div class="field-label">Entidade</div><div class="field-value"><?= report_h($ticket['entity_name'] ?: '-') ?></div></div>
                    <div class="field"><div class="field-label">Criado em</div><div class="field-value"><?= report_date($ticket['date']) ?></div></div>
                    <div class="field"><div class="field-label">SLA/Previsão</div><div class="field-value"><?= report_date($ticket['time_to_resolve']) ?></div></div>
                    <div class="field"><div class="field-label">Gerado em</div><div class="field-value"><?= report_h($generatedAt) ?></div></div>
                </div>
                <p class="audit-note">Documento consolidado a partir dos registros disponíveis no GLPI no momento da geração, incluindo histórico, anexos e logs técnicos vinculados ao chamado.</p>
            </section>

            <section class="section">
                <h2>Resumo d<?= report_h($reportArticle) ?> <?= report_h($reportSubjectLabel) ?></h2>
                <div class="grid">
                    <div class="field"><div class="field-label">Status</div><div class="field-value"><?= report_h(dashglpi_itil_status_label($reportItilType, (int) $ticket['status'])) ?></div></div>
                    <div class="field"><div class="field-label">Entidade</div><div class="field-value"><?= report_h($ticket['entity_name'] ?: '-') ?></div></div>
                    <div class="field"><div class="field-label">Categoria</div><div class="field-value"><?= report_h($ticket['category_name'] ?: '-') ?></div></div>
                    <?php if (!empty($reportItilType['has_ticket_type'])): ?>
                    <div class="field"><div class="field-label">Tipo</div><div class="field-value"><?= report_h(report_type_label((int) ($ticket['type'] ?? 0))) ?></div></div>
                    <?php endif; ?>
                    <div class="field"><div class="field-label">Prioridade</div><div class="field-value"><?= report_h(report_scale_label((int) $ticket['priority'])) ?></div></div>
                    <div class="field"><div class="field-label">Urgência</div><div class="field-value"><?= report_h(report_scale_label((int) $ticket['urgency'])) ?></div></div>
                    <div class="field"><div class="field-label">Impacto</div><div class="field-value"><?= report_h(report_scale_label((int) $ticket['impact'])) ?></div></div>
                    <?php if ($reportItemtypeKey === 'ticket'): ?>
                    <div class="field"><div class="field-label">Origem</div><div class="field-value"><?= report_h($ticket['requesttype_name'] ?: '-') ?></div></div>
                    <?php endif; ?>
                    <div class="field"><div class="field-label">Criado em</div><div class="field-value"><?= report_date($ticket['date']) ?></div></div>
                    <div class="field"><div class="field-label">Previsão/SLA</div><div class="field-value"><?= report_date($ticket['time_to_resolve']) ?></div></div>
                    <div class="field"><div class="field-label">Solucionado em</div><div class="field-value"><?= report_date($ticket['solvedate']) ?></div></div>
                    <div class="field"><div class="field-label">Fechado em</div><div class="field-value"><?= report_date($ticket['closedate']) ?></div></div>
                </div>
            </section>

            <section class="section">
                <h2>Pessoas Envolvidas</h2>
                <div class="grid">
                    <div class="field"><div class="field-label">Requerentes</div><div class="field-value"><?= report_h(implode(', ', array_unique($people[1])) ?: '-') ?></div></div>
                    <div class="field"><div class="field-label">Técnicos</div><div class="field-value"><?= report_h(implode(', ', array_unique($people[2])) ?: '-') ?></div></div>
                    <div class="field"><div class="field-label">Observadores</div><div class="field-value"><?= report_h(implode(', ', array_unique($people[3])) ?: '-') ?></div></div>
                    <div class="field"><div class="field-label">Localização</div><div class="field-value"><?= report_h($ticket['location_name'] ?: '-') ?></div></div>
                </div>
            </section>

            <section class="section">
                <h2>Conteúdo Original</h2>
                <div class="content-box"><?= report_rich($ticket['content'] ?? '', (int) $ticket['id'], $showInlineImages ? $imageDocuments : null) ?></div>
            </section>

            <section class="section">
                <h2>Timeline Completa</h2>
                <?php if (empty($timeline)): ?>
                    <div class="empty">Nenhum evento encontrado.</div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($timeline as $event): ?>
                            <article class="timeline-item">
                                <div class="timeline-date"><?= report_date($event['date']) ?></div>
                                <div>
                                    <span class="tag<?= !empty($event['private']) ? ' private' : '' ?>"><?= report_h($event['type']) ?><?= !empty($event['private']) ? ' privado / evidência de auditoria' : '' ?></span>
                                    <h3><?= report_h($event['title']) ?></h3>
                                    <p class="muted">Autor: <?= report_h($event['actor']) ?></p>
                                    <div class="content-box"><?= $event['content'] ?></div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section class="section">
                <h2>Anexos</h2>
                <?php if ($showInlineImages && !empty($imageDocuments)): ?>
                    <div class="image-grid">
                        <?php foreach ($imageDocuments as $imageDocument): ?>
                            <?= report_image_figure((int) $ticket['id'], $imageDocument) ?>
                        <?php endforeach; ?>
                    </div>
                    <br>
                <?php endif; ?>
                <?php if (empty($documents)): ?>
                    <div class="empty">Nenhum anexo vinculado ao chamado.</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Nome</th><th>Arquivo</th><th>Tipo</th><th>Autor</th><th>Data</th></tr></thead>
                        <tbody>
                        <?php foreach ($documents as $doc): ?>
                            <tr>
                                <td><?= report_h($doc['name'] ?: '-') ?></td>
                                <td><?= report_h($doc['filename'] ?: $doc['link'] ?: '-') ?></td>
                                <td><?= report_h($doc['mime'] ?: '-') ?></td>
                                <td><?= report_h(report_user_name($doc)) ?></td>
                                <td><?= report_date($doc['attached_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="section">
                <h2>Histórico Técnico / Auditoria</h2>
                <?php if (empty($logs)): ?>
                    <div class="empty">Nenhum log técnico encontrado.</div>
                <?php else: ?>
                    <table>
                        <thead><tr><th>Data</th><th>Usuário</th><th>Ação</th><th>Antes</th><th>Depois</th></tr></thead>
                        <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?= report_date($log['date_mod']) ?></td>
                                <td><?= report_h($log['user_name'] ?: 'Sistema') ?></td>
                                <td><?= report_h('Log #' . (int) $log['id'] . ' / Opção ' . (string) $log['id_search_option']) ?></td>
                                <td><?= report_h((string) ($log['old_value'] ?: $log['old_id'] ?: '-')) ?></td>
                                <td><?= report_h((string) ($log['new_value'] ?: $log['new_id'] ?: '-')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </div>
    </main>
<?php endif; ?>
</body>
</html>
