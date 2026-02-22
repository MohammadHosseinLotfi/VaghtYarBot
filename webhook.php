<?php

require_once __DIR__ . '/config/bootstrap.php';

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Service\DateTimeService;
use App\Service\PrayerTimeService;
use App\Service\CalendarService;
use App\Handler\CommandHandler;
use App\Handler\CallbackHandler;

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) exit;

$update = new Update($input);

$db          = getDB();
$dateTime    = new DateTimeService();
$prayerTime  = new PrayerTimeService($dateTime);
$calendar    = new CalendarService();
$api         = new Api();

if ($update->getMessage()) {
    $handler = new CommandHandler(
        $api,
        new UserRepository($db),
        new CityRepository($db),
        $prayerTime,
        $dateTime,
        $calendar
    );
    $handler->handle($update);
    exit;
}

if ($update->isCallbackQuery()) {
    $cbHandler = new CallbackHandler($api, $calendar);
    $cbHandler->handle($update);
    exit;
}

exit;
