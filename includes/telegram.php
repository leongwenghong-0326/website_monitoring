<?php

function get_telegram_credentials(): array
{
    $token = trim(get_setting('telegram_bot_token'));
    $chatId = trim(get_setting('telegram_chat_id'));

    if ($token === '' || $chatId === '') {
        $configPath = CONFIG_PATH . '/telegram.php';
        if (is_file($configPath)) {
            $telegram_bot_token = '';
            $telegram_chat_id = '';
            include $configPath;
            if ($token === '') {
                $token = trim($telegram_bot_token ?? '');
            }
            if ($chatId === '') {
                $chatId = trim($telegram_chat_id ?? '');
            }
        }
    }

    return ['token' => $token, 'chat_id' => $chatId];
}

function telegram_send(string $text): array
{
    $creds = get_telegram_credentials();
    $token = $creds['token'];
    $chatId = $creds['chat_id'];

    if ($token === '' || $chatId === '') {
        return ['ok' => false, 'error' => 'Telegram bot token or chat ID is not configured.'];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $payload = http_build_query([
        'chat_id' => $chatId,
        'text' => $text,
        'disable_web_page_preview' => true,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);
    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        return ['ok' => false, 'error' => $error ?: 'Could not reach Telegram API.'];
    }

    $json = json_decode($raw, true);
    if (!empty($json['ok'])) {
        return ['ok' => true];
    }

    $apiError = $json['description'] ?? ('HTTP ' . $http);
    return ['ok' => false, 'error' => $apiError];
}

function telegram_alert(array $website, string $type, array $check): bool
{
    global $pdo;

    $response = isset($check['response_time']) ? format_ms((int) $check['response_time']) : 'timeout / n/a';
    $time = date('d M Y, h:i:s A');
    $httpCode = $check['http_code'] ?? 0;

    if ($type === 'down') {
        $title = "🔴 ALERT: Website DOWN";
        $statusText = '🔴 DOWN';
    } elseif ($type === 'recovery') {
        $title = "🟢 RECOVERY: Website back UP";
        $statusText = '🟢 UP';
    } else {
        $title = "🟡 WARNING: Slow response detected";
        $statusText = '🟡 SLOW';
    }

    $lines = [
        $title,
        '',
        'Website: ' . $website['name'],
        'URL: ' . $website['url'],
        'Status: ' . $statusText,
        'Response time: ' . $response,
        'HTTP code: ' . ($httpCode ?: 'n/a'),
        'Time: ' . $time,
    ];

    if (!empty($check['error'])) {
        $lines[] = 'Error: ' . $check['error'];
    }

    $message = implode("\n", $lines);
    $result = telegram_send($message);

    $stmt = $pdo->prepare(
        'INSERT INTO alerts (website_id, alert_type, message, created_at) VALUES (?, ?, ?, NOW())'
    );
    $stmt->execute([(int) $website['id'], $type, $message]);

    return !empty($result['ok']);
}
