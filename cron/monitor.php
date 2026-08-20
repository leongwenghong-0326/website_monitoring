<?php
/**
 * Monitoring engine.
 * CLI:  php cron/monitor.php
 * Web:  /cron/monitor.php?key=YOUR_CRON_KEY
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$isCli = php_sapi_name() === 'cli';

if (!$isCli) {
    $key = $_GET['key'] ?? '';
    $expected = get_setting('cron_key');
    if ($expected === '' || !hash_equals($expected, $key)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$summary = run_monitoring_cycle(false);
$line = sprintf(
    "[%s] Checked %d/%d websites, status-change alerts: %d\n",
    date('Y-m-d H:i:s'),
    $summary['checked'],
    $summary['total'],
    $summary['alerts']
);

if ($isCli) {
    echo $line;
} else {
    header('Content-Type: text/plain; charset=utf-8');
    echo $line;
}
