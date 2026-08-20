<?php
/**
 * Database connection (PDO).
 */
$db_host = 'localhost';
$db_name = 'website_monitoring';
$db_user = 'root';
$db_pass = 'Hong2007';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    $GLOBALS['pdo'] = $pdo;
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h2>Database connection failed</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>';
    exit;
}
