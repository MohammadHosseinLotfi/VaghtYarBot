<?php
require_once __DIR__ . '/config/bootstrap.php';

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Repository\NotifyRepository;
use App\Service\DateTimeService;
use App\Service\PrayerTimeService;
use App\Service\CalendarService;
use App\Service\GeoService;
use App\Service\NowruzService;
use App\Service\NotifyService;
use App\Handler\CommandHandler;
use App\Handler\CallbackHandler;

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) exit;

finishTelegramWebhook();

$update = new Update($input);
$api    = new Api();

if ($update->getMessage() && $update->getChatId()) {
    $api->sendChatAction((int) $update->getChatId(), 'typing');
}

try {
    $db           = getDB();
    $userRepo     = new UserRepository($db);
    $cityRepo     = new CityRepository($db);
    $eventRepo    = new EventRepository($db);
    $locationRepo = new LocationRepository($db);
    $notifyRepo   = new NotifyRepository($db);
    $dateTime     = new DateTimeService();
    $prayerTime   = new PrayerTimeService($dateTime);
    $calendar     = new CalendarService($eventRepo);
    $nowruz       = new NowruzService();
    $notifySvc    = new NotifyService($notifyRepo, $locationRepo);

    if ($update->getMessage()) {
        $handler = new CommandHandler(
            $api,
            $userRepo,
            $cityRepo,
            $prayerTime,
            $dateTime,
            $calendar,
            $eventRepo,
            new GeoService(),
            $nowruz,
            $locationRepo,
            $notifyRepo,
            $notifySvc
        );
        $handler->handle($update);
        exit;
    }

    if ($update->isCallbackQuery()) {
        (new CallbackHandler(
            $api,
            $calendar,
            $cityRepo,
            $prayerTime,
            $userRepo,
            $locationRepo,
            $notifyRepo,
            $notifySvc
        ))->handle($update);
        exit;
    }

} catch (\Throwable $e) {
    error_log(sprintf(
        '[VaghtYarBot] %s | %s:%d | Trace: %s',
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));

    $chatId = $update->getChatId() ?? $update->getCallbackChatId();
    if ($chatId) {
        try {
            (new Api())->sendMessage((int)$chatId, '⚠️ خطایی رخ داد. لطفاً دوباره امتحان کن.');
        } catch (\Throwable) {}
    }
}
exit;
