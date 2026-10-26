<?php

function plugin_dashglpi_admin_bridge_require_token(): void
{
    $expected = (string) getenv('DASHGLPI_BRIDGE_TOKEN');
    if ($expected === '') {
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => 'Bridge nao configurado.'], 503);
    }

    $provided = (string) ($_SERVER['HTTP_X_DASHGLPI_BRIDGE_TOKEN'] ?? '');
    if (!hash_equals($expected, $provided)) {
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => 'Token invalido.'], 403);
    }
}

function plugin_dashglpi_admin_bridge_json(array $data, int $status = 200): void
{
    $requestId = plugin_dashglpi_admin_bridge_request_id();
    $data['request_id'] ??= $requestId;
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Request-ID: ' . $requestId);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function plugin_dashglpi_admin_bridge_request_id(): string
{
    $requestId = strtolower(trim((string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')));
    if (!preg_match('/^[a-f0-9-]{16,64}$/', $requestId)) {
        try {
            $requestId = bin2hex(random_bytes(16));
        } catch (Throwable) {
            $requestId = substr(sha1(uniqid('', true)), 0, 32);
        }
    }
    $GLOBALS['DASHGLPI_BRIDGE_REQUEST_ID'] = $requestId;
    if (!headers_sent()) {
        header('X-Request-ID: ' . $requestId);
    }
    return $requestId;
}

function plugin_dashglpi_admin_bridge_payload(): array
{
    plugin_dashglpi_admin_bridge_require_token();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => 'Metodo nao permitido.'], 405);
    }

    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'multipart/form-data') !== false) {
        $payload = $_POST['payload'] ?? null;
        $decoded = is_string($payload) ? json_decode($payload, true) : null;
        if (!is_array($decoded)) {
            plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => 'Payload multipart invalido.'], 400);
        }

        if (empty($_SESSION['glpi_currenttime'])) {
            $_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
        }

        return plugin_dashglpi_admin_bridge_payload_with_request_id($decoded);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || !is_array($input['payload'] ?? null)) {
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => 'JSON invalido.'], 400);
    }

    if (empty($_SESSION['glpi_currenttime'])) {
        $_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
    }

    return plugin_dashglpi_admin_bridge_payload_with_request_id($input['payload']);
}

function plugin_dashglpi_admin_bridge_payload_with_request_id(array $payload): array
{
    $headerId = plugin_dashglpi_admin_bridge_request_id();
    $payloadId = strtolower(trim((string) ($payload['request_id'] ?? '')));
    if (preg_match('/^[a-f0-9-]{16,64}$/', $payloadId) && hash_equals($headerId, $payloadId)) {
        $GLOBALS['DASHGLPI_BRIDGE_REQUEST_ID'] = $payloadId;
        return $payload;
    }
    if ($payloadId !== '' && preg_match('/^[a-f0-9-]{16,64}$/', $payloadId)) {
        throw new RuntimeException('Identificador de correlacao inconsistente.', 400);
    }
    $payload['request_id'] = $headerId;
    return $payload;
}

function plugin_dashglpi_admin_bridge_uploaded_files(string $field): array
{
    if (!isset($_FILES[$field])) {
        return [];
    }

    $entry = $_FILES[$field];
    $names = $entry['name'] ?? [];
    $tmpNames = $entry['tmp_name'] ?? [];
    $errors = $entry['error'] ?? [];
    $types = $entry['type'] ?? [];
    $sizes = $entry['size'] ?? [];

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $errors = [$errors];
        $types = [$types];
        $sizes = [$sizes];
    }

    if (count($names) > 10) {
        throw new RuntimeException('O chamado aceita no maximo 10 anexos.', 422);
    }

    $files = [];
    $totalBytes = 0;
    $maxFileBytes = plugin_dashglpi_admin_bridge_ini_bytes((string) ini_get('upload_max_filesize'));
    $maxPostBytes = plugin_dashglpi_admin_bridge_ini_bytes((string) ini_get('post_max_size'));
    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            $status = in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) ? 413 : 400;
            throw new RuntimeException('Falha ao receber um dos anexos enviados ao GLPI.', $status);
        }

        $tmpName = (string) ($tmpNames[$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Arquivo temporario do anexo nao encontrado no GLPI.', 400);
        }

        $size = (int) ($sizes[$index] ?? 0);
        if ($size <= 0 || ($maxFileBytes > 0 && $size > $maxFileBytes)) {
            throw new RuntimeException('Anexo vazio ou acima do limite por arquivo do GLPI.', 422);
        }
        $totalBytes += $size;
        if ($maxPostBytes > 0 && $totalBytes > $maxPostBytes) {
            throw new RuntimeException('O tamanho total dos anexos excede o limite de envio do GLPI.', 422);
        }

        $clientName = basename(str_replace('\\', '/', (string) $name));
        if ($clientName === '' || strlen($clientName) > 240 || preg_match('/[\x00-\x1F\x7F]/', $clientName)) {
            throw new RuntimeException('Nome de anexo invalido ou muito longo.', 422);
        }
        if (preg_match('/\.(php\d*|phtml|phar|cgi|pl|py|sh|shtml)$/i', $clientName)) {
            throw new RuntimeException('Este tipo de arquivo nao pode ser anexado.', 422);
        }

        $mimeType = (string) ($types[$index] ?? 'application/octet-stream');
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $detected = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if (is_string($detected) && $detected !== '') {
                    $mimeType = $detected;
                }
            }
        }

        $files[] = [
            'name' => $clientName,
            'tmp_name' => $tmpName,
            'type' => $mimeType,
            'size' => $size,
        ];
    }

    return $files;
}

function plugin_dashglpi_admin_bridge_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $number = (float) $value;
    $unit = strtolower(substr($value, -1));
    return (int) ($number * match ($unit) {
        'g' => 1024 * 1024 * 1024,
        'm' => 1024 * 1024,
        'k' => 1024,
        default => 1,
    });
}

function plugin_dashglpi_admin_bridge_ticket_document_category_id(): int
{
    global $CFG_GLPI, $DB;

    $configuredId = max(0, (int) ($CFG_GLPI['documentcategories_id_forticket'] ?? 0));
    if ($configuredId > 0) {
        $category = new DocumentCategory();
        if ($category->getFromDB($configuredId)) {
            return $configuredId;
        }
    }

    $fallbackName = 'Anexos de Chamado';
    foreach ($DB->request([
        'SELECT' => ['id'],
        'FROM' => 'glpi_documentcategories',
        'WHERE' => ['name' => $fallbackName],
        'LIMIT' => 1,
    ]) as $row) {
        $categoryId = max(0, (int) ($row['id'] ?? 0));
        if ($categoryId > 0) {
            $CFG_GLPI['documentcategories_id_forticket'] = $categoryId;
            return $categoryId;
        }
    }

    foreach ($DB->request([
        'SELECT' => ['id'],
        'FROM' => 'glpi_documentcategories',
        'ORDER' => 'id ASC',
        'LIMIT' => 1,
    ]) as $row) {
        $categoryId = max(0, (int) ($row['id'] ?? 0));
        if ($categoryId > 0) {
            $CFG_GLPI['documentcategories_id_forticket'] = $categoryId;
            return $categoryId;
        }
    }

    $category = new DocumentCategory();
    $categoryId = (int) $category->add([
        'name' => $fallbackName,
        'comment' => 'Categoria padrao usada pelo DashGLPI para anexos de chamados.',
    ]);
    if ($categoryId <= 0) {
        throw new RuntimeException('Nao foi possivel criar categoria padrao para anexos de chamados.');
    }

    $CFG_GLPI['documentcategories_id_forticket'] = $categoryId;
    return $categoryId;
}
function plugin_dashglpi_admin_bridge_find_one(string $class, array $criteria): ?array
{
    $item = new $class();
    $rows = $item->find($criteria, 'id ASC', 1);
    if (!$rows) {
        return null;
    }

    $row = reset($rows);
    return is_array($row) ? $row : null;
}

function plugin_dashglpi_admin_bridge_require_item(string $class, int $id, string $message): array
{
    if ($class === Entity::class && $id === 0) {
        return ['id' => 0, 'name' => 'Entidade raiz'];
    }

    $item = new $class();
    if ($id < 0 || !$item->getFromDB($id)) {
        throw new RuntimeException($message);
    }

    return $item->fields;
}

function plugin_dashglpi_admin_bridge_name(string $value, string $message): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        throw new RuntimeException($message);
    }

    return substr($value, 0, 255);
}

function plugin_dashglpi_admin_bridge_last_message(string $fallback): string
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

    return $bucket ? implode(' ', array_unique($bucket)) : $fallback;
}
/**
 * Wrapper para bridges `*_config.php` (Padrão duplicado B do PLAN-20260703-013).
 *
 * Encapsula: `plugin_dashglpi_admin_bridge_payload()`, execução de `$fn` dentro de
 * `Session::callAsSystem()`, try/catch com log via `plugin_dashglpi_admin_bridge_log()`
 * e resposta padrão `{ok:true/false,...}`. `$fn` recebe o payload decodificado e
 * retorna o valor de `result`.
 *
 * Fase 1: helper introduzido, nenhum bridge existente migrado ainda.
 */
function plugin_dashglpi_admin_bridge_handle(string $scope, callable $fn, string $fallbackMessage = 'Erro interno no bridge.'): void
{
    try {
        $payload = plugin_dashglpi_admin_bridge_payload();
        plugin_dashglpi_admin_bridge_bootstrap_constants();
        plugin_dashglpi_admin_bridge_bootstrap_logger();
        plugin_dashglpi_admin_bridge_bootstrap_cache();
        plugin_dashglpi_admin_bridge_bootstrap_db();

        $result = Session::callAsSystem(static function () use ($payload, $fn) {
            return $fn($payload);
        });

        plugin_dashglpi_admin_bridge_json(['ok' => true] + (is_array($result) ? $result : ['result' => $result]));
    } catch (Throwable $e) {
        plugin_dashglpi_admin_bridge_log($e, $scope);
        $message = trim((string) $e->getMessage());
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => $message !== '' ? $message : $fallbackMessage], 500);
    }
}

/**
 * Consolida o trecho repetido `require_item(Ticket::class,...) + checagem is_deleted`
 * usado pelos bridges de ticket. Mensagens parametrizáveis pois cada bridge usa um texto
 * diferente para "chamado removido" (ex.: "...nao pode ser cancelado.", "...atribuido.").
 *
 * Fase 1: disponível, não migrado ainda.
 */
function plugin_dashglpi_admin_bridge_require_ticket(
    int $id,
    string $notFoundMessage = 'Chamado nao encontrado.',
    ?string $deletedMessage = null
): array {
    $fields = plugin_dashglpi_admin_bridge_require_item(Ticket::class, $id, $notFoundMessage);

    if ($deletedMessage !== null && !empty($fields['is_deleted'])) {
        throw new RuntimeException($deletedMessage);
    }

    return $fields;
}

function plugin_dashglpi_admin_bridge_bootstrap_constants(): void
{
    $root = dirname(__DIR__, 3);
    $autoload = $root . '/vendor/autoload.php';
    if (!defined('GLPI_ROOT') && is_file($autoload)) {
        require_once $autoload;
    }
    if (!defined('GLPI_ROOT')) {
        define('GLPI_ROOT', $root);
    }
    if (!defined('GLPI_CONFIG_DIR')) {
        define('GLPI_CONFIG_DIR', (string) (getenv('GLPI_CONFIG_DIR') ?: '/var/glpi/config'));
    }
    if (!defined('GLPI_VAR_DIR')) {
        define('GLPI_VAR_DIR', (string) (getenv('GLPI_VAR_DIR') ?: '/var/glpi/files'));
    }

    $varDir = rtrim((string) GLPI_VAR_DIR, '/');
    $directoryConstants = [
        'GLPI_DOC_DIR' => $varDir,
        'GLPI_CACHE_DIR' => $varDir . '/_cache',
        'GLPI_CRON_DIR' => $varDir . '/_cron',
        'GLPI_GRAPH_DIR' => $varDir . '/_graphs',
        'GLPI_LOCAL_I18N_DIR' => $varDir . '/_locales',
        'GLPI_LOCK_DIR' => $varDir . '/_lock',
        'GLPI_LOG_DIR' => $varDir . '/_log',
        'GLPI_PICTURE_DIR' => $varDir . '/_pictures',
        'GLPI_PLUGIN_DOC_DIR' => $varDir . '/_plugins',
        'GLPI_RSS_DIR' => $varDir . '/_rss',
        'GLPI_SESSION_DIR' => $varDir . '/_sessions',
        'GLPI_TMP_DIR' => $varDir . '/_tmp',
        'GLPI_UPLOAD_DIR' => $varDir . '/_uploads',
        'GLPI_INVENTORY_DIR' => $varDir . '/_inventories',
        'GLPI_THEMES_DIR' => $varDir . '/_themes',
    ];

    foreach ($directoryConstants as $constant => $directory) {
        if (!defined($constant)) {
            define($constant, $directory);
        }
    }

    if (!defined('GLPI_LOG_LVL')) {
        define('GLPI_LOG_LVL', 'warning');
    }
    if (!defined('GLPI_SKIP_UPDATES')) {
        define('GLPI_SKIP_UPDATES', false);
    }
    if (!defined('GLPI_ALLOW_IFRAME_IN_RICH_TEXT')) {
        define('GLPI_ALLOW_IFRAME_IN_RICH_TEXT', false);
    }
    if (!defined('GLPI_DISALLOWED_UPLOADS_PATTERN')) {
        define('GLPI_DISALLOWED_UPLOADS_PATTERN', '/\.(php\d*|phar)$/i');
    }
    if (!defined('GLPI_MARKETPLACE_DIR')) {
        define('GLPI_MARKETPLACE_DIR', (string) (getenv('GLPI_MARKETPLACE_DIR') ?: GLPI_ROOT . '/marketplace'));
    }
    if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
        define('GLPI_PLUGINS_DIRECTORIES', [GLPI_MARKETPLACE_DIR, GLPI_ROOT . '/plugins']);
    }

    foreach ($directoryConstants as $directory) {
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
    }
}
function plugin_dashglpi_admin_bridge_bootstrap_logger(): void
{
    global $PHPLOGGER;

    if ($PHPLOGGER instanceof Psr\Log\LoggerInterface) {
        return;
    }

    if (!class_exists(Monolog\Logger::class)) {
        if (class_exists(Psr\Log\NullLogger::class)) {
            $PHPLOGGER = new Psr\Log\NullLogger();
            return;
        }

        if (interface_exists(Psr\Log\LoggerInterface::class)) {
            $PHPLOGGER = new class implements Psr\Log\LoggerInterface {
                public function emergency(Stringable|string $message, array $context = []): void { $this->log('emergency', $message, $context); }
                public function alert(Stringable|string $message, array $context = []): void { $this->log('alert', $message, $context); }
                public function critical(Stringable|string $message, array $context = []): void { $this->log('critical', $message, $context); }
                public function error(Stringable|string $message, array $context = []): void { $this->log('error', $message, $context); }
                public function warning(Stringable|string $message, array $context = []): void { $this->log('warning', $message, $context); }
                public function notice(Stringable|string $message, array $context = []): void { $this->log('notice', $message, $context); }
                public function info(Stringable|string $message, array $context = []): void { $this->log('info', $message, $context); }
                public function debug(Stringable|string $message, array $context = []): void { $this->log('debug', $message, $context); }
                public function log($level, Stringable|string $message, array $context = []): void { error_log('[DashGLPI bridge:' . $level . '] ' . (string) $message); }
            };
            return;
        }

        return;
    }

    $PHPLOGGER = new Monolog\Logger('glpi');
    if (class_exists(Glpi\Log\ErrorLogHandler::class)) {
        $PHPLOGGER->pushHandler(new Glpi\Log\ErrorLogHandler());
    }
    if (class_exists(Glpi\Log\AccessLogHandler::class)) {
        $PHPLOGGER->pushHandler(new Glpi\Log\AccessLogHandler());
    }
}
function plugin_dashglpi_admin_bridge_bootstrap_cache(): void
{
    global $GLPI_CACHE;

    if ($GLPI_CACHE instanceof Psr\SimpleCache\CacheInterface) {
        return;
    }

    if (!class_exists(Glpi\Cache\CacheManager::class)) {
        if (interface_exists(Psr\SimpleCache\CacheInterface::class)) {
            $GLPI_CACHE = new class implements Psr\SimpleCache\CacheInterface {
                private array $values = [];
                public function get(string $key, mixed $default = null): mixed { return array_key_exists($key, $this->values) ? $this->values[$key] : $default; }
                public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool { $this->values[$key] = $value; return true; }
                public function delete(string $key): bool { unset($this->values[$key]); return true; }
                public function clear(): bool { $this->values = []; return true; }
                public function getMultiple(iterable $keys, mixed $default = null): iterable { foreach ($keys as $key) { yield $key => $this->get((string) $key, $default); } }
                public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool { foreach ($values as $key => $value) { $this->set((string) $key, $value, $ttl); } return true; }
                public function deleteMultiple(iterable $keys): bool { foreach ($keys as $key) { $this->delete((string) $key); } return true; }
                public function has(string $key): bool { return array_key_exists($key, $this->values); }
            };
            return;
        }

        return;
    }

    $GLPI_CACHE = (new Glpi\Cache\CacheManager())->getCoreCacheInstance();
}
function plugin_dashglpi_admin_bridge_bootstrap_db(): void
{
    global $CFG_GLPI, $DB;

    if (!(is_object($DB) && class_exists('DBmysql') && is_a($DB, 'DBmysql'))) {
        if (!class_exists('DB')) {
            $configDir = (string) (getenv('GLPI_CONFIG_DIR') ?: '/var/glpi/config');
            $configDb = rtrim($configDir, '/') . '/config_db.php';
            if (is_file($configDb)) {
                require_once $configDb;
            }
        }

        if (!class_exists('DB')) {
            throw new RuntimeException('Bootstrap GLPI incompleto: classe DB indisponivel.');
        }

        $DB = new DB();
    }

    // The isolated plugin bridge loads GLPI classes without booting its web
    // kernel. CFG_GLPI therefore contains defaults, not the persisted values
    // from glpi_configs. Load the native legacy configuration while hiding
    // REQUEST_URI temporarily: GLPI otherwise asks the absent Symfony
    // RequestStack for a main request. Restore the request immediately after.
    if (!class_exists(Config::class) || !DBConnection::isDbAvailable()) {
        throw new RuntimeException('Bootstrap GLPI incompleto: configuracao persistida indisponivel.');
    }

    $hadRequestUri = array_key_exists('REQUEST_URI', $_SERVER);
    $requestUri = $_SERVER['REQUEST_URI'] ?? null;
    $rootDoc = $CFG_GLPI['root_doc'] ?? '';
    unset($_SERVER['REQUEST_URI']);
    try {
        $loaded = Config::loadLegacyConfiguration();
    } finally {
        if ($hadRequestUri) {
            $_SERVER['REQUEST_URI'] = $requestUri;
        }
    }
    if ($loaded !== true) {
        throw new RuntimeException('GLPI nao carregou as configuracoes persistidas do banco.');
    }
    // This request only returns JSON and has no GLPI page base path.
    $CFG_GLPI['root_doc'] = $rootDoc;
}

/**
 * Prepare the native GLPI session for an actor used by an isolated bridge
 * request. Keep this in one place because GLPI 11 and 12 differ in how they
 * validate user preferences during loadPreferencesInSession().
 */
function plugin_dashglpi_admin_bridge_prepare_actor_session(User $user): void
{
    $userId = max(0, (int) ($user->fields['id'] ?? 0));
    if ($userId <= 0) {
        throw new RuntimeException('Usuario do bridge nao informado.', 403);
    }

    $_SESSION = [];
    Session::initVars();
    $_SESSION['glpiID'] = $userId;
    $_SESSION['glpiname'] = (string) ($user->fields['name'] ?? '');
    $_SESSION['glpidefault_entity'] = (int) ($user->fields['entities_id'] ?? 0);

    // GLPI 12 requires a string language code. Older records may contain NULL;
    // use the installation language only for this request, without persisting
    // anything back to glpi_users.
    $language = trim((string) ($user->fields['language'] ?? ''));
    if ($language === '') {
        $language = 'pt_BR';
        $user->fields['language'] = $language;
    }
    $_SESSION['glpilanguage'] = $language;

    $user->loadPreferencesInSession();
    Session::initEntityProfiles($userId);
}

/** Run a bridge operation with the validated user's native GLPI profile/session. */
function plugin_dashglpi_admin_bridge_as_actor(array $payload, callable $handler): array
{
    $hadSession = array_key_exists('_SESSION', $GLOBALS);
    $saved = $hadSession ? $_SESSION : null;
    try {
        $userId = (int) ($payload['actor']['user_id'] ?? 0);
        $requestedProfileId = (int) ($payload['actor']['profile_id'] ?? 0);
        $user = new User();
        if ($userId <= 0 || !$user->getFromDB($userId) || !$user->fields['is_active'] || $user->fields['is_deleted']) {
            throw new RuntimeException('Usuario de abertura invalido.', 403);
        }

        $now = date('Y-m-d H:i:s');
        if ((!empty($user->fields['begin_date']) && $user->fields['begin_date'] > $now)
            || (!empty($user->fields['end_date']) && $user->fields['end_date'] < $now)) {
            throw new RuntimeException('Usuario fora do periodo de validade.', 403);
        }

        plugin_dashglpi_admin_bridge_prepare_actor_session($user);
        $availableProfiles = array_map('intval', array_keys($_SESSION['glpiprofiles'] ?? []));
        if ($requestedProfileId > 0 && !in_array($requestedProfileId, $availableProfiles, true)) {
            throw new RuntimeException('Perfil de abertura nao pertence ao usuario.', 403);
        }

        $candidateProfiles = $requestedProfileId > 0
            ? array_values(array_unique(array_merge([$requestedProfileId], $availableProfiles)))
            : $availableProfiles;
        $probeInput = function_exists('plugin_dashglpi_ticket_create_probe_input')
            ? plugin_dashglpi_ticket_create_probe_input($payload)
            : [];
        foreach ($candidateProfiles as $candidateProfileId) {
            Session::changeProfile($candidateProfileId);
            if (!isset($_SESSION['glpiactiveprofile'])) {
                continue;
            }
            Session::changeActiveEntities('all');
            Session::loadGroups();
            $ticket = new Ticket();
            if ($ticket->can(-1, CREATE, $probeInput)) {
                return $handler($payload);
            }
        }
        throw new RuntimeException('Perfil sem permissao GLPI para abrir chamado nesta entidade.', 403);
    } finally {
        if ($hadSession) {
            $_SESSION = $saved;
        } else {
            unset($_SESSION);
        }
    }
}

/** Add uploaded documents to a native GLPI item input before add(). */
function plugin_dashglpi_admin_bridge_attach_uploaded_files(array &$itemInput, string $field = 'attachments'): void
{
    $uploadedFiles = plugin_dashglpi_admin_bridge_uploaded_files($field);
    if (!$uploadedFiles) {
        return;
    }

    plugin_dashglpi_admin_bridge_ticket_document_category_id();
    foreach ($uploadedFiles as $index => $file) {
        $originalName = basename((string) ($file['name'] ?? 'arquivo'));
        $prefix = substr(sha1((string) microtime(true) . $index . $originalName), 0, 23);
        if (is_array($_FILES[$field]['name'] ?? null)) {
            $_FILES[$field]['name'][$index] = $prefix . $originalName;
        } else {
            $_FILES[$field]['name'] = $prefix . $originalName;
        }
    }

    $uploadResult = GLPIUploadHandler::uploadFiles([
        'name' => $field,
        'print_response' => false,
    ]);
    foreach ($uploadResult as $group) {
        foreach ((array) $group as $fileResult) {
            if (!empty($fileResult->error)) {
                throw new RuntimeException((string) $fileResult->error);
            }
            if (!isset($fileResult->name)) {
                continue;
            }
            $itemInput['_filename'][] = (string) $fileResult->name;
            $itemInput['_prefix_filename'][] = (string) ($fileResult->prefix ?? '');
        }
    }
}

/** Ensure the native GLPI notification queue contains the ticket event. */
function plugin_dashglpi_admin_bridge_ensure_ticket_notification(int $ticketId): bool
{
    if ($ticketId <= 0 || !class_exists(NotificationEvent::class)) {
        plugin_dashglpi_admin_bridge_notification_log($ticketId, false, 'notification_event_unavailable');
        return false;
    }

    try {
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) {
            plugin_dashglpi_admin_bridge_notification_log($ticketId, false, 'ticket_not_found');
            return false;
        }
        if (plugin_dashglpi_admin_bridge_has_ticket_notification($ticketId)) {
            return true;
        }

        global $CFG_GLPI;
        $mailModeActive = class_exists(Notification_NotificationTemplate::class)
            ? Notification_NotificationTemplate::hasActiveMode()
            : false;
        $raiseResult = NotificationEvent::raiseEvent('new', $ticket);
        $queued = plugin_dashglpi_admin_bridge_has_ticket_notification($ticketId);
        $profileId = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        $sessionUserId = (int) ($_SESSION['glpiID'] ?? 0);
        $reason = sprintf(
            'raise_return=%s use_notifications=%d notifications_mailing=%d active_mail_mode=%d session_user=%d profile=%d entity=%d %s',
            var_export($raiseResult, true),
            (int) ($CFG_GLPI['use_notifications'] ?? 0),
            (int) ($CFG_GLPI['notifications_mailing'] ?? 0),
            (int) $mailModeActive,
            $sessionUserId,
            $profileId,
            (int) ($ticket->fields['entities_id'] ?? 0),
            $queued ? 'queued_after_raise' : 'queue_empty_after_raise'
        );
        plugin_dashglpi_admin_bridge_notification_log($ticketId, $queued, $reason);
        return $queued;
    } catch (Throwable $e) {
        // The ticket is already created. Notification failure must remain
        // observable without converting a successful add() into HTTP 500.
        plugin_dashglpi_admin_bridge_notification_log($ticketId, false, get_class($e) . ': ' . trim((string) $e->getMessage()));
        return false;
    }
}

function plugin_dashglpi_admin_bridge_notification_log(int $ticketId, bool $queued, string $reason): void
{
    $line = sprintf(
        '[DashGLPI bridge:ticket_notification request_id=%s] ticket=%d queued=%s reason=%s',
        (string) ($GLOBALS['DASHGLPI_BRIDGE_REQUEST_ID'] ?? plugin_dashglpi_admin_bridge_request_id()),
        $ticketId,
        $queued ? 'yes' : 'no',
        str_replace(["\r", "\n"], ' ', $reason)
    );
    error_log($line);
    if (class_exists('Toolbox')) {
        Toolbox::logInFile('php-errors', $line . "\n");
    }
}

function plugin_dashglpi_admin_bridge_has_ticket_notification(int $ticketId): bool
{
    global $DB;
    if (!$DB->tableExists('glpi_queuednotifications')) {
        return false;
    }

    $where = ['itemtype' => 'Ticket', 'items_id' => $ticketId, 'is_deleted' => 0];
    if ($DB->fieldExists('glpi_queuednotifications', 'event', false)) {
        $where['event'] = 'new';
    }
    $row = $DB->request([
        'COUNT' => 'total',
        'FROM' => 'glpi_queuednotifications',
        'WHERE' => $where,
    ])->current();

    return (int) ($row['total'] ?? 0) > 0;
}

/** Serialize retries for one actor/request and reuse the original native ticket. */
function plugin_dashglpi_admin_bridge_with_ticket_idempotency(string $externalId, callable $create): array
{
    global $DB;
    $lockName = 'dashglpi_ticket_' . substr(hash('sha256', $externalId), 0, 40);
    $result = $DB->doQuery("SELECT GET_LOCK('" . $lockName . "', 15) AS lock_status");
    if ($result === false) {
        throw new RuntimeException('Nao foi possivel reservar a chave de abertura no banco.', 503);
    }
    $row = $DB->fetchArray($result);
    if ((int) ($row['lock_status'] ?? 0) !== 1) {
        throw new RuntimeException('Esta abertura ainda esta sendo processada. Tente novamente em instantes.', 409);
    }

    try {
        $existing = plugin_dashglpi_admin_bridge_find_one(Ticket::class, [
            'externalid' => $externalId,
            'is_deleted' => 0,
        ]);
        if ($existing) {
            $existingTicketId = (int) ($existing['id'] ?? 0);
            $notificationQueued = plugin_dashglpi_admin_bridge_ensure_ticket_notification($existingTicketId);
            return [
                'ticket' => [
                    'id' => $existingTicketId,
                    'name' => (string) ($existing['name'] ?? ''),
                    'entities_id' => (int) ($existing['entities_id'] ?? 0),
                    'type' => (int) ($existing['type'] ?? Ticket::INCIDENT_TYPE),
                    'priority' => (int) ($existing['priority'] ?? 0),
                ],
                'sla' => null,
                'message' => 'Chamado ja havia sido registrado; retorno recuperado com seguranca.',
                'idempotent_replay' => true,
                'notification_queued' => $notificationQueued,
            ];
        }

        return $create();
    } finally {
        $DB->doQuery("SELECT RELEASE_LOCK('" . $lockName . "')");
    }
}

function plugin_dashglpi_admin_bridge_log(Throwable $e, string $scope): void
{
    $message = trim((string) $e->getMessage());
    $line = sprintf(
        "[DashGLPI admin bridge:%s request_id=%s] class=%s code=%s message=%s file=%s line=%d",
        $scope,
        (string) ($GLOBALS['DASHGLPI_BRIDGE_REQUEST_ID'] ?? plugin_dashglpi_admin_bridge_request_id()),
        get_class($e),
        (string) $e->getCode(),
        $message !== '' ? $message : '(empty)',
        $e->getFile(),
        $e->getLine()
    );

    error_log($line);

    if (class_exists('Toolbox')) {
        Toolbox::logInFile('php-errors', $line . "\n");
    }
}
