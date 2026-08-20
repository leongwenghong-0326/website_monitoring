<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dashboard.php');
}
verify_csrf();

$summary = run_monitoring_cycle(true);
flash(
    'success',
    'Manual check finished. Websites checked: ' . $summary['checked'] . ' / ' . $summary['total'] . '.'
);
redirect('admin/dashboard.php');
