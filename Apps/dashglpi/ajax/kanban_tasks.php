<?php

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/ajax_endpoint.php';
require_once __DIR__ . '/../inc/kanban_tasks.php';

dashglpi_require_auth();
dashglpi_assert_page_access('tickets');

if (!empty(dashglpi_current_user_context()['lock_my_tasks'])) {
    $_GET['my_tasks'] = '1';
    $_POST['my_tasks'] = '1';
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        dashglpi_json(dashglpi_kanban_tasks_list($_GET));
    } catch (Throwable $e) {
        error_log('[DashGLPI] kanban tasks list error: ' . $e->getMessage());
        dashglpi_json(['error' => 'Erro ao carregar tarefas do Kanban.'], 500);
    }
}

dashglpi_ajax_bridge_endpoint('kanban_tasks', static function (): array {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create') {
        return ['task' => dashglpi_kanban_task_create($_POST)];
    }

    if ($action === 'detail') {
        $taskId = max(0, (int) ($_POST['task_id'] ?? 0));
        if ($taskId <= 0) {
            throw new RuntimeException('Tarefa inválida.');
        }

        return ['task' => dashglpi_kanban_task_get($taskId)];
    }

    if ($action === 'update') {
        $taskId = max(0, (int) ($_POST['task_id'] ?? 0));
        if ($taskId <= 0) {
            throw new RuntimeException('Tarefa inválida.');
        }

        return ['task' => dashglpi_kanban_task_update($taskId, $_POST)];
    }

    if ($action === 'update_status') {
        $taskId = max(0, (int) ($_POST['task_id'] ?? 0));
        if ($taskId <= 0) {
            throw new RuntimeException('Tarefa inválida.');
        }

        return ['task' => dashglpi_kanban_task_update_status($taskId, (int) ($_POST['status'] ?? 0))];
    }

    if ($action === 'reorder') {
        $taskId = max(0, (int) ($_POST['task_id'] ?? 0));
        if ($taskId <= 0) {
            throw new RuntimeException('Tarefa inválida.');
        }

        $orderedIds = json_decode((string) ($_POST['ordered_ids'] ?? '[]'), true);
        if (!is_array($orderedIds)) {
            throw new RuntimeException('Ordem inválida.');
        }

        return ['task' => dashglpi_kanban_task_reorder($taskId, (int) ($_POST['status'] ?? 0), $orderedIds)];
    }

    throw new RuntimeException('Ação inválida.');
}, 'Erro ao salvar tarefa do Kanban.');
