<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$title = get_setting('status_page_title', 'Status page');
$subtitle = get_setting('status_page_subtitle', 'Service status');
$refreshSeconds = 60;

$stmt = $pdo->query(
    "SELECT * FROM websites WHERE show_on_status_page = 1 ORDER BY name ASC"
);
$services = $stmt->fetchAll();

$up = 0;
$down = 0;
$unknown = 0;
$latestCheck = null;

foreach ($services as &$service) {
    if ($service['status'] === 'up') {
        $up++;
    } elseif ($service['status'] === 'down') {
        $down++;
    } else {
        $unknown++;
    }
    if ($service['last_checked'] && ($latestCheck === null || $service['last_checked'] > $latestCheck)) {
        $latestCheck = $service['last_checked'];
    }
    $service['uptime_24h'] = uptime_percent((int) $service['id'], 1);
    $service['uptime_7d'] = uptime_percent((int) $service['id'], 7);
    $service['uptime_30d'] = uptime_percent((int) $service['id'], 30);
    $service['uptime_90d'] = uptime_percent((int) $service['id'], 90);
    $service['bars'] = daily_uptime_bars((int) $service['id'], 90);
    if ($service['status'] === 'down' && $service['bars']) {
        $lastIdx = count($service['bars']) - 1;
        $service['bars'][$lastIdx]['state'] = 'down';
        $service['bars'][$lastIdx]['label'] = date('d M Y', strtotime($service['bars'][$lastIdx]['date'])) . ' — Currently down';
    } elseif ($service['status'] === 'up' && $service['bars']) {
        $lastIdx = count($service['bars']) - 1;
        $service['bars'][$lastIdx]['state'] = 'up';
        $service['bars'][$lastIdx]['label'] = date('d M Y', strtotime($service['bars'][$lastIdx]['date'])) . ' — Operational';
    }
}
unset($service);

$totalPublic = count($services);
if ($totalPublic === 0) {
    $overall = 'empty';
    $overallText = 'No public services yet';
} elseif ($down === 0 && $unknown === 0) {
    $overall = 'operational';
    $overallText = 'All systems operational';
} elseif ($up === 0 && $down > 0) {
    $overall = 'major';
    $overallText = 'Major outage';
} elseif ($down > 0) {
    $overall = 'partial';
    $overallText = 'Partial system outage';
} else {
    $overall = 'unknown';
    $overallText = 'Status pending';
}

$overallUptime = ['24h' => [], '7d' => [], '30d' => [], '90d' => []];
foreach ($services as $service) {
    foreach (['24h' => 'uptime_24h', '7d' => 'uptime_7d', '30d' => 'uptime_30d', '90d' => 'uptime_90d'] as $label => $key) {
        if ($service[$key] !== null) {
            $overallUptime[$label][] = $service[$key];
        }
    }
}
$overallPct = [];
foreach ($overallUptime as $label => $vals) {
    $overallPct[$label] = $vals ? round(array_sum($vals) / count($vals), 2) : null;
}

$incidents = $pdo->query(
    "SELECT a.*, w.name
     FROM alerts a
     JOIN websites w ON w.id = a.website_id
     WHERE w.show_on_status_page = 1
       AND a.alert_type IN ('down', 'recovery')
       AND a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
     ORDER BY a.created_at DESC
     LIMIT 12"
)->fetchAll();

function pct_text(?float $value): string
{
    return $value === null ? '—' : number_format($value, 2) . '%';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($title); ?></title>
    <link rel="stylesheet" href="<?php echo e(url('assets/css/status.css')); ?>">
</head>
<body data-refresh="<?php echo (int) $refreshSeconds; ?>" data-has-down="<?php echo $down > 0 ? '1' : '0'; ?>">
<header class="sp-header">
    <div class="sp-wrap">
        <div class="sp-brand">
            <span class="sp-logo">●</span>
            <h1><?php echo e($title); ?></h1>
        </div>
        <div class="sp-header-actions">
            <button type="button" id="btn-fullscreen">Fullscreen mode</button>
            <button type="button" id="btn-sound" data-on="0">Alert sound off</button>
        </div>
    </div>
</header>

<main class="sp-wrap">
    <section class="sp-meta">
        <div>
            <h2><?php echo e($subtitle ?: 'Service status'); ?></h2>
            <p>
                Live time: <strong class="live-time" id="live-time" data-timezone="<?php echo e(get_setting('timezone', 'Asia/Kuala_Lumpur')); ?>">--:--:--</strong>
                <span class="dot-sep">|</span>
                Last updated <?php echo e($latestCheck ? format_datetime($latestCheck) : '—'); ?>
                <span class="dot-sep">|</span>
                Next update in <strong id="countdown"><?php echo (int) $refreshSeconds; ?></strong> sec.
            </p>
        </div>
    </section>

    <section class="overall overall-<?php echo e($overall); ?>">
        <div class="overall-icon">
            <?php if ($overall === 'operational'): ?>✓
            <?php elseif ($overall === 'partial'): ?>!
            <?php elseif ($overall === 'major'): ?>✕
            <?php else: ?>•
            <?php endif; ?>
        </div>
        <div>
            <h3><?php echo e($overallText); ?></h3>
            <p><?php echo (int) $up; ?> operational · <?php echo (int) $down; ?> down · <?php echo (int) $unknown; ?> pending</p>
        </div>
    </section>

    <section class="uptime-summary">
        <article><span>24 hours</span><strong><?php echo e(pct_text($overallPct['24h'])); ?></strong></article>
        <article><span>7 days</span><strong><?php echo e(pct_text($overallPct['7d'])); ?></strong></article>
        <article><span>30 days</span><strong><?php echo e(pct_text($overallPct['30d'])); ?></strong></article>
        <article><span>90 days</span><strong><?php echo e(pct_text($overallPct['90d'])); ?></strong></article>
    </section>

    <section class="services">
        <div class="services-head">
            <h2>Services</h2>
            <div class="legend">
                <span><i class="bar bar-up"></i> Operational</span>
                <span><i class="bar bar-partial"></i> Partial outage</span>
                <span><i class="bar bar-down"></i> Downtime</span>
                <span><i class="bar bar-nodata"></i> No data</span>
            </div>
        </div>

        <?php if (!$services): ?>
            <div class="empty-box">No services are published on this status page yet.</div>
        <?php endif; ?>

        <?php foreach ($services as $service): ?>
            <article class="service">
                <div class="service-row">
                    <div>
                        <h3><?php echo e($service['name']); ?></h3>
                        <p class="service-url"><?php echo e($service['url']); ?></p>
                    </div>
                    <div class="service-right">
                        <?php if ($service['status'] === 'up'): ?>
                            <span class="pill pill-up">Operational</span>
                        <?php elseif ($service['status'] === 'down'): ?>
                            <span class="pill pill-down">Down</span>
                        <?php else: ?>
                            <span class="pill pill-unknown">Not checked</span>
                        <?php endif; ?>
                        <strong class="pct"><?php echo e(pct_text($service['uptime_90d'])); ?></strong>
                    </div>
                </div>
                <div class="bars" aria-label="90-day uptime history">
                    <?php foreach ($service['bars'] as $bar): ?>
                        <span class="bar bar-<?php echo e($bar['state']); ?>" title="<?php echo e($bar['label']); ?>"></span>
                    <?php endforeach; ?>
                </div>
                <div class="service-foot">
                    <span>90-day history</span>
                    <span>
                        Response
                        <?php echo e(format_ms($service['response_time'] !== null ? (int) $service['response_time'] : null)); ?>
                        · Interval <?php echo (int) $service['interval_minutes']; ?> min
                    </span>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="incidents">
        <h2>Recent incidents (7 days)</h2>
        <?php if (!$incidents): ?>
            <p class="ok-note">No incidents reported in the last 7 days.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($incidents as $incident): ?>
                    <li>
                        <span class="pill <?php echo $incident['alert_type'] === 'recovery' ? 'pill-up' : 'pill-down'; ?>">
                            <?php echo $incident['alert_type'] === 'recovery' ? 'Resolved' : 'Outage'; ?>
                        </span>
                        <div>
                            <strong><?php echo e($incident['name']); ?></strong>
                            <p><?php echo e(format_datetime($incident['created_at'])); ?></p>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

<footer class="sp-footer">
    <div class="sp-wrap">
        <span>Status page</span>
        <span>Powered by Website Monitoring System</span>
    </div>
</footer>
<script src="<?php echo e(url('assets/js/live-time.js')); ?>"></script>
<script src="<?php echo e(url('assets/js/status.js')); ?>"></script>
</body>
</html>
