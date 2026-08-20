<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_login();

$admin = current_admin();
$errors = [];
$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';

    if ($action === 'test_telegram') {
        $testResult = telegram_send(
            "Website Monitoring System\n\nThis is a test message.\nTime: " . date('d M Y, h:i:s A')
        );
    } elseif ($action === 'password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $stmt = $pdo->prepare('SELECT password FROM admins WHERE id = ?');
        $stmt->execute([$admin['id']]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $errors[] = 'New password confirmation does not match.';
        } else {
            $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            flash('success', 'Password updated.');
            redirect('admin/settings.php');
        }
    } else {
        set_setting('telegram_bot_token', trim($_POST['telegram_bot_token'] ?? ''));
        set_setting('telegram_chat_id', trim($_POST['telegram_chat_id'] ?? ''));
        sync_telegram_config_file(
            trim($_POST['telegram_bot_token'] ?? ''),
            trim($_POST['telegram_chat_id'] ?? '')
        );
        set_setting('status_page_title', trim($_POST['status_page_title'] ?? 'Status page') ?: 'Status page');
        set_setting('status_page_subtitle', trim($_POST['status_page_subtitle'] ?? ''));
        set_setting('default_slow_threshold_ms', (string) max(100, (int) ($_POST['default_slow_threshold_ms'] ?? 3000)));
        set_setting('auto_check_interval_seconds', (string) max(5, (int) ($_POST['auto_check_interval_seconds'] ?? 5)));
        set_setting('timezone', trim($_POST['timezone'] ?? 'Asia/Kuala_Lumpur') ?: 'Asia/Kuala_Lumpur');
        flash('success', 'Settings saved.');
        redirect('admin/settings.php');
    }
}

$pageTitle = 'Settings';
$currentPage = 'settings';
include INCLUDES_PATH . '/header.php';
?>
<div class="grid-2">
    <div class="panel form-panel">
        <h2>Telegram alerts</h2>
        <?php if ($testResult !== null): ?>
            <div class="alert alert-<?php echo $testResult['ok'] ? 'success' : 'error'; ?>">
                <?php echo $testResult['ok'] ? 'Test message sent. Check Telegram.' : e($testResult['error']); ?>
            </div>
        <?php endif; ?>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save">
            <label>Bot token</label>
            <input type="text" name="telegram_bot_token" value="<?php echo e(get_setting('telegram_bot_token')); ?>" placeholder="123456:ABC-DEF...">
            <label>Chat ID</label>
            <input type="text" name="telegram_chat_id" value="<?php echo e(get_setting('telegram_chat_id')); ?>" placeholder="123456789">
            <label>Status page title</label>
            <input type="text" name="status_page_title" value="<?php echo e(get_setting('status_page_title', 'Status page')); ?>">
            <label>Status page subtitle</label>
            <input type="text" name="status_page_subtitle" value="<?php echo e(get_setting('status_page_subtitle')); ?>" placeholder="Service status">
            <label>Default slow threshold (ms)</label>
            <input type="number" name="default_slow_threshold_ms" min="100" max="60000" value="<?php echo e(get_setting('default_slow_threshold_ms', '3000')); ?>">
            <label>Auto-check interval (seconds)</label>
            <input type="number" name="auto_check_interval_seconds" min="5" max="300" value="<?php echo e(get_setting('auto_check_interval_seconds', '5')); ?>">
            <p class="hint">How often the system checks websites automatically while admin is open (minimum 5 seconds). Set each website interval to 1 minute for faster UP/DOWN Telegram alerts.</p>
            <p class="hint">When logged out, checks also run from the <a href="<?php echo e(url('status/')); ?>" target="_blank">public status page</a>. For 24/7 alerts with no browser open, set a server cron job to call the cron URL below every 1 minute.</p>
            <label>Timezone</label>
            <input type="text" name="timezone" value="<?php echo e(get_setting('timezone', 'Asia/Kuala_Lumpur')); ?>">
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save settings</button>
            </div>
        </form>
        <form method="post" class="inline-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="test_telegram">
            <button class="btn" type="submit">Send test Telegram message</button>
        </form>
        <p class="hint">Config file: <code>config/telegram.php</code> (synced when you save settings)</p>
        <p class="hint">Public status page: <a href="<?php echo e(url('status/')); ?>" target="_blank"><?php echo e(url('status/')); ?></a></p>
        <p class="hint">Cron URL (keep secret): <code><?php echo e(url('cron/monitor.php?key=' . get_setting('cron_key'))); ?></code></p>
        <p class="hint">Password reset key: <code><?php echo e(get_setting('password_reset_key')); ?></code></p>
    </div>

    <div class="panel form-panel">
        <h2>Change admin password</h2>
        <?php foreach ($errors as $err): ?>
            <div class="alert alert-error"><?php echo e($err); ?></div>
        <?php endforeach; ?>
        <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="password">
            <label>Current password</label>
            <div class="password-wrap">
                <input type="password" name="current_password" id="current_password" required>
                <button type="button" class="toggle-pass" data-target="current_password">Show</button>
            </div>
            <label>New password</label>
            <div class="password-wrap">
                <input type="password" name="new_password" id="new_password" required minlength="6">
                <button type="button" class="toggle-pass" data-target="new_password">Show</button>
            </div>
            <label>Confirm new password</label>
            <div class="password-wrap">
                <input type="password" name="confirm_password" id="confirm_password" required minlength="6">
                <button type="button" class="toggle-pass" data-target="confirm_password">Show</button>
            </div>
            <button class="btn btn-primary" type="submit">Update password</button>
        </form>
        <div class="help-box">
            <h3>How Telegram alerts work</h3>
            <ol>
                <li>Create a bot with <strong>@BotFather</strong> and copy the token.</li>
                <li>Start a chat with your bot, then get your Chat ID from <strong>@userinfobot</strong>.</li>
                <li>Save both values here and send a test message.</li>
                <li>Alerts are sent only when status changes (DOWN / back UP) or when a site first becomes slow.</li>
            </ol>
        </div>
    </div>
</div>
<?php include INCLUDES_PATH . '/footer.php'; ?>
