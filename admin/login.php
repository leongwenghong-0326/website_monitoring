<?php
require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect('admin/dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } elseif (!login_admin($username, $password)) {
        $error = 'Invalid username or password.';
    } else {
        flash('success', 'Welcome back, ' . $username . '.');
        redirect('admin/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Website Monitoring</title>
    <link rel="stylesheet" href="<?php echo e(url('assets/css/admin.css')); ?>">
</head>
<body class="auth-body">
<div class="auth-card">
    <div class="brand-mark lg">WM</div>
    <h1>Admin Login</h1>
    <p class="muted">Website Monitoring System</p>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo e($error); ?></div>
        <p class="hint login-hint">Uncheck <strong>Remember me</strong>, or use a private/incognito window — saved credentials may be outdated. Reset password via <a href="<?php echo e(url('admin/emergency_reset.php?key=wm2026reset')); ?>">emergency reset</a>.</p>
    <?php endif; ?>
    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?php echo e($flash['type']); ?>"><?php echo e($flash['message']); ?></div>
    <?php endif; ?>
    <form method="post" id="login-form" autocomplete="on">
        <?php echo csrf_field(); ?>
        <label for="username">Username</label>
        <div class="password-wrap">
            <input type="text" name="username" id="username" required autocomplete="username"
                   value="<?php echo e($_POST['username'] ?? ''); ?>">
            <span class="field-spacer" aria-hidden="true"></span>
        </div>
        <label for="password">Password</label>
        <div class="password-wrap">
            <input type="password" name="password" id="password" required autocomplete="current-password">
            <button type="button" class="toggle-pass" data-target="password">Show</button>
        </div>
        <label class="check remember-me">
            <input type="checkbox" name="remember" id="remember" value="1" checked>
            Remember username &amp; password on this device
        </label>
        <button class="btn btn-primary btn-block" type="submit">Login</button>
    </form>
    <p class="auth-links">
        <a href="<?php echo e(url('admin/forgot_password.php')); ?>">Forgot password?</a>
        &nbsp;·&nbsp;
        <a href="<?php echo e(url('status/')); ?>">View status page</a>
    </p>
</div>
<script src="<?php echo e(url('assets/js/admin.js')); ?>"></script>
</body>
</html>
