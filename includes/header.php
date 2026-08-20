<?php
$currentPage = $currentPage ?? '';
$admin = current_admin();
$counts = website_counts();
$autoCheckSummary = maybe_run_auto_check();
$autoCheckInterval = max(5, (int) get_setting('auto_check_interval_seconds', '5'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="auto-check-url" content="<?php echo e(url('admin/auto_check.php')); ?>" data-interval="<?php echo (int) $autoCheckInterval; ?>">
    <title><?php echo e($pageTitle ?? 'Admin'); ?> | Website Monitoring</title>
    <?php $assetVer = (string) (@filemtime(ROOT_PATH . '/assets/css/admin.css') ?: time()); ?>
    <link rel="stylesheet" href="<?php echo e(url('assets/css/admin.css?v=' . $assetVer)); ?>">
</head>
<body>
<div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">WM</div>
            <div>
                <strong>Web Monitor</strong>
                <span>Admin Panel</span>
            </div>
        </div>
        <nav>
            <a class="<?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>" href="<?php echo e(url('admin/dashboard.php')); ?>">Dashboard</a>
            <a class="<?php echo $currentPage === 'websites' ? 'active' : ''; ?>" href="<?php echo e(url('admin/websites.php')); ?>">Websites</a>
            <a class="<?php echo $currentPage === 'logs' ? 'active' : ''; ?>" href="<?php echo e(url('admin/logs.php')); ?>">Monitoring Logs</a>
            <a class="<?php echo $currentPage === 'status_changes' ? 'active' : ''; ?>" href="<?php echo e(url('admin/status_changes.php')); ?>">Status Changes</a>
            <a class="<?php echo $currentPage === 'alerts' ? 'active' : ''; ?>" href="<?php echo e(url('admin/alerts.php')); ?>">Alerts</a>
            <a class="<?php echo $currentPage === 'settings' ? 'active' : ''; ?>" href="<?php echo e(url('admin/settings.php')); ?>">Settings</a>
            <a href="<?php echo e(url('status/')); ?>" target="_blank" rel="noopener">Public Status Page</a>
        </nav>
        <div class="sidebar-foot">
            <div class="mini-stats">
                <span class="dot up"></span> <?php echo (int) $counts['up']; ?> UP
                <span class="dot down"></span> <?php echo (int) $counts['down']; ?> DOWN
            </div>
            <a class="logout" href="<?php echo e(url('admin/logout.php')); ?>">Logout (<?php echo e($admin['username'] ?? 'admin'); ?>)</a>
        </div>
    </aside>
    <main class="content">
        <header class="topbar">
            <div class="topbar-title">
                <button type="button" class="sidebar-toggle" id="sidebar-toggle" aria-label="Open menu" aria-expanded="false">☰</button>
                <h1><?php echo e($pageTitle ?? 'Dashboard'); ?></h1>
            </div>
            <div class="topbar-actions">
                <span class="live-time" id="live-time" data-timezone="<?php echo e(get_setting('timezone', 'Asia/Kuala_Lumpur')); ?>" title="Live local time">--:--:--</span>
                <span class="auto-check-badge" id="auto-check-status" title="Automatic monitoring is active">
                    Auto-check: ON (every <?php echo (int) $autoCheckInterval; ?>s)
                </span>
                <form method="post" action="<?php echo e(url('admin/check_now.php')); ?>">
                    <?php echo csrf_field(); ?>
                    <button class="btn btn-primary" type="submit">Run check now</button>
                </form>
            </div>
        </header>
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>
