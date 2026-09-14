<?php

/**
 * Orquestrador de eventos (PLAN-20260702-007) — entrypoint CLI de UMA passada.
 *
 * Generaliza o antigo worker/sla_monitor.php para rodar os três workers do
 * ecossistema no mesmo processo, cada um isolado em seu próprio try/catch (uma
 * falha em um worker não interrompe os demais). O agendamento (loop a cada N
 * segundos) continua a cargo do container `kawa_dashglpi_worker`, mantendo o
 * motor desacoplado do cron nativo do GLPI.
 *
 * Uso:  php worker/orchestrator.php [--dry-run]
 * Saída: uma linha JSON por worker (para logs do container).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/sla_monitor.php';
require_once __DIR__ . '/../inc/task_worker.php';
require_once __DIR__ . '/../inc/solution_worker.php';
require_once __DIR__ . '/../inc/rules_engine.php';
require_once __DIR__ . '/../inc/push.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

/**
 * @param callable(bool):array $runner
 */
function dashglpi_orchestrator_run_worker(string $name, callable $runner, bool $dryRun): array
{
    $startedAt = microtime(true);
    $line = ['worker' => $name, 'ts' => date('c'), 'dry_run' => $dryRun];

    try {
        $summary = $runner($dryRun);
        $line += [
            'scanned' => $summary['scanned'] ?? 0,
            'dispatched' => $summary['dispatched'] ?? 0,
            'errors' => $summary['errors'] ?? [],
        ];
    } catch (Throwable $e) {
        error_log('[DashGLPI][orchestrator][' . $name . '] ' . $e->getMessage());
        $line['fatal'] = $e->getMessage();
    }

    $line['took_ms'] = (int) round((microtime(true) - $startedAt) * 1000);
    return $line;
}

$workers = [
    'sla' => 'dashglpi_sla_monitor_run',
    'task' => 'dashglpi_task_worker_run',
    'solution' => 'dashglpi_solution_worker_run',
    // Engine dinâmica de regras (PLAN-20260703-008) — aditiva, não substitui os 3
    // workers fixos acima. Regras criadas via UI (front/settings.php?section=rules).
    'rules' => 'dashglpi_rule_engine_run',
    'push' => 'dashglpi_push_worker_run',
];

$exitCode = 0;
foreach ($workers as $name => $runner) {
    $line = dashglpi_orchestrator_run_worker($name, $runner, $dryRun);
    if (!empty($line['fatal']) || !empty($line['errors'])) {
        $exitCode = 1;
    }
    fwrite(STDOUT, json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}

exit($exitCode);
