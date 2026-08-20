<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $id = (int) $_POST['delete_id'];
    $stmt = $pdo->prepare('DELETE FROM websites WHERE id = ?');
    $stmt->execute([$id]);
    flash('success', 'Website deleted.');
    redirect('admin/websites.php');
}

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

$sql .= ' ORDER BY name ASC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$websites = $stmt->fetchAll();

$pageTitle = 'Manage Websites';
$currentPage = 'websites';
include INCLUDES_PATH . '/header.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2>Websites</h2>
        <a class="btn btn-primary" href="<?php echo e(url('admin/website_form.php')); ?>">Add website</a>
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
        <a class="btn btn-ghost" href="<?php echo e(url('admin/websites.php')); ?>">Reset</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Name</th>
                <th>URL</th>
                <th>Status</th>
                <th>Response</th>
                <th>Last checked</th>
                <th>Interval</th>
                <th>Status page</th>
                <th></th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$websites): ?>
                <tr><td colspan="8" class="empty">No websites match your filter.</td></tr>
            <?php endif; ?>
            <?php foreach ($websites as $site): ?>
                <tr>
                    <td><strong><?php echo e($site['name']); ?></strong></td>
                    <td><a href="<?php echo e($site['url']); ?>" target="_blank" rel="noopener"><?php echo e($site['url']); ?></a></td>
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
                    <td><?php echo (int) $site['show_on_status_page'] ? 'Yes' : 'No'; ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?php echo e(url('admin/website_form.php?id=' . (int) $site['id'])); ?>">Edit</a>
                        <form method="post" class="inline" data-confirm="Delete this website and its logs?">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="delete_id" value="<?php echo (int) $site['id']; ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
