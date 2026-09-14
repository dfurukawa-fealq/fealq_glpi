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

function plugin_dashglpi_admin_bridge_log(Throwable $e, string $scope): void
{
    $message = trim((string) $e->getMessage());
    Toolbox::logInFile(
        'php-errors',
        sprintf(
            "[DashGLPI admin bridge:%s] class=%s code=%s message=%s file=%s line=%d\n",
            $scope,
            get_class($e),
            (string) $e->getCode(),
            $message !== '' ? $message : '(empty)',
            $e->getFile(),
            $e->getLine()
        )
    );
}
