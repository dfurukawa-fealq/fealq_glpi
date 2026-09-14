<?php

require_once __DIR__ . '/ajax_messages.php';

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
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            dashglpi_json(['ok' => false, 'error' => DASHGLPI_AJAX_MSG_METHOD_NOT_ALLOWED], 405);
        }

        if (!dashglpi_validate_csrf($_POST['csrf_token'] ?? null)) {
            dashglpi_json(['ok' => false, 'error' => DASHGLPI_AJAX_MSG_CSRF_INVALID], 403);
        }

        $result = $handler();

        dashglpi_json(['ok' => true] + (is_array($result) ? $result : ['result' => $result]));
    } catch (Throwable $e) {
        $message = trim((string) $e->getMessage());
        error_log('[DashGLPI] ' . $scope . ' error: ' . ($message !== '' ? $message : get_class($e)));
        dashglpi_json(['ok' => false, 'error' => $message !== '' ? $message : $fallbackMessage], 500);
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
        throw new RuntimeException(DASHGLPI_AJAX_MSG_TICKET_INVALID);
    }

    return $ticketId;
}
