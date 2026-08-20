<?php
/**
 * Application bootstrap — include this at the top of every PHP page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

$scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$scriptName = rtrim($scriptName, '/');
$isCli = PHP_SAPI === 'cli';

// Detect project base URL from filesystem vs htdocs
$docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: '');
$rootReal = str_replace('\\', '/', ROOT_PATH);
if ($docRoot && strpos($rootReal, $docRoot) === 0) {
    $baseUrl = substr($rootReal, strlen($docRoot));
} else {
    $baseUrl = '/website_monitoring';
}
$baseUrl = '/' . trim($baseUrl, '/');
if ($baseUrl === '/') {
    $baseUrl = '';
}
define('BASE_URL', $baseUrl);

$currentScript = basename($_SERVER['SCRIPT_NAME'] ?? '');
if ($isCli && !empty($_SERVER['argv'][0])) {
    $currentScript = basename($_SERVER['argv'][0]);
}
$installLock = ROOT_PATH . '/install.lock';

if (!$isCli && $currentScript !== 'install.php' && !file_exists($installLock)) {
    header('Location: ' . BASE_URL . '/install.php');
    exit;
}

if ($currentScript !== 'install.php') {
    require_once CONFIG_PATH . '/database.php';
    /** @var PDO $pdo */
    $pdo = $GLOBALS['pdo'] ?? null;
    if (!$pdo instanceof PDO) {
        http_response_code(500);
        echo '<h2>Database connection failed</h2><p>PDO was not initialized.</p>';
        exit;
    }
    require_once INCLUDES_PATH . '/functions.php';
    require_once INCLUDES_PATH . '/telegram.php';
    require_once INCLUDES_PATH . '/monitor.php';
    require_once INCLUDES_PATH . '/auth.php';
    $tz = get_setting('timezone', 'Asia/Kuala_Lumpur');
    if ($tz && in_array($tz, timezone_identifiers_list(), true)) {
        date_default_timezone_set($tz);
    }
    try {
        $pdo->exec("SET time_zone = '" . (new DateTime())->format('P') . "'");
    } catch (Throwable $e) {
        // Keep PHP timezone even if MySQL rejects the offset.
    }
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    return BASE_URL . ($path !== '' ? '/' . $path : '');
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array
{
    if (empty($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request token. Please go back and try again.');
    }
}
