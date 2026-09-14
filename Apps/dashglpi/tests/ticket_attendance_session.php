<?php

// Gates reais de sessão/CSRF, sem conexão ao banco (PLAN-20260905-001).
if (PHP_SAPI !== 'cli' || getenv('DASHGLPI_ATTENDANCE_TEST') !== '1') {
    exit("Execute no container descartável descrito no README.\n");
}
$endpoints = ['ticket_detail.php', 'ticket_attendance.php', 'ticket_followup.php', 'ticket_task.php',
    'ticket_update.php', 'ticket_solution.php', 'ticket_cancel.php', 'ticket_satisfaction.php'];
if (isset($argv[1])) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/ajax/' . $argv[1];
    $_SERVER['HTTP_ACCEPT'] = 'application/json';
    register_shutdown_function(static function (): void { fwrite(STDERR, (string) http_response_code()); });
    if (in_array($argv[1], $endpoints, true)) {
        require __DIR__ . '/../ajax/' . $argv[1];
    } elseif (in_array($argv[1], ['csrf-invalid', 'csrf-missing'], true)) {
        require __DIR__ . '/../inc/bootstrap.php';
        require __DIR__ . '/../inc/ajax_endpoint.php';
        dashglpi_start_session();
        $_SESSION['dashglpi_csrf'] = 'session-fixture';
        if ($argv[1] === 'csrf-invalid') $_POST['csrf_token'] = 'invalid';
        dashglpi_ajax_bridge_endpoint('attendance test', static function (): array {
            throw new RuntimeException('O handler não deveria executar.');
        });
    }
    exit;
}
$passed = 0;
foreach (array_merge($endpoints, ['csrf-invalid', 'csrf-missing']) as $case) {
    $process = proc_open([PHP_BINARY, __FILE__, $case], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    fclose($pipes[0]);
    $body = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $status = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($process);
    $expected = str_starts_with($case, 'csrf-') ? '403' : '401';
    if ($code !== 0 || trim($status) !== $expected || !isset(json_decode($body, true)['error'])) {
        throw new RuntimeException("Falha em $case: status=$status body=$body");
    }
    $passed++;
    echo "PASS: $case rejeitado com HTTP $expected\n";
}
echo "Session gates: $passed checks passed.\n";
