<?php

require_once __DIR__ . '/ajax_messages.php';

function dashglpi_ajax_request_id(): string
{
    static $requestId = null;
    if ($requestId === null) {
        try {
            $requestId = bin2hex(random_bytes(8));
        } catch (Throwable) {
            $requestId = substr(str_replace('.', '', uniqid('', true)), 0, 16);
        }
    }

    return $requestId;
}

function dashglpi_ajax_http_status_from_exception(Throwable $e, int $fallback = 500): int
{
    $code = (int) $e->getCode();
    if ($code >= 400 && $code <= 599) {
        return $code;
    }

    return $fallback;
}

function dashglpi_ajax_error_response(
    string $scope,
    Throwable $e,
    string $fallbackMessage = DASHGLPI_AJAX_MSG_GENERIC_SERVER_ERROR,
    int $fallbackStatus = 500
): void {
    $status = dashglpi_ajax_http_status_from_exception($e, $fallbackStatus);
    $message = trim((string) $e->getMessage());
    $traceId = dashglpi_ajax_request_id();

    error_log(sprintf(
        '[DashGLPI][%s] %s error: %s in %s:%d',
        $traceId,
        $scope,
        $message !== '' ? $message : get_class($e),
        $e->getFile(),
        $e->getLine()
    ));

    $payload = [
        'ok' => false,
        'error' => $message !== '' ? $message : $fallbackMessage,
    ];
    if ($status >= 500) {
        $payload['trace_id'] = $traceId;
    }

    dashglpi_json($payload, $status);
}

function dashglpi_ajax_success_response($result): void
{
    if (is_array($result)) {
        dashglpi_json(['ok' => true] + $result);
    }

    dashglpi_json(['ok' => true, 'result' => $result]);
}

function dashglpi_ajax_assert_method(string $method): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        throw new RuntimeException(DASHGLPI_AJAX_MSG_METHOD_NOT_ALLOWED, 405);
    }
}

/**
 * Wrapper para endpoints AJAX cliente (Padrão duplicado A do PLAN-20260703-013).
 *
 * Encapsula: validação de método POST, validação de CSRF, try/catch com log e
 * resposta de erro padrão (status 500). Não substitui `dashglpi_require_auth()` /
 * `dashglpi_assert_page_access()` / `dashglpi_assert_ticket_access()`, que continuam
 * sendo responsabilidade do endpoint chamador antes de invocar este wrapper.
 *
 * $handler roda depois de método/CSRF validados e deve retornar um array associativo
 * de sucesso (mesclado em `{ok:true, ...}`) — sem incluir a chave `ok`. O próprio
 * $handler pode chamar `dashglpi_json(...)` diretamente e não retornar, caso o formato
 * de sucesso precise de controle total (ex.: respostas de GET, como em ticket_followup.php).
 *
 * Fase 1: helper introduzido, nenhum endpoint existente migrado ainda.
 */
function dashglpi_ajax_bridge_endpoint(string $scope, callable $handler, string $fallbackMessage = DASHGLPI_AJAX_MSG_GENERIC_SERVER_ERROR): void
{
    try {
        dashglpi_ajax_assert_method('POST');

        if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
            throw new RuntimeException(DASHGLPI_AJAX_MSG_CSRF_INVALID, 403);
        }

        dashglpi_ajax_success_response($handler());
    } catch (Throwable $e) {
        dashglpi_ajax_error_response($scope, $e, $fallbackMessage);
    }
}

function dashglpi_ajax_get_endpoint(string $scope, callable $handler, string $fallbackMessage = DASHGLPI_AJAX_MSG_GENERIC_SERVER_ERROR): void
{
    try {
        dashglpi_ajax_assert_method('GET');
        $result = $handler();
        dashglpi_json($result);
    } catch (Throwable $e) {
        dashglpi_ajax_error_response($scope, $e, $fallbackMessage);
    }
}

/**
 * Extrai e valida `ticket_id` de POST/GET no padrão repetido pelos endpoints de ticket
 * (`max(0, (int) ...)` + erro se <= 0). Fase 1: disponível, não migrado ainda.
 */
function dashglpi_ajax_require_ticket_id(array $source, string $field = 'ticket_id'): int
{
    $ticketId = max(0, (int) ($source[$field] ?? 0));
    if ($ticketId <= 0) {
        throw new RuntimeException(DASHGLPI_AJAX_MSG_TICKET_INVALID, 400);
    }

    return $ticketId;
}
