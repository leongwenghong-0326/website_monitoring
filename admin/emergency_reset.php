<?php
/**
 * ONE-TIME emergency admin reset for server deployment.
 * DELETE THIS FILE immediately after use!
 *
 * Open in browser:
 *   /admin/emergency_reset.php?key=wm2026reset
 */
define('EMERGENCY_RESET_KEY', 'wm2026reset');

$key = $_GET['key'] ?? '';
if (!hash_equals(EMERGENCY_RESET_KEY, $key)) {
    http_response_code(404);
    exit('Not found');
}

require_once dirname(__DIR__) . '/config/database.php';

$message = '';
$type = '';

try {
    $pdo->query('SELECT 1 FROM admins LIMIT 1');
} catch (Throwable $e) {
    exit('<h2>admins table not found</h2><p>Run <a href="../install.php?reinstall=1">install.php</a> first.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($username === '') {
        $message = 'Username is required.';
        $type = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Password must be at least 6 characters.';
        $type = 'error';
    } elseif ($password !== $confirm) {
        $message = 'Passwords do not match.';
        $type = 'error';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('SELECT id FROM admins ORDER BY id ASC LIMIT 1');
        $stmt->execute();
        $admin = $stmt->fetch();

        if ($admin) {
            $pdo->prepare('UPDATE admins SET username = ?, password = ? WHERE id = ?')
                ->execute([$username, $hash, $admin['id']]);
        } else {
            $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)')
                ->execute([$username, $hash]);
        }

        $message = 'Admin account updated. You can log in now. Delete emergency_reset.php from the server immediately!';
        $type = 'success';
    }
}

$current = $pdo->query('SELECT username FROM admins ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Admin Reset</title>
    <link rel="stylesheet" href="<?php echo htmlspecialchars('../assets/css/admin.css'); ?>">
</head>
<body class="auth-body">
<div class="auth-card">
    <h1>Emergency admin reset</h1>
    <p class="muted">Current admin username in database: <strong><?php echo htmlspecialchars((string) $current); ?></strong></p>
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $type === 'success' ? 'success' : 'error'; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <?php if ($type !== 'success'): ?>
    <form method="post">
        <label>Username</label>
        <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? 'admin'); ?>">
        <label>New password</label>
        <input type="password" name="password" required minlength="6">
        <label>Confirm password</label>
        <input type="password" name="confirm_password" required minlength="6">
        <button class="btn btn-primary btn-block" type="submit">Reset admin password</button>
    </form>
    <?php else: ?>
        <p class="auth-links"><a href="login.php">Go to login</a></p>
    <?php endif; ?>
</div>
</body>
</html>
