<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

/** @var PDO $pdo */
$pdo = $GLOBALS['pdo'];

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';
$counts = website_counts();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$period = $_GET['period'] ?? 'all';

$sql = 'SELECT * FROM websites WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (name LIKE ? OR url LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($status === 'up' || $status === 'down') {
    $sql .= ' AND status = ?';
    $params[] = $status;
}
if ($period === 'today') {
    $sql .= ' AND DATE(last_checked) = CURDATE()';
} elseif ($period === '7days') {
    $sql .= ' AND last_checked >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}
$sql .= ' ORDER BY last_checked DESC, name ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$websites = $stmt->fetchAll();

$recentAlerts = $pdo->query(
    'SELECT a.*, w.name, w.url
     FROM alerts a
     JOIN websites w ON w.id = a.website_id
     ORDER BY a.created_at DESC
     LIMIT 8'
)->fetchAll();

$recentLogs = $pdo->query(
    'SELECT l.*, w.name, w.url
     FROM logs l
     JOIN websites w ON w.id = l.website_id
     ORDER BY l.checked_at DESC
     LIMIT 10'
)->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<section class="cards">
    <article class="stat-card">
        <span>Total websites</span>
        <strong><?php echo (int) $counts['total']; ?></strong>
    </article>
    <article class="stat-card up">
        <span>UP</span>
        <strong><?php echo (int) $counts['up']; ?></strong>
    </article>
    <article class="stat-card down">
        <span>DOWN</span>
        <strong><?php echo (int) $counts['down']; ?></strong>
    </article>
    <article class="stat-card muted">
        <span>Not checked yet</span>
        <strong><?php echo (int) $counts['unknown']; ?></strong>
    </article>
</section>

<section class="grid-2">
    <div class="panel">
        <div class="panel-head">
            <h2>Website status</h2>
            <a href="<?php echo e(url('admin/websites.php')); ?>">Manage</a>
        </div>
        <form class="filters" method="get">
            <input type="text" name="q" placeholder="Search name or URL" value="<?php echo e($q); ?>">
            <select name="status">
                <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All websites</option>
                <option value="up" <?php echo $status === 'up' ? 'selected' : ''; ?>>UP only</option>
                <option value="down" <?php echo $status === 'down' ? 'selected' : ''; ?>>DOWN only</option>
            </select>
            <select name="period">
                <option value="all" <?php echo $period === 'all' ? 'selected' : ''; ?>>Any check time</option>
                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Checked today</option>
                <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 days</option>
            </select>
            <button class="btn" type="submit">Filter</button>
            <a class="btn btn-ghost" href="<?php echo e(url('admin/dashboard.php')); ?>">Reset</a>
        </form>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Website</th>
                    <th>Status</th>
                    <th>Response</th>
                    <th>Last checked</th>
                    <th>Interval</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$websites): ?>
                    <tr><td colspan="5" class="empty">No websites match your filter. <a href="<?php echo e(url('admin/website_form.php')); ?>">Add one</a>.</td></tr>
                <?php endif; ?>
                <?php foreach ($websites as $site): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($site['name']); ?></strong>
                            <div class="sub"><a href="<?php echo e($site['url']); ?>" target="_blank" rel="noopener"><?php echo e($site['url']); ?></a></div>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo e($site['status']); ?>">
                                <?php echo $site['status'] === 'up' ? '🟢 UP' : ($site['status'] === 'down' ? '🔴 DOWN' : '⚪ UNKNOWN'); ?>
                            </span>
                            <?php if ((int) $site['is_slow'] === 1 && $site['status'] === 'up'): ?>
                                <span class="badge badge-slow">🟡 SLOW</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(format_ms($site['response_time'] !== null ? (int) $site['response_time'] : null)); ?></td>
                        <td><?php echo e(format_datetime($site['last_checked'])); ?></td>
                        <td><?php echo (int) $site['interval_minutes']; ?> min</td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Recent alerts</h2>
            <a href="<?php echo e(url('admin/alerts.php')); ?>">View all</a>
        </div>
        <?php if (!$recentAlerts): ?>
            <p class="empty">No alerts yet. Alerts appear when a website goes DOWN, recovers, or is too slow.</p>
        <?php else: ?>
            <ul class="feed">
                <?php foreach ($recentAlerts as $alert): ?>
                    <li>
                        <span class="badge badge-<?php echo $alert['alert_type'] === 'recovery' ? 'up' : ($alert['alert_type'] === 'slow' ? 'slow' : 'down'); ?>">
                            <?php echo strtoupper(e($alert['alert_type'])); ?>
                        </span>
                        <div>
                            <strong><?php echo e($alert['name']); ?></strong>
                            <div class="sub"><?php echo e(format_datetime($alert['created_at'])); ?></div>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</section>

<section class="panel">
    <div class="panel-head">
        <h2>Latest monitoring activity</h2>
        <a href="<?php echo e(url('admin/logs.php')); ?>">Full logs</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Website</th>
                <th>Status</th>
                <th>Response time</th>
                <th>HTTP</th>
                <th>Checked at</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$recentLogs): ?>
                <tr><td colspan="5" class="empty">No checks yet. Use “Run check now” or set up the cron job.</td></tr>
            <?php endif; ?>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?php echo e($log['name']); ?></td>
                    <td><span class="badge badge-<?php echo e($log['status']); ?>"><?php echo $log['status'] === 'up' ? '🟢 UP' : '🔴 DOWN'; ?></span></td>
                    <td><?php echo e(format_ms($log['response_time'] !== null ? (int) $log['response_time'] : null)); ?></td>
                    <td><?php echo $log['http_code'] ? (int) $log['http_code'] : '—'; ?></td>
                    <td><?php echo e(format_datetime($log['checked_at'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
