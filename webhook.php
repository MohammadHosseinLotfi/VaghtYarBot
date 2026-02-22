<?php

require_once __DIR__ . '/config/bootstrap.php';

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Service\DateTimeService;
use App\Service\PrayerTimeService;
use App\Handler\CommandHandler;

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) exit;

$update = new Update($input);
if (!$update->getMessage()) exit;

$db          = getDB();
$dateTime    = new DateTimeService();
$prayerTime  = new PrayerTimeService($dateTime);

$handler = new CommandHandler(
    new Api(),
    new UserRepository($db),
    new CityRepository($db),
    $prayerTime,
    $dateTime
);

$handler->handle($update);
