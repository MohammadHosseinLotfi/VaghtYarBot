<?php

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $_ENV['DB_HOST'],
            $_ENV['DB_NAME'],
            $_ENV['DB_CHARSET'] ?? 'utf8mb4'
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 3,
        ];

        if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
            $options[PDO::MYSQL_ATTR_CONNECT_TIMEOUT] = 3;
        }

        $pdo = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], $options);
    }
    return $pdo;
}

/**
 * پاسخ HTTP ۲۰۰ را سریع برای تلگرام می‌فرستد تا وب‌هوک را تکراری نکند،
 * بعد اسکریپت می‌تواند پردازش را ادامه دهد.
 */
function finishTelegramWebhook(): void
{
    if (headers_sent()) {
        return;
    }

    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Length: 2');
    echo 'OK';

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
        return;
    }

    ignore_user_abort(true);
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}
