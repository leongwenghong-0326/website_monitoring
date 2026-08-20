<?php
/**
 * First-run installer for XAMPP / local PHP + MySQL.
 */
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

$root = __DIR__;
$lockFile = $root . DIRECTORY_SEPARATOR . 'install.lock';
$dbFile = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';

if (file_exists($lockFile) && !isset($_GET['reinstall'])) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Already installed</title></head><body style="font-family:sans-serif;padding:40px;">';
    echo '<h2>Already installed</h2><p>Delete <code>install.lock</code> only if you want to run the installer again.</p>';
    echo '<p><a href="admin/login.php">Go to admin login</a></p></body></html>';
    exit;
}

function h($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$errors = [];
$success = false;
$resetKey = '';
$cronKey = '';

$host = $_POST['db_host'] ?? 'localhost';
$name = $_POST['db_name'] ?? 'website_monitoring';
$user = $_POST['db_user'] ?? 'root';
$pass = $_POST['db_pass'] ?? '';
$adminUser = trim($_POST['admin_user'] ?? 'admin');
$adminPass = $_POST['admin_pass'] ?? '';
$adminPass2 = $_POST['admin_pass2'] ?? '';
$telegramToken = trim($_POST['telegram_bot_token'] ?? '');
$telegramChat = trim($_POST['telegram_chat_id'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($adminUser === '') {
        $errors[] = 'Admin username is required.';
    }
    if (strlen($adminPass) < 6) {
        $errors[] = 'Admin password must be at least 6 characters.';
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = 'Admin passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo = new PDO(
                "mysql:host={$host};charset=utf8mb4",
                $user,
                $pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $dbNameSafe = str_replace('`', '', $name);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbNameSafe}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$dbNameSafe}`");

            $schema = file_get_contents($root . '/sql/schema.sql');
            $schema = preg_replace('/CREATE DATABASE[\s\S]*?USE `[^`]+`;/i', '', $schema);
            if (!extension_loaded('pdo_mysql')) {
                throw new RuntimeException('PHP PDO MySQL extension is not enabled.');
            }
            if (!extension_loaded('curl')) {
                throw new RuntimeException('PHP cURL extension is not enabled. Enable extension=curl in php.ini.');
            }

            $statements = array_filter(array_map('trim', explode(';', $schema)));
            foreach ($statements as $sql) {
                if ($sql === '' || stripos($sql, 'CREATE TABLE') === false) {
                    continue;
                }
                $pdo->exec($sql);
            }

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $pdo->exec('DELETE FROM admins');
            $stmt = $pdo->prepare('INSERT INTO admins (username, password) VALUES (?, ?)');
            $stmt->execute([$adminUser, $hash]);

            $resetKey = bin2hex(random_bytes(8));
            $cronKey = bin2hex(random_bytes(16));

            $settings = [
                'telegram_bot_token' => $telegramToken,
                'telegram_chat_id' => $telegramChat,
                'status_page_title' => 'Status page',
                'status_page_subtitle' => 'Service status',
                'default_slow_threshold_ms' => '3000',
                'auto_check_interval_seconds' => '5',
                'timezone' => 'Asia/Kuala_Lumpur',
                'password_reset_key' => $resetKey,
                'cron_key' => $cronKey,
            ];
            $ins = $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
            );
            foreach ($settings as $k => $v) {
                $ins->execute([$k, $v]);
            }

            $telegramConfig = $root . '/config/telegram.php';
            $tgContent = "<?php\n"
                . "/**\n * Telegram Bot API credentials.\n */\n"
                . '$telegram_bot_token = ' . var_export($telegramToken, true) . ";\n"
                . '$telegram_chat_id = ' . var_export($telegramChat, true) . ";\n";
            file_put_contents($telegramConfig, $tgContent);

            $dbPhp = "<?php\n"
                . "/**\n * Database connection (PDO).\n */\n"
                . '$db_host = ' . var_export($host, true) . ";\n"
                . '$db_name = ' . var_export($dbNameSafe, true) . ";\n"
                . '$db_user = ' . var_export($user, true) . ";\n"
                . '$db_pass = ' . var_export($pass, true) . ";\n"
                . '$db_charset = \'utf8mb4\';' . "\n\n"
                . '$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";' . "\n\n"
                . "try {\n"
                . "    \$pdo = new PDO(\$dsn, \$db_user, \$db_pass, [\n"
                . "        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,\n"
                . "        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,\n"
                . "        PDO::ATTR_EMULATE_PREPARES   => false,\n"
                . "    ]);\n"
                . "} catch (PDOException \$e) {\n"
                . "    http_response_code(500);\n"
                . "    echo '<h2>Database connection failed</h2><p>' . htmlspecialchars(\$e->getMessage()) . '</p>';\n"
                . "    exit;\n"
                . "}\n";

            if (file_put_contents($dbFile, $dbPhp) === false) {
                throw new RuntimeException('Could not write config/database.php. Check folder permissions.');
            }

            file_put_contents($lockFile, 'installed:' . date('c') . PHP_EOL);
            $success = true;
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install | Website Monitoring System</title>
    <style>
        body { font-family: Segoe UI, sans-serif; background:#0f172a; color:#e2e8f0; margin:0; }
        .box { max-width:640px; margin:40px auto; background:#111827; padding:32px; border-radius:16px; border:1px solid #1f2937; }
        h1 { margin-top:0; }
        label { display:block; margin:14px 0 6px; font-size:14px; }
        input { width:100%; box-sizing:border-box; padding:10px 12px; border-radius:8px; border:1px solid #334155; background:#0b1220; color:#fff; }
        button { margin-top:18px; background:#0d9488; color:#fff; border:0; padding:12px 18px; border-radius:8px; font-weight:600; cursor:pointer; }
        .err { background:#7f1d1d; padding:10px 12px; border-radius:8px; margin:8px 0; }
        .ok { background:#14532d; padding:12px; border-radius:8px; }
        .hint { color:#94a3b8; font-size:13px; }
        code { background:#1e293b; padding:2px 6px; border-radius:4px; }
        a { color:#5eead4; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        @media (max-width:700px) { .grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<div class="box">
    <h1>Install Website Monitoring System</h1>
    <p class="hint">XAMPP-friendly setup. Default MySQL user is <code>root</code> with an empty password.</p>

    <?php if ($success): ?>
        <div class="ok">
            <p><strong>Installation complete.</strong></p>
            <p>Save these keys:</p>
            <p>Password reset key: <code><?php echo h($resetKey); ?></code></p>
            <p>Cron key: <code><?php echo h($cronKey); ?></code></p>
            <p>
                <a href="<?php echo h($base); ?>/admin/login.php">Open admin login</a>
                &nbsp;·&nbsp;
                <a href="<?php echo h($base); ?>/status/">Open public status page</a>
            </p>
        </div>
    <?php else: ?>
        <?php foreach ($errors as $err): ?>
            <div class="err"><?php echo h($err); ?></div>
        <?php endforeach; ?>
        <form method="post">
            <h3>Database</h3>
            <div class="grid">
                <div>
                    <label>MySQL host</label>
                    <input name="db_host" value="<?php echo h($host); ?>" required>
                </div>
                <div>
                    <label>Database name</label>
                    <input name="db_name" value="<?php echo h($name); ?>" required>
                </div>
                <div>
                    <label>MySQL username</label>
                    <input name="db_user" value="<?php echo h($user); ?>" required>
                </div>
                <div>
                    <label>MySQL password</label>
                    <input name="db_pass" type="password" value="<?php echo h($pass); ?>">
                </div>
            </div>
            <h3>Admin account</h3>
            <label>Username</label>
            <input name="admin_user" value="<?php echo h($adminUser); ?>" required>
            <label>Password</label>
            <input name="admin_pass" type="password" required minlength="6">
            <label>Confirm password</label>
            <input name="admin_pass2" type="password" required minlength="6">
            <h3>Telegram (optional — can be set later)</h3>
            <label>Bot token</label>
            <input name="telegram_bot_token" value="<?php echo h($telegramToken); ?>">
            <label>Chat ID</label>
            <input name="telegram_chat_id" value="<?php echo h($telegramChat); ?>">
            <button type="submit">Install</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
