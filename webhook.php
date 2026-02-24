<?php
require_once __DIR__ . '/config/bootstrap.php';

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Service\DateTimeService;
use App\Service\PrayerTimeService;
use App\Service\CalendarService;
use App\Handler\CommandHandler;
use App\Handler\CallbackHandler;
use App\Service\GeoService;
use App\Service\NowruzService;

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) exit;

$update = new Update($input);

try {
    $db         = getDB();
    $dateTime   = new DateTimeService();
    $prayerTime = new PrayerTimeService($dateTime);
    $calendar   = new CalendarService(new EventRepository($db));
    $api        = new Api();
    $nowruz     = new NowruzService();

    if ($update->getMessage()) {
        $handler = new CommandHandler(
            $api,
            new UserRepository($db),
            new CityRepository($db),
            $prayerTime,
            $dateTime,
            $calendar,
            new EventRepository($db),
            new GeoService(),
            $nowruz
        );
        $handler->handle($update);
        exit;
    }

    if ($update->isCallbackQuery()) {
        (new CallbackHandler($api, $calendar))->handle($update);
        exit;
    }

} catch (\Throwable $e) {
    // لاگ کردن خطا
    error_log(sprintf(
        '[VaghtYarBot] %s | %s:%d | Trace: %s',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    // اطلاع به کاربر
    $chatId = $update->getChatId() ?? $update->getCallbackChatId();
    if ($chatId) {
        try {
            (new Api())->sendMessage((int)$chatId, '⚠️ خطایی رخ داد. لطفاً دوباره امتحان کن.');
        } catch (\Throwable) {}
    }
}
exit;
