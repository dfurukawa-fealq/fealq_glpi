<?php

// Fatal errors can occur while loading GLPI itself, before the regular
// try/catch below is reached. Always keep the bridge contract JSON-shaped.
ob_start();
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error || !in_array((int) ($error['type'] ?? 0), [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $message = trim((string) ($error['message'] ?? ''));
    $requestId = strtolower(trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{16,64}$/', $requestId)) {
        try {
            $requestId = bin2hex(random_bytes(16));
        } catch (Throwable) {
            $requestId = substr(sha1(uniqid('', true)), 0, 32);
        }
    }
    error_log(sprintf(
        '[DashGLPI bridge][ticket_create:fatal] request_id=%s %s in %s:%d',
        $requestId,
        $message !== '' ? $message : 'Erro fatal no bridge.',
        (string) ($error['file'] ?? '(unknown)'),
        (int) ($error['line'] ?? 0)
    ));

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Request-ID: ' . $requestId);
    }
    echo json_encode([
        'ok' => false,
        'error' => 'Erro fatal ao carregar o bridge de abertura de chamado.',
        'request_id' => $requestId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

// GLPI 12 expects its legacy classes (including Toolbox) to be available
// before inc/includes.php is loaded. The bridge is an isolated HTTP request,
// so it cannot rely on a front controller having loaded Composer already.
$glpiRoot = dirname(__DIR__, 3);
$glpiAutoload = $glpiRoot . '/vendor/autoload.php';
if (is_file($glpiAutoload)) {
    require_once $glpiAutoload;
}

require_once __DIR__ . '/../../../inc/includes.php';
require_once __DIR__ . '/admin_bridge_common.php';
require_once __DIR__ . '/../inc/entity_sla_policy.php';

plugin_dashglpi_ticket_create_bridge_handle();

function plugin_dashglpi_ticket_create_bridge_handle(): void
{
    try {
        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $postLimit = plugin_dashglpi_admin_bridge_ini_bytes((string) ini_get('post_max_size'));
        if ($contentLength > 0 && $postLimit > 0 && $contentLength > $postLimit) {
            throw new RuntimeException('O envio excede post_max_size configurado no GLPI.', 413);
        }
        $payload = plugin_dashglpi_admin_bridge_payload();
        plugin_dashglpi_admin_bridge_bootstrap_constants();
        plugin_dashglpi_admin_bridge_bootstrap_logger();
        plugin_dashglpi_admin_bridge_bootstrap_cache();
        plugin_dashglpi_admin_bridge_bootstrap_db();

        $action = (string) ($payload['action'] ?? 'catalog');
        $result = match ($action) {
            'catalog' => Session::callAsSystem(static fn(): array => plugin_dashglpi_ticket_create_catalog($payload)),
            'create' => plugin_dashglpi_admin_bridge_as_actor($payload, 'plugin_dashglpi_ticket_create_submit'),
            default => throw new RuntimeException('Acao invalida para abertura de chamado.'),
        };

        plugin_dashglpi_admin_bridge_json(['ok' => true] + $result);
    } catch (Throwable $e) {
        plugin_dashglpi_admin_bridge_log($e, 'ticket_create');
        $code = in_array((int) $e->getCode(), [400, 403, 404, 409, 422], true) ? (int) $e->getCode() : 500;
        $message = trim((string) $e->getMessage());
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => $message !== '' ? $message : 'Erro interno no bridge de abertura de chamado.'], $code);
    }
}

function plugin_dashglpi_ticket_create_probe_input(array $payload): array
{
    $urgency = plugin_dashglpi_ticket_create_urgency($payload['urgency'] ?? 3);
    $impact = plugin_dashglpi_ticket_create_impact($payload['impact'] ?? 3);

    return [
        'name' => 'Permissao de abertura DashGLPI',
        'content' => 'Verificacao de permissao.',
        'entities_id' => max(0, (int) ($payload['entities_id'] ?? 0)),
        'type' => plugin_dashglpi_ticket_create_type($payload['type'] ?? Ticket::INCIDENT_TYPE),
        'urgency' => $urgency,
        'impact' => $impact,
        'priority' => Ticket::computePriority($urgency, $impact),
        'itilcategories_id' => max(0, (int) ($payload['itilcategories_id'] ?? 0)),
        '_users_id_requester' => max(0, (int) ($payload['requester_id'] ?? 0)),
    ];
}

function plugin_dashglpi_ticket_create_catalog(array $payload): array
{
    $entities = plugin_dashglpi_ticket_create_entities_from_payload($payload['entities'] ?? []);
    $defaultEntityId = plugin_dashglpi_ticket_create_default_entity_id($payload, $entities);
    $selectedEntityId = plugin_dashglpi_ticket_create_selected_entity_id($payload['entities_id'] ?? $defaultEntityId, $entities, $defaultEntityId);
    $type = plugin_dashglpi_ticket_create_type($payload['type'] ?? Ticket::INCIDENT_TYPE);

    return [
        'catalog' => [
            'entities' => $entities,
            'default_entity_id' => $defaultEntityId,
            'selected_entity_id' => $selectedEntityId,
            'selected_type' => $type,
            'types' => [
                ['id' => Ticket::INCIDENT_TYPE, 'label' => Ticket::getTicketTypeName(Ticket::INCIDENT_TYPE)],
                ['id' => Ticket::DEMAND_TYPE, 'label' => Ticket::getTicketTypeName(Ticket::DEMAND_TYPE)],
            ],
            'urgencies' => [
                ['id' => 1, 'label' => CommonITILObject::getUrgencyName(1)],
                ['id' => 2, 'label' => CommonITILObject::getUrgencyName(2)],
                ['id' => 3, 'label' => CommonITILObject::getUrgencyName(3)],
                ['id' => 4, 'label' => CommonITILObject::getUrgencyName(4)],
                ['id' => 5, 'label' => CommonITILObject::getUrgencyName(5)],
            ],
            'default_urgency' => 3,
            'categories' => plugin_dashglpi_ticket_create_categories($selectedEntityId, $type),
            'upload_max_label' => Document::getMaxUploadSize(),
        ],
    ];
}

function plugin_dashglpi_ticket_create_submit(array $payload): array
{
    $requestId = strtolower(trim((string) ($payload['request_id'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{16,64}$/', $requestId)) {
        throw new RuntimeException('Identificador da solicitacao invalido.', 400);
    }
    $requesterId = max(0, (int) ($payload['requester_id'] ?? 0));
    if ($requesterId <= 0) {
        throw new RuntimeException('Solicitante do chamado nao informado.');
    }

    $actorId = max(0, (int) ($payload['actor']['user_id'] ?? 0));
    if ($actorId <= 0) {
        throw new RuntimeException('Usuario de abertura nao informado.', 403);
    }
    $externalId = 'dashglpi:' . $actorId . ':' . $requestId;

    return plugin_dashglpi_admin_bridge_with_ticket_idempotency($externalId, static function () use ($payload, $requesterId, $externalId): array {

    plugin_dashglpi_admin_bridge_require_item(User::class, $requesterId, 'Solicitante do chamado nao encontrado.');

    $entitiesId = max(0, (int) ($payload['entities_id'] ?? 0));
    plugin_dashglpi_admin_bridge_require_item(Entity::class, $entitiesId, 'Entidade do chamado nao encontrada.');

    $type = plugin_dashglpi_ticket_create_type($payload['type'] ?? Ticket::INCIDENT_TYPE);
    $urgency = plugin_dashglpi_ticket_create_urgency($payload['urgency'] ?? 3);
    $impact = plugin_dashglpi_ticket_create_impact($payload['impact'] ?? 3);
    $priority = Ticket::computePriority($urgency, $impact);
    $sla = plugin_dashglpi_ticket_create_sla_resolution($entitiesId, $priority);
    $categoryId = max(0, (int) ($payload['itilcategories_id'] ?? 0));
    $title = plugin_dashglpi_admin_bridge_name((string) ($payload['name'] ?? ''), 'Informe o titulo do chamado.');
    $content = trim((string) ($payload['content'] ?? ''));
    if ($content === '') {
        throw new RuntimeException('Informe a descricao do chamado.');
    }

    $availableCategoryIds = array_map(
        static fn(array $category): int => (int) ($category['id'] ?? 0),
        plugin_dashglpi_ticket_create_categories($entitiesId, $type)
    );
    if ($categoryId > 0 && !in_array($categoryId, $availableCategoryIds, true)) {
        throw new RuntimeException('A categoria selecionada nao esta disponivel para esta entidade.');
    }

    unset($_SESSION['MESSAGE_AFTER_REDIRECT']);

    $ticketInput = [
        'name' => $title,
        'content' => $content,
        'entities_id' => $entitiesId,
        'type' => $type,
        'urgency' => $urgency,
        'impact' => $impact,
        'priority' => $priority,
        'itilcategories_id' => $categoryId,
        '_users_id_requester' => $requesterId,
        '_users_id_requester_notif' => ['use_notification' => 1],
        'users_id' => $requesterId,
        'users_id_recipient' => (int) Session::getLoginUserID(),
        'users_id_lastupdater' => (int) Session::getLoginUserID(),
        'externalid' => $externalId,
    ];
    plugin_dashglpi_ticket_create_apply_sla($ticketInput, $sla);
    plugin_dashglpi_admin_bridge_attach_uploaded_files($ticketInput, 'attachments');

    $ticket = new Ticket();
    $ticketId = (int) $ticket->add($ticketInput);
    if ($ticketId <= 0) {
        throw new RuntimeException(plugin_dashglpi_ticket_create_last_message('Falha ao registrar chamado.'));
    }
    $notificationQueued = plugin_dashglpi_admin_bridge_ensure_ticket_notification($ticketId);
    plugin_dashglpi_ticket_create_log_sla_warnings($ticketId, $sla);

    return [
        'ticket' => [
            'id' => $ticketId,
            'name' => $title,
            'entities_id' => $entitiesId,
            'type' => $type,
            'urgency' => $urgency,
            'impact' => $impact,
            'priority' => $priority,
            'itilcategories_id' => $categoryId,
            'sla' => $sla,
        ],
        'sla' => $sla,
        'message' => plugin_dashglpi_ticket_create_success_message($sla),
        'notification_queued' => $notificationQueued,
    ];
    });
}

function plugin_dashglpi_ticket_create_entities_from_payload($rawEntities): array
{
    $entities = [];
    foreach ((array) $rawEntities as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $id = max(0, (int) ($entry['id'] ?? 0));
        $label = trim((string) ($entry['label'] ?? ''));
        if ($label === '') {
            $label = trim((string) ($entry['completename'] ?? ''));
        }
        if ($label === '') {
            $label = trim((string) ($entry['name'] ?? 'Entidade'));
        }

        $entities[$id] = [
            'id' => $id,
            'name' => (string) ($entry['name'] ?? $label),
            'completename' => (string) ($entry['completename'] ?? $label),
            'label' => $label,
        ];
    }

    if (!$entities) {
        $entities[0] = [
            'id' => 0,
            'name' => 'Entidade raiz',
            'completename' => 'Entidade raiz',
            'label' => 'Entidade raiz',
        ];
    }

    return array_values($entities);
}

function plugin_dashglpi_ticket_create_default_entity_id(array $payload, array $entities): int
{
    $allowedIds = array_map(static fn(array $entity): int => (int) ($entity['id'] ?? 0), $entities);
    $requestedDefault = max(0, (int) ($payload['default_entity_id'] ?? 0));
    if (in_array($requestedDefault, $allowedIds, true)) {
        return $requestedDefault;
    }

    return (int) ($entities[0]['id'] ?? 0);
}

function plugin_dashglpi_ticket_create_selected_entity_id($value, array $entities, int $fallback): int
{
    $allowedIds = array_map(static fn(array $entity): int => (int) ($entity['id'] ?? 0), $entities);
    $selected = max(0, (int) $value);
    return in_array($selected, $allowedIds, true) ? $selected : $fallback;
}

function plugin_dashglpi_ticket_create_type($value): int
{
    $type = (int) $value;
    return in_array($type, [Ticket::INCIDENT_TYPE, Ticket::DEMAND_TYPE], true)
        ? $type
        : Ticket::INCIDENT_TYPE;
}

function plugin_dashglpi_ticket_create_urgency($value): int
{
    $urgency = (int) $value;
    return ($urgency >= 1 && $urgency <= 5) ? $urgency : 3;
}

function plugin_dashglpi_ticket_create_impact($value): int
{
    $impact = (int) $value;
    return ($impact >= 1 && $impact <= 5) ? $impact : 3;
}

function plugin_dashglpi_ticket_create_sla_resolution(int $entitiesId, int $priority): array
{
    try {
        return dashglpi_sla_policy_effective_sla_for_ticket($entitiesId, $priority);
    } catch (Throwable $e) {
        $message = trim((string) $e->getMessage());
        Toolbox::logInFile(
            'php-errors',
            sprintf(
                "[DashGLPI ticket_create:sla] class=%s code=%s message=%s file=%s line=%d\n",
                get_class($e),
                (string) $e->getCode(),
                $message !== '' ? $message : '(empty)',
                $e->getFile(),
                $e->getLine()
            )
        );

        return [
            'applied' => false,
            'origin' => 'none',
            'source_entities_id' => 0,
            'source_entity_name' => '',
            'priority' => $priority,
            'tto_key' => '',
            'tto_id' => 0,
            'ttr_key' => '',
            'ttr_id' => 0,
            'warnings' => ['Nao foi possivel resolver a politica SLA da entidade.'],
        ];
    }
}

function plugin_dashglpi_ticket_create_apply_sla(array &$ticketInput, array $sla): void
{
    $ttoId = max(0, (int) ($sla['tto_id'] ?? 0));
    $ttrId = max(0, (int) ($sla['ttr_id'] ?? 0));

    if ($ttoId > 0) {
        $ticketInput['slas_id_tto'] = $ttoId;
    }

    if ($ttrId > 0) {
        $ticketInput['slas_id_ttr'] = $ttrId;
    }
}

function plugin_dashglpi_ticket_create_log_sla_warnings(int $ticketId, array $sla): void
{
    $warnings = array_values(array_filter(array_map('strval', (array) ($sla['warnings'] ?? []))));
    if (!$warnings) {
        return;
    }

    Toolbox::logInFile(
        'php-errors',
        sprintf(
            "[DashGLPI ticket_create:sla] ticket=%d entity_source=%d warning=%s\n",
            $ticketId,
            (int) ($sla['source_entities_id'] ?? 0),
            implode(' | ', $warnings)
        )
    );
}

function plugin_dashglpi_ticket_create_success_message(array $sla): string
{
    $warnings = array_values(array_filter(array_map('strval', (array) ($sla['warnings'] ?? []))));
    if (!empty($sla['applied']) && !$warnings) {
        return 'Chamado criado com sucesso com SLA da entidade aplicado.';
    }

    if (!empty($sla['applied'])) {
        return 'Chamado criado com sucesso com SLA parcial da entidade aplicado.';
    }

    if ($warnings) {
        return 'Chamado criado com sucesso. Revise os avisos da politica SLA da entidade.';
    }

    return 'Chamado criado com sucesso.';
}

function plugin_dashglpi_ticket_create_categories(int $entitiesId, int $type): array
{
    global $DB;

    $entityScope = [$entitiesId];
    if ($entitiesId > 0) {
        foreach (getAncestorsOf('glpi_entities', $entitiesId) as $ancestorId) {
            $entityScope[] = (int) $ancestorId;
        }
    }
    $entityScope[] = 0;
    $entityScope = array_values(array_unique(array_map('intval', $entityScope)));

    $typeField = $type === Ticket::DEMAND_TYPE ? 'is_request' : 'is_incident';
    $categories = [];
    foreach ($DB->request([
        'SELECT' => ['id', 'name', 'completename', 'itilcategories_id', 'entities_id', 'is_recursive'],
        'FROM' => 'glpi_itilcategories',
        'WHERE' => [
            'is_helpdeskvisible' => 1,
            $typeField => 1,
            'entities_id' => $entityScope,
        ],
        'ORDER' => ['completename ASC', 'name ASC'],
    ]) as $row) {
        $categoryEntityId = (int) ($row['entities_id'] ?? 0);
        $appliesToEntity = $categoryEntityId === $entitiesId
            || (!empty($row['is_recursive']) && in_array($categoryEntityId, $entityScope, true));
        if (!$appliesToEntity) {
            continue;
        }

        $label = plugin_dashglpi_ticket_create_category_path_label($row);

        $categories[] = [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'completename' => (string) ($row['completename'] ?? ''),
            'label' => $label,
        ];
    }

    return $categories;
}

function plugin_dashglpi_ticket_create_category_path_label(array $row): string
{
    global $DB;

    $parts = [];
    $currentName = trim((string) ($row['name'] ?? ''));
    if ($currentName !== '') {
        $parts[] = $currentName;
    }

    $parentId = (int) ($row['itilcategories_id'] ?? 0);
    $visited = [(int) ($row['id'] ?? 0)];
    $guard = 0;

    while ($parentId > 0 && $guard < 100 && !in_array($parentId, $visited, true)) {
        $visited[] = $parentId;
        $iterator = $DB->request([
            'SELECT' => ['id', 'name', 'itilcategories_id'],
            'FROM' => 'glpi_itilcategories',
            'WHERE' => ['id' => $parentId],
            'LIMIT' => 1,
        ]);
        $parent = null;
        foreach ($iterator as $parentRow) {
            $parent = $parentRow;
            break;
        }
        if (!$parent) {
            break;
        }

        $parentName = trim((string) ($parent['name'] ?? ''));
        if ($parentName !== '') {
            array_unshift($parts, $parentName);
        }
        $parentId = (int) ($parent['itilcategories_id'] ?? 0);
        $guard++;
    }

    if ($parts) {
        return implode(' > ', $parts);
    }

    $fallback = trim((string) ($row['completename'] ?? ''));
    return $fallback !== '' ? $fallback : 'Categoria';
}

function plugin_dashglpi_ticket_create_last_message(string $fallback): string
{
    $messages = $_SESSION['MESSAGE_AFTER_REDIRECT'] ?? [];
    $bucket = [];
    foreach ($messages as $items) {
        foreach ((array) $items as $message) {
            $message = trim((string) $message);
            if ($message !== '') {
                $bucket[] = strip_tags($message);
            }
        }
    }

    unset($_SESSION['MESSAGE_AFTER_REDIRECT']);

    if ($bucket) {
        return implode(' ', array_unique($bucket));
    }

    return $fallback;
}
