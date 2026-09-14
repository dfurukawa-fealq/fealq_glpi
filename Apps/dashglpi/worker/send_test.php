<?php

/**
 * Teste isolado dos canais de mensageria (Step 6 do .Stack.md).
 *
 * Envia um alerta fixo para um canal específico ou para todos os habilitados,
 * usando a configuração efetiva (settings do plugin + fallback de env).
 *
 * Uso:
 *   php worker/send_test.php                 # todos os canais configurados
 *   php worker/send_test.php teams           # apenas Teams
 *   php worker/send_test.php whatsapp
 *   php worker/send_test.php telegram
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../inc/bootstrap.php';
require_once __DIR__ . '/../inc/sla_monitor.php';

$only = isset($argv[1]) ? strtolower(trim((string) $argv[1])) : '';

$alert = [
    'ticket_id' => 0,
    'title' => 'Chamado de teste — DashGLPI',
    'category' => 'Teste / Integração',
    'entity_name' => 'Fealq',
    'entities_id' => 0,
    'priority' => 5,
    'minutes_overdue' => 20,
    'threshold' => 15,
    'level' => 'breach',
    'url' => dashglpi_sla_monitor_ticket_url(0),
];

$dispatcher = new DashglpiNotificationDispatcher();
$config = dashglpi_alerting_config();

$results = $only !== ''
    ? [$only => $dispatcher->sendTo($only, $alert, $config)]
    : $dispatcher->dispatch($alert, $config);

$exit = 0;
foreach ($results as $key => $res) {
    $status = !empty($res['ok']) ? 'OK' : (!empty($res['skipped']) ? 'SKIP' : 'FAIL');
    if ($status === 'FAIL') {
        $exit = 1;
    }
    fwrite(STDOUT, sprintf(
        "[%-4s] %-20s status=%d %s%s",
        $status,
        (string) ($res['label'] ?? $key),
        (int) ($res['status'] ?? 0),
        !empty($res['error']) ? '- ' . $res['error'] : '',
        PHP_EOL
    ));
}

exit($exit);
