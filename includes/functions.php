<?php

function get_setting(string $key, string $default = ''): string
{
    global $pdo;
    static $cache = [];
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    $cache[$key] = $row ? (string) $row['setting_value'] : $default;
    return $cache[$key];
}

function set_setting(string $key, string $value): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}

function format_datetime(?string $dt): string
{
    if (!$dt) {
        return 'Never';
    }
    $ts = strtotime($dt);
    return $ts ? date('d M Y, h:i:s A', $ts) : $dt;
}

function format_ms(?int $ms): string
{
    if ($ms === null) {
        return '—';
    }
    if ($ms >= 1000) {
        return number_format($ms / 1000, 2) . ' s';
    }
    return $ms . ' ms';
}

function status_label(string $status): string
{
    switch ($status) {
        case 'up':
            return 'UP';
        case 'down':
            return 'DOWN';
        default:
            return 'UNKNOWN';
    }
}

function website_counts(): array
{
    global $pdo;
    $total = (int) $pdo->query('SELECT COUNT(*) FROM websites')->fetchColumn();
    $up = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'up'")->fetchColumn();
    $down = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'down'")->fetchColumn();
    $unknown = (int) $pdo->query("SELECT COUNT(*) FROM websites WHERE status = 'unknown'")->fetchColumn();
    return compact('total', 'up', 'down', 'unknown');
}

function uptime_percent(int $websiteId, int $days = 90): ?float
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN status = "up" THEN 1 ELSE 0 END) AS up_count,
            COUNT(*) AS total
         FROM logs
         WHERE website_id = ?
           AND checked_at >= DATE_SUB(NOW(), INTERVAL ? DAY)'
    );
    $stmt->execute([$websiteId, $days]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['total'] === 0) {
        return null;
    }
    return round(((int) $row['up_count'] / (int) $row['total']) * 100, 2);
}

function daily_uptime_bars(int $websiteId, int $days = 90): array
{
    global $pdo;
    $stmt = $pdo->prepare(
        'SELECT DATE(checked_at) AS day_date,
                SUM(CASE WHEN status = "down" THEN 1 ELSE 0 END) AS down_count,
                COUNT(*) AS total
         FROM logs
         WHERE website_id = ?
           AND checked_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         GROUP BY DATE(checked_at)'
    );
    $stmt->execute([$websiteId, $days - 1]);
    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $map[$row['day_date']] = $row;
    }

    $bars = [];
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        if (!isset($map[$date])) {
            $bars[] = [
                'date' => $date,
                'state' => 'nodata',
                'uptime' => null,
                'label' => date('d M Y', strtotime($date)) . ' — No data',
            ];
            continue;
        }
        $down = (int) $map[$date]['down_count'];
        $total = (int) $map[$date]['total'];
        $up = $total - $down;
        $pct = $total > 0 ? round(($up / $total) * 100, 2) : 0;
        if ($down === 0) {
            $state = 'up';
        } elseif ($down === $total) {
            $state = 'down';
        } else {
            $state = 'partial';
        }
        $bars[] = [
            'date' => $date,
            'state' => $state,
            'uptime' => $pct,
            'label' => date('d M Y', strtotime($date)) . ' — ' . $pct . '% uptime',
        ];
    }
    return $bars;
}

function paginate(int $total, int $page, int $perPage = 20): array
{
    $pages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * $perPage;
    return compact('total', 'page', 'perPage', 'pages', 'offset');
}

/**
 * Write Telegram credentials to config/telegram.php (backup / spec requirement).
 * Database settings remain the primary source when saved from Admin Settings.
 */
function sync_telegram_config_file(string $token, string $chatId): bool
{
    $path = CONFIG_PATH . '/telegram.php';
    $content = "<?php\n"
        . "/**\n * Telegram Bot API credentials.\n *\n"
        . " * You can set values here OR in Admin → Settings (database).\n"
        . " * When both are set, Admin Settings (database) takes priority.\n */\n"
        . '$telegram_bot_token = ' . var_export($token, true) . ";\n"
        . '$telegram_chat_id = ' . var_export($chatId, true) . ";\n";

    return file_put_contents($path, $content) !== false;
}

