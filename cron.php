<?php
require_once __DIR__ . '/config/bootstrap.php';

use App\Telegram\Api;
use App\Repository\NotifyRepository;
use App\Service\DateTimeService;
use App\Service\PrayerTimeService;
use App\Service\NotifyCron;

header('Content-Type: text/plain; charset=utf-8');

$expected = (string) ($_ENV['CRON_KEY'] ?? '');
$given    = (string) ($_GET['key'] ?? '');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo "Forbidden\n";
    exit;
}

try {
    $db     = getDB();
    $sent   = (new NotifyCron(
        new NotifyRepository($db),
        new PrayerTimeService(new DateTimeService()),
        new Api()
    ))->run();

    echo "ok sent={$sent}\n";
} catch (\Throwable $e) {
    error_log(sprintf(
        '[VaghtYarBot][cron] %s | %s:%d',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
    http_response_code(500);
    echo "error\n";
}
