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
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
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

        return $decoded;
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || !is_array($input['payload'] ?? null)) {
        plugin_dashglpi_admin_bridge_json(['ok' => false, 'error' => 'JSON invalido.'], 400);
    }

    if (empty($_SESSION['glpi_currenttime'])) {
        $_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
    }

    return $input['payload'];
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

    if (!is_array($names)) {
        $names = [$names];
        $tmpNames = [$tmpNames];
        $errors = [$errors];
        $types = [$types];
    }

    $files = [];
    foreach ($names as $index => $name) {
        $error = (int) ($errors[$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha ao receber um dos anexos enviados ao GLPI.');
        }

        $tmpName = (string) ($tmpNames[$index] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('Arquivo temporario do anexo nao encontrado no GLPI.');
        }

        $files[] = [
            'name' => (string) $name,
            'tmp_name' => $tmpName,
            'type' => (string) ($types[$index] ?? 'application/octet-stream'),
        ];
    }

    return $files;
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
    if (!defined('GLPI_LOG_DIR')) {
        define('GLPI_LOG_DIR', (string) (getenv('GLPI_LOG_DIR') ?: '/var/glpi/logs'));
    }
    if (!defined('GLPI_LOG_LVL')) {
        define('GLPI_LOG_LVL', 'warning');
    }
    if (!defined('GLPI_CACHE_DIR')) {
        define('GLPI_CACHE_DIR', rtrim((string) (getenv('GLPI_VAR_DIR') ?: '/var/glpi'), '/') . '/_cache');
    }
    if (!defined('GLPI_MARKETPLACE_DIR')) {
        define('GLPI_MARKETPLACE_DIR', (string) (getenv('GLPI_MARKETPLACE_DIR') ?: '/var/glpi/marketplace'));
    }
    if (!defined('GLPI_PLUGINS_DIRECTORIES')) {
        define('GLPI_PLUGINS_DIRECTORIES', [GLPI_ROOT . '/plugins', GLPI_MARKETPLACE_DIR]);
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
    global $DB;

    if (is_object($DB) && class_exists('DBmysql') && is_a($DB, 'DBmysql')) {
        return;
    }

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
function plugin_dashglpi_admin_bridge_log(Throwable $e, string $scope): void
{
    $message = trim((string) $e->getMessage());
    $line = sprintf(
        "[DashGLPI admin bridge:%s] class=%s code=%s message=%s file=%s line=%d",
        $scope,
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
