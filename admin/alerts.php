<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$page = (int) ($_GET['page'] ?? 1);
$total = (int) $pdo->query('SELECT COUNT(*) FROM alerts')->fetchColumn();
$pager = paginate($total, $page, 20);

$stmt = $pdo->query(
    'SELECT a.*, w.name, w.url
     FROM alerts a
     JOIN websites w ON w.id = a.website_id
     ORDER BY a.created_at DESC
     LIMIT ' . (int) $pager['perPage'] . ' OFFSET ' . (int) $pager['offset']
);
$alerts = $stmt->fetchAll();

$pageTitle = 'Alerts';
$currentPage = 'alerts';
include INCLUDES_PATH . '/header.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Telegram / status change alerts</h2>
        <span class="muted">Alerts are stored even if Telegram is not configured.</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Type</th>
                <th>Website</th>
                <th>Time</th>
                <th>Message</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$alerts): ?>
                <tr><td colspan="4" class="empty">No alerts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($alerts as $alert): ?>
                <tr>
                    <td>
                        <span class="badge badge-<?php echo $alert['alert_type'] === 'recovery' ? 'up' : ($alert['alert_type'] === 'slow' ? 'slow' : 'down'); ?>">
                            <?php echo strtoupper(e($alert['alert_type'])); ?>
                        </span>
                    </td>
                    <td>
                        <strong><?php echo e($alert['name']); ?></strong>
                        <div class="sub"><?php echo e($alert['url']); ?></div>
                    </td>
                    <td><?php echo e(format_datetime($alert['created_at'])); ?></td>
                    <td><pre class="msg"><?php echo e($alert['message']); ?></pre></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                <a class="<?php echo $i === $pager['page'] ? 'active' : ''; ?>" href="<?php echo e(url('admin/alerts.php?page=' . $i)); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
