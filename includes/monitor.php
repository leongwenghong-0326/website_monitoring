<?php

function check_website_url(string $url, int $timeout = 15): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT      => 'WebsiteMonitoringSystem/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_HEADER         => false,
        CURLOPT_NOBODY         => false,
    ]);

    $start = microtime(true);
    $body = curl_exec($ch);
    $elapsed = (int) round((microtime(true) - $start) * 1000);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $reachable = $body !== false && $httpCode >= 200 && $httpCode < 400;

    return [
        'status'        => $reachable ? 'up' : 'down',
        'response_time' => $body !== false ? $elapsed : null,
        'http_code'     => $httpCode,
        'error'         => $reachable ? null : ($error ?: ('HTTP ' . ($httpCode ?: 'timeout'))),
    ];
}

function process_website_check(array $website, bool $force = false): ?array
{
    global $pdo;

    if (!$force && !empty($website['last_checked'])) {
        $next = strtotime($website['last_checked']) + ((int) $website['interval_minutes'] * 60);
        if (time() < $next) {
            return null;
        }
    }

    $check = check_website_url($website['url']);
    $isSlow = 0;
    $threshold = (int) $website['slow_threshold_ms'];
    if ($check['status'] === 'up' && $check['response_time'] !== null && $check['response_time'] > $threshold) {
        $isSlow = 1;
    }

    $log = $pdo->prepare(
        'INSERT INTO logs (website_id, status, response_time, http_code, error_message, is_slow, checked_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW())'
    );
    $log->execute([
        $website['id'],
        $check['status'],
        $check['response_time'],
        $check['http_code'] ?: null,
        $check['error'],
        $isSlow,
    ]);

    $update = $pdo->prepare(
        'UPDATE websites
         SET status = ?, last_checked = NOW(), response_time = ?, is_slow = ?, updated_at = NOW()
         WHERE id = ?'
    );
    $update->execute([
        $check['status'],
        $check['response_time'],
        $isSlow,
        $website['id'],
    ]);

    $previous = $website['last_alert_status'];
    if ($previous === null || $previous === '') {
        $previous = ($website['status'] !== 'unknown') ? $website['status'] : null;
    }
    $newStatus = $check['status'];

    if ($previous !== $newStatus) {
        if ($newStatus === 'down') {
            telegram_alert($website, 'down', $check);
        } elseif ($newStatus === 'up' && $previous === 'down') {
            telegram_alert($website, 'recovery', $check);
        }
        $pdo->prepare('UPDATE websites SET last_alert_status = ? WHERE id = ?')
            ->execute([$newStatus, $website['id']]);
    }

    $wasSlow = (int) $website['is_slow'] === 1;
    if ($newStatus === 'up' && $isSlow && !$wasSlow) {
        telegram_alert($website, 'slow', $check);
    }

    $check['is_slow'] = $isSlow;
    $check['website_id'] = (int) $website['id'];
    return $check;
}

function run_monitoring_cycle(bool $forceAll = false): array
{
    global $pdo;
    $websites = $pdo->query('SELECT * FROM websites ORDER BY id ASC')->fetchAll();
    $checked = 0;
    $alerts = 0;
    $results = [];

    foreach ($websites as $website) {
        $beforeStatus = $website['last_alert_status'];
        $beforeSlow = (int) $website['is_slow'];
        $result = process_website_check($website, $forceAll);
        if ($result === null) {
            continue;
        }
        $checked++;
        if ($result['status'] !== $beforeStatus || ((int) $result['is_slow'] === 1 && $beforeSlow === 0)) {
            $alerts++;
        }
        $results[] = $result;
    }

    return [
        'total'   => count($websites),
        'checked' => $checked,
        'alerts'  => $alerts,
        'results' => $results,
    ];
}

/**
 * Run the monitoring cycle automatically (throttled).
 * Used by admin pages and background AJAX — does not force all sites.
 */
function maybe_run_auto_check(): ?array
{
    static $ranThisRequest = false;
    if ($ranThisRequest) {
        return null;
    }

    $interval = max(5, (int) get_setting('auto_check_interval_seconds', '5'));
    $last = (int) get_setting('last_auto_check_ts', '0');
    if (time() - $last < $interval) {
        return null;
    }

    set_setting('last_auto_check_ts', (string) time());
    $ranThisRequest = true;

    return run_monitoring_cycle(false);
}
