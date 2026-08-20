<?php
/**
 * Public background check endpoint (JSON, throttled).
 * Keeps monitoring + Telegram alerts running when admin is logged out.
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$summary = maybe_run_auto_check();
if ($summary === null) {
    echo json_encode([
        'ok'       => true,
        'skipped'  => true,
        'message'  => 'Waiting for next auto-check interval.',
        'interval' => (int) get_setting('auto_check_interval_seconds', '5'),
        'time'     => date('c'),
    ]);
    exit;
}

echo json_encode([
    'ok'      => true,
    'skipped' => false,
    'checked' => $summary['checked'],
    'total'   => $summary['total'],
    'alerts'  => $summary['alerts'],
    'time'    => date('c'),
]);
