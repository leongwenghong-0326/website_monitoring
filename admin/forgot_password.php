<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$message = '';
$type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $resetKey = trim($_POST['reset_key'] ?? '');
    $new = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $storedKey = get_setting('password_reset_key');

    if ($username === '' || $resetKey === '' || $new === '') {
        $message = 'Please fill in all fields.';
        $type = 'error';
    } elseif (!hash_equals($storedKey, $resetKey)) {
        $message = 'Invalid reset key.';
        $type = 'error';
    } elseif (strlen($new) < 6) {
        $message = 'New password must be at least 6 characters.';
        $type = 'error';
    } elseif ($new !== $confirm) {
        $message = 'Password confirmation does not match.';
        $type = 'error';
    } else {
        $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $admin = $stmt->fetch();
        if (!$admin) {
            $message = 'Admin username not found.';
            $type = 'error';
        } else {
            $pdo->prepare('UPDATE admins SET password = ? WHERE id = ?')
                ->execute([password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            flash('success', 'Password reset successful. You can log in now.');
            redirect('admin/login.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Website Monitoring</title>
    <link rel="stylesheet" href="<?php echo e(url('assets/css/admin.css')); ?>">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>Reset password</h1>
    <p class="muted">Use the password reset key shown at install time (also stored in Settings after login).</p>
    <?php if ($message): ?>
        <div class="alert alert-<?php echo e($type); ?>"><?php echo e($message); ?></div>
    <?php endif; ?>
    <form method="post">
        <?php echo csrf_field(); ?>
        <label>Admin username</label>
        <input type="text" name="username" required value="<?php echo e($_POST['username'] ?? ''); ?>">
        <label>Reset key</label>
        <input type="text" name="reset_key" required>
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
        <button class="btn btn-primary btn-block" type="submit">Reset password</button>
    </form>
    <p class="auth-links"><a href="<?php echo e(url('admin/login.php')); ?>">Back to login</a></p>
</div>
<script src="<?php echo e(url('assets/js/admin.js')); ?>"></script>
</body>
</html>
