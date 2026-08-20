<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$site = [
    'name' => '',
    'url' => '',
    'interval_minutes' => 5,
    'slow_threshold_ms' => (int) get_setting('default_slow_threshold_ms', '3000'),
    'show_on_status_page' => 1,
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM websites WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('error', 'Website not found.');
        redirect('admin/websites.php');
    }
    $site = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $site['name'] = trim($_POST['name'] ?? '');
    $site['url'] = trim($_POST['url'] ?? '');
    $site['interval_minutes'] = (int) ($_POST['interval_minutes'] ?? 5);
    $site['slow_threshold_ms'] = (int) ($_POST['slow_threshold_ms'] ?? 3000);
    $site['show_on_status_page'] = isset($_POST['show_on_status_page']) ? 1 : 0;

    if ($site['name'] === '') {
        $errors[] = 'Website name is required.';
    }
    if ($site['url'] === '' || !filter_var($site['url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Please enter a valid URL including http:// or https://';
    }
    if ($site['interval_minutes'] < 1 || $site['interval_minutes'] > 1440) {
        $errors[] = 'Interval must be between 1 and 1440 minutes.';
    }
    if ($site['slow_threshold_ms'] < 100 || $site['slow_threshold_ms'] > 60000) {
        $errors[] = 'Slow threshold must be between 100 and 60000 ms.';
    }

    if (!$errors) {
        if ($id) {
            $stmt = $pdo->prepare(
                'UPDATE websites SET name = ?, url = ?, interval_minutes = ?, slow_threshold_ms = ?, show_on_status_page = ? WHERE id = ?'
            );
            $stmt->execute([
                $site['name'], $site['url'], $site['interval_minutes'],
                $site['slow_threshold_ms'], $site['show_on_status_page'], $id
            ]);
            flash('success', 'Website updated.');
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO websites (name, url, interval_minutes, slow_threshold_ms, show_on_status_page)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $site['name'], $site['url'], $site['interval_minutes'],
                $site['slow_threshold_ms'], $site['show_on_status_page']
            ]);
            flash('success', 'Website added. Use “Run check now” or wait for the cron job.');
        }
        redirect('admin/websites.php');
    }
}

$pageTitle = $id ? 'Edit Website' : 'Add Website';
$currentPage = 'websites';
include INCLUDES_PATH . '/header.php';
?>
<div class="panel form-panel">
    <h2><?php echo e($pageTitle); ?></h2>
    <?php foreach ($errors as $err): ?>
        <div class="alert alert-error"><?php echo e($err); ?></div>
    <?php endforeach; ?>
    <form method="post">
        <?php echo csrf_field(); ?>
        <label>Website name</label>
        <input type="text" name="name" required value="<?php echo e($site['name']); ?>" placeholder="Company homepage">

        <label>Website URL</label>
        <input type="url" name="url" required value="<?php echo e($site['url']); ?>" placeholder="https://example.com">

        <label>Monitoring interval (minutes)</label>
        <input type="number" name="interval_minutes" min="1" max="1440" required value="<?php echo (int) $site['interval_minutes']; ?>">

        <label>Slow response threshold (milliseconds)</label>
        <input type="number" name="slow_threshold_ms" min="100" max="60000" required value="<?php echo (int) $site['slow_threshold_ms']; ?>">
        <p class="hint">If the site is UP but slower than this, a Telegram WARNING is sent once until it recovers.</p>

        <label class="check">
            <input type="checkbox" name="show_on_status_page" value="1" <?php echo (int) $site['show_on_status_page'] ? 'checked' : ''; ?>>
            Show this website on the public status page
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><?php echo $id ? 'Save changes' : 'Add website'; ?></button>
            <a class="btn btn-ghost" href="<?php echo e(url('admin/websites.php')); ?>">Cancel</a>
        </div>
    </form>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
