<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? 'all';
$websiteId = (int) ($_GET['website_id'] ?? 0);
$period = $_GET['period'] ?? 'all';
$changesOnly = (isset($_GET['changes']) && $_GET['changes'] === '1')
    || (defined('STATUS_CHANGES_PAGE') && STATUS_CHANGES_PAGE);
$page = (int) ($_GET['page'] ?? 1);

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(w.name LIKE ? OR w.url LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($status === 'up' || $status === 'down') {
    $where[] = 'l.status = ?';
    $params[] = $status;
}
if ($websiteId > 0) {
    $where[] = 'l.website_id = ?';
    $params[] = $websiteId;
}
if ($period === 'today') {
    $where[] = 'DATE(l.checked_at) = CURDATE()';
} elseif ($period === '7days') {
    $where[] = 'l.checked_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$whereSql = implode(' AND ', $where);

$prevStatusSql = '(SELECT prev.status FROM logs prev
        WHERE prev.website_id = l.website_id
          AND (prev.checked_at < l.checked_at OR (prev.checked_at = l.checked_at AND prev.id < l.id))
        ORDER BY prev.checked_at DESC, prev.id DESC
        LIMIT 1)';

if ($changesOnly) {
    $whereSql .= " AND {$prevStatusSql} IS NOT NULL AND {$prevStatusSql} <> l.status";
}

$countSql = "SELECT COUNT(*) FROM logs l JOIN websites w ON w.id = l.website_id WHERE {$whereSql}";
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pager = paginate($total, $page, 25);

$sql = "SELECT l.*, w.name, w.url, {$prevStatusSql} AS prev_status
        FROM logs l
        JOIN websites w ON w.id = l.website_id
        WHERE {$whereSql}
        ORDER BY l.checked_at DESC
        LIMIT {$pager['perPage']} OFFSET {$pager['offset']}";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$sites = $pdo->query('SELECT id, name FROM websites ORDER BY name')->fetchAll();

$pageTitle = (defined('STATUS_CHANGES_PAGE') && STATUS_CHANGES_PAGE)
    ? 'Status Change History'
    : 'Monitoring Logs';
$currentPage = (defined('STATUS_CHANGES_PAGE') && STATUS_CHANGES_PAGE)
    ? 'status_changes'
    : 'logs';
include INCLUDES_PATH . '/header.php';
?>
<div class="panel">
    <div class="panel-head">
        <h2><?php echo $changesOnly ? 'Status change history' : 'Full history'; ?></h2>
        <span class="muted"><?php echo (int) $total; ?> records</span>
    </div>
    <form class="filters" method="get">
        <input type="text" name="q" placeholder="Search name or URL" value="<?php echo e($q); ?>">
        <select name="website_id">
            <option value="0">All websites</option>
            <?php foreach ($sites as $s): ?>
                <option value="<?php echo (int) $s['id']; ?>" <?php echo $websiteId === (int) $s['id'] ? 'selected' : ''; ?>>
                    <?php echo e($s['name']); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="all">All status</option>
            <option value="up" <?php echo $status === 'up' ? 'selected' : ''; ?>>UP</option>
            <option value="down" <?php echo $status === 'down' ? 'selected' : ''; ?>>DOWN</option>
        </select>
        <select name="period">
            <option value="all">Any time</option>
            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
            <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 days</option>
        </select>
        <label class="check compact">
            <input type="checkbox" name="changes" value="1" <?php echo $changesOnly ? 'checked' : ''; ?>>
            Status changes only
        </label>
        <button class="btn" type="submit">Filter</button>
        <?php if ($changesOnly): ?>
            <a class="btn btn-ghost" href="<?php echo e(url('admin/logs.php')); ?>">All logs</a>
        <?php else: ?>
            <a class="btn btn-ghost" href="<?php echo e(url('admin/status_changes.php')); ?>">Changes only</a>
        <?php endif; ?>
        <a class="btn btn-ghost" href="<?php echo e(url($changesOnly ? 'admin/status_changes.php' : 'admin/logs.php')); ?>">Reset</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Website</th>
                <th>Status</th>
                <th>Response</th>
                <th>HTTP</th>
                <th>Checked at</th>
                <th>Change</th>
            </tr>
            </thead>
            <tbody>
            <?php if (!$logs): ?>
                <tr><td colspan="6" class="empty">No logs found.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $log): ?>
                <?php $changed = $log['prev_status'] !== null && $log['prev_status'] !== $log['status']; ?>
                <tr>
                    <td>
                        <strong><?php echo e($log['name']); ?></strong>
                        <div class="sub"><?php echo e($log['url']); ?></div>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo e($log['status']); ?>">
                            <?php echo $log['status'] === 'up' ? '🟢 UP' : '🔴 DOWN'; ?>
                        </span>
                        <?php if ((int) $log['is_slow']): ?>
                            <span class="badge badge-slow">🟡 SLOW</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo e(format_ms($log['response_time'] !== null ? (int) $log['response_time'] : null)); ?></td>
                    <td><?php echo $log['http_code'] ? (int) $log['http_code'] : '—'; ?></td>
                    <td><?php echo e(format_datetime($log['checked_at'])); ?></td>
                    <td><?php echo $changed ? 'Status changed' : 'No change'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager['pages'] > 1): ?>
        <div class="pagination">
            <?php for ($i = 1; $i <= $pager['pages']; $i++): ?>
                <?php
                $query = $_GET;
                $query['page'] = $i;
                $href = 'admin/logs.php?' . http_build_query($query);
                ?>
                <a class="<?php echo $i === $pager['page'] ? 'active' : ''; ?>" href="<?php echo e(url($href)); ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
