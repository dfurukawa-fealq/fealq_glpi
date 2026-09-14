<?php
// Somente o servidor temporário da suíte isolada pode usar este router (PLAN-20260905-001).
if (PHP_SAPI !== 'cli-server' || getenv('DASHGLPI_TEST_CONFIG') !== '/test/config') {
    http_response_code(404); exit;
}
define('GLPI_CONFIG_DIR', '/test/config');
require '/var/www/glpi/vendor/autoload.php';
(new \Glpi\Kernel\Kernel())->boot();
if ($DB->dbdefault !== 'attendance_test' || $DB->dbhost !== 'localhost:/test/socket/mysql.sock') {
    throw new RuntimeException('Base de testes incorreta.');
}
$CFG_GLPI['use_notifications'] = 0;
putenv('DASHGLPI_BRIDGE_TOKEN=integration-only');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/health') { echo 'ok'; return; }
$allowed = ['ticket_followup_config.php','ticket_task_config.php','ticket_solution_config.php','ticket_attendance_config.php','ticket_satisfaction_config.php','ticket_cancel_config.php'];
$endpoint = basename($path);
if (!in_array($endpoint, $allowed, true)) { http_response_code(404); exit; }
require '/var/www/glpi/plugins/dashglpi/ajax/' . $endpoint;
