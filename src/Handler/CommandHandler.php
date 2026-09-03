<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Repository\NotifyRepository;
use App\Service\PrayerTimeService;
use App\Service\DateTimeService;
use App\Service\CalendarService;
use App\Service\GeoService;
use App\Service\NowruzService;
use App\Service\NotifyService;
use App\Service\DateConvertService;

class CommandHandler
{
    public function __construct(
        private Api                $api,
        private UserRepository     $userRepo,
        private CityRepository     $cityRepo,
        private PrayerTimeService  $prayerTime,
        private DateTimeService    $dateTime,
        private CalendarService    $calendar,
        private EventRepository    $eventRepo,
        private GeoService         $geoService,
        private NowruzService      $nowruzService,
        private LocationRepository $locationRepo,
        private NotifyRepository   $notifyRepo,
        private NotifyService      $notifyService,
        private DateConvertService $dateConvert
    ) {}

    public function handle(Update $update): void
    {
        if ($update->hasLocation()) {
            if ($update->getUserId()) {
                $this->userRepo->save((int) $update->getUserId());
            }
            $this->handleLocation($update);
            return;
        }

        $text   = trim($update->getText());
        $offset = null;
        $label  = null;

        if ($update->isCommand('start')) {
            $this->handleStart($update);
            return;
        }

        if ($update->getUserId()) {
            $this->userRepo->save((int) $update->getUserId());
        }

        if ($update->isCommand('today') || $text === '📅 امروز' || $text === 'امروز') {
            $this->handleToday($update);
        } elseif ($update->isCommand('ow') || $text === '🕌 اوقات شرعی' || $text === 'اوقات شرعی') {
            $cityName = $update->isCommand('ow') ? $update->getCommandArg('ow') : '';
            $this->handlePrayerTimes($update, $cityName);
        } elseif (preg_match('/^اوقات\s+(.+)$/u', $text, $m)) {
            $this->handlePrayerTimes($update, trim($m[1]));
        } elseif ($update->isCommand('notify') || $text === '🔔 اعلان‌ها' || $text === 'اعلان‌ها') {
            $arg = $update->isCommand('notify') ? $update->getCommandArg('notify') : '';
            $this->handleNotify($update, $arg);
        } elseif ($update->isCommand('cal') || $text === '🗓 تقویم' || $text === 'تقویم') {
            $this->handleCal($update);
        } elseif ($update->isCommand('nowruz') || $text === '🌸 نوروز' || $text === 'نوروز') {
            $this->handleNowruz($update);
        } elseif ($update->isCommand('conv')) {
            $this->handleConvert($update, $update->getCommandArg('conv'));
        } elseif (($converted = $this->dateConvert->tryConvert($text)) !== null) {
            $this->api->sendMessage($update->getChatId(), $converted);
        } elseif ($this->parseDateOffset($text, $offset, $label)) {
            $this->handleDateOffset($update, $offset, $label);
        }
    }

    private function mainKeyboard(): array
    {
        return [
            [['text' => '📅 امروز'], ['text' => '🕌 اوقات شرعی']],
            [['text' => '🗓 تقویم'], ['text' => '🌸 نوروز']],
            [
                ['text' => '🔔 اعلان‌ها'],
                ['text' => '📍 موقعیت', 'request_location' => true],
            ],
        ];
    }

    private function handleStart(Update $update): void
    {
        $userId = $update->getUserId();
        $isNew  = $this->userRepo->isNew($userId);
        $this->userRepo->save($userId);

        $name = htmlspecialchars($update->getFirstName(), ENT_QUOTES, 'UTF-8');

        $msg = $isNew
            ? "سلام <b>{$name}</b> عزیز 👋\n\n"
              . "به ربات وقت‌یار خوش اومدی 🕌\n\n"
              . "دستورها:\n"
              . "• <code>/today</code> — تاریخ و ساعت + مناسبت‌های امروز\n"
              . "• <code>/ow نام شهر</code> — اوقات شرعی (مثلاً <code>/ow کاشان</code>)\n"
              . "• <code>/notify</code> — اعلان اذان شهر ذخیره‌شده\n"
              . "• <code>/cal</code> — تقویم شمسی\n"
              . "• <code>/conv تاریخ</code> — تبدیل شمسی/میلادی (مثلاً <code>/conv 1405/6/8</code>)\n"
              . "• <code>/nowruz</code> — لحظه تحویل سال + شمارش معکوس 🌸\n"
              . "• موقعیت مکانیت رو مستقیم ارسال کن 📍"
            : "سلام دوباره <b>{$name}</b>! 😊\n\n"
              . "• <code>/today</code> — تاریخ و ساعت + مناسبت‌های امروز\n"
              . "• <code>/ow نام شهر</code> — اوقات شرعی (مثلاً <code>/ow کاشان</code>)\n"
              . "• <code>/notify</code> — اعلان اذان شهر ذخیره‌شده\n"
              . "• <code>/cal</code> — تقویم شمسی\n"
              . "• <code>/conv تاریخ</code> — تبدیل شمسی/میلادی (مثلاً <code>/conv 1405/6/8</code>)\n"
              . "• <code>/nowruz</code> — لحظه تحویل سال + شمارش معکوس 🌸\n"
              . "• <code>/nowruz 1406</code> — تحویل سال دلخواه\n";

        if ($update->isGroup()) {
            $this->api->sendMessage($update->getChatId(), $msg);
            return;
        }

        $this->api->sendReplyKeyboard(
            $update->getChatId(),
            $msg,
            $this->mainKeyboard(),
            true
        );
    }

    private function handleToday(Update $update): void
    {
        $now = $this->dateTime->getNow();

        $msg  = "⏰ ساعت: <code>{$now['time']}</code>\n\n";
        $msg .= "📅 <b>شمسی:</b>  {$now['formatted']}\n";
        $msg .= "📆 <b>میلادی:</b> {$now['g_day']} {$now['g_month_name']} {$now['g_year']}\n";

        if ($now['h_year'] > 0) {
            $msg .= "🌙 <b>قمری:</b>  {$now['h_day']} {$now['h_month_name']} {$now['h_year']}\n";
        }

        $msg .= str_repeat('─', 18) . "\n";

        $events = $this->eventRepo->getTodayEvents(
            $now['j_month'], $now['j_day'],
            $now['h_month'], $now['h_day']
        );

        if (empty($events)) {
            $msg .= "✅ امروز مناسبت خاصی نیست.";
        } else {
            $msg .= "📌 <b>مناسبت‌های امروز:</b>\n";
            foreach ($events as $e) {
                $title = htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8');
                $icon  = $e['holiday'] ? '🔴' : '▫️';
                $msg  .= "{$icon} {$title}\n";
            }
        }

        $this->api->sendMessage($update->getChatId(), $msg, ['reply_parameters' => ['message_id' => $update->getMessageId()]]);
    }

    private function handleDateOffset(Update $update, int $offsetSeconds, string $label): void
    {
        $d = $this->dateTime->getByOffset($offsetSeconds);

        $msg  = "📅 <b>تاریخ {$label}:</b>\n\n";
        $msg .= "📅 <b>شمسی:</b>  {$d['formatted']}\n";
        $msg .= "📆 <b>میلادی:</b> {$d['g_day']} {$d['g_month_name']} {$d['g_year']}\n";

        if ($d['h_year'] > 0) {
            $msg .= "🌙 <b>قمری:</b>  {$d['h_day']} {$d['h_month_name']} {$d['h_year']}\n";
        }

        $msg .= str_repeat('─', 18) . "\n";

        $events = $this->eventRepo->getTodayEvents(
            $d['j_month'], $d['j_day'],
            $d['h_month'], $d['h_day']
        );

        if (empty($events)) {
            $msg .= "✅ {$label} مناسبت خاصی نیست.";
        } else {
            $msg .= "📌 <b>مناسبت‌های {$label}:</b>\n";
            foreach ($events as $e) {
                $title = htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8');
                $icon  = $e['holiday'] ? '🔴' : '▫️';
                $msg  .= "{$icon} {$title}\n";
            }
        }

        $this->api->sendMessage($update->getChatId(), $msg, ['reply_parameters' => ['message_id' => $update->getMessageId()]]);
    }

    private function parseDateOffset(string $text, ?int &$offset, ?string &$label): bool
    {
        $text = strtr($text, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        $text = trim($text);

        $fixed = [
            'فردا'    => [+1 * 86400,  'فردا'],
            'پس‌فردا' => [+2 * 86400,  'پس‌فردا'],
            'پس فردا' => [+2 * 86400,  'پس‌فردا'],
            'دیروز'   => [-1 * 86400,  'دیروز'],
            'پریروز'  => [-2 * 86400,  'پریروز'],
        ];

        if (isset($fixed[$text])) {
            [$offset, $label] = $fixed[$text];
            return true;
        }

        $pattern = '/^(\d+)\s+(روز|هفته|ماه|سال)\s+(بعد|دیگه|دیگر|قبل|پیش)$/u';
        if (!preg_match($pattern, $text, $m)) {
            return false;
        }

        $n    = (int) $m[1];
        $unit = $m[2];
        $dir  = $m[3];
        $sign = in_array($dir, ['بعد','دیگه','دیگر']) ? +1 : -1;

        $seconds = match ($unit) {
            'روز'   => $n * 86400,
            'هفته'  => $n * 7 * 86400,
            'ماه'   => $n * 30 * 86400,
            'سال'   => $n * 365 * 86400,
            default => 0,
        };

        if ($seconds === 0) {
            return false;
        }

        $offset = $sign * $seconds;
        $dirLabel = in_array($dir, ['بعد','دیگه','دیگر']) ? 'بعد' : 'قبل';
        $label    = "{$n} {$unit} {$dirLabel}";

        return true;
    }

    private function handlePrayerTimes(Update $update, string $cityName): void
    {
        $userId = (int) $update->getUserId();

        if ($cityName === '') {
            $saved = $this->locationRepo->findByUserId($userId);
            if ($saved === null) {
                $this->api->sendMessage(
                    $update->getChatId(),
                    "📍 نام شهر رو بعد از دستور بنویس:\n<code>/ow کاشان</code>"
                );
                return;
            }
            $this->sendPrayerTimes($update->getChatId(), $userId, $this->locationRepo->toCityPayload($saved));
            return;
        }

        $safeName = htmlspecialchars($cityName, ENT_QUOTES, 'UTF-8');
        $matches  = $this->cityRepo->findAllByExactName($cityName);

        if (count($matches) === 1) {
            $this->sendPrayerTimes($update->getChatId(), $userId, $matches[0]);
            return;
        }

        if (count($matches) > 1) {
            $this->api->sendMessage(
                $update->getChatId(),
                "🔍 چند شهر با نام «{$safeName}» پیدا شد:\nیکی رو انتخاب کن 👇",
                ['reply_markup' => ['inline_keyboard' => $this->buildCityKeyboard($matches, 'ow')]]
            );
            return;
        }

        $city = $this->cityRepo->findCapitalByProvinceName($cityName);
        if ($city) {
            $this->sendPrayerTimes($update->getChatId(), $userId, $city);
            return;
        }

        $results = $this->cityRepo->searchByName($cityName);
        if (empty($results)) {
            $this->api->sendMessage(
                $update->getChatId(),
                "❌ شهر <b>{$safeName}</b> پیدا نشد.\nنام شهر رو دقیق‌تر بنویس."
            );
            return;
        }

        $suggestions = array_map(
            fn($c) => "• <code>/ow " . htmlspecialchars($c['name'], ENT_QUOTES, 'UTF-8') . "</code> (" . htmlspecialchars($c['province_name'], ENT_QUOTES, 'UTF-8') . ")",
            $results
        );
        $this->api->sendMessage(
            $update->getChatId(),
            "🔍 منظورت اینه؟\n\n" . implode("\n", $suggestions)
        );
    }

    private function sendPrayerTimes(int $chatId, int $userId, array $city): void
    {
        $opts = [];
        $markup = $this->saveMarkup($userId, $city);
        if ($markup) {
            $opts['reply_markup'] = $markup;
        }
        $this->api->sendMessage($chatId, $this->prayerTime->getForCity($city), $opts);
    }

    private function saveMarkup(int $userId, array $city): ?array
    {
        $lat = (float) ($city['latitude'] ?? 0);
        $lng = (float) ($city['longitude'] ?? 0);
        if ($lat == 0.0 && $lng == 0.0) {
            return null;
        }

        $cityId = isset($city['id']) ? (int) $city['id'] : null;
        $saved  = $this->locationRepo->findByUserId($userId);
        if ($this->locationRepo->isSame($saved, $cityId, $lat, $lng)) {
            return null;
        }

        $this->userRepo->putContext($userId, 'save', [
            'city_id' => $cityId,
            'lat'     => $lat,
            'lng'     => $lng,
        ]);

        return ['inline_keyboard' => [[
            ['text' => '💾 ذخیره به‌عنوان شهر من', 'callback_data' => 'save:place'],
        ]]];
    }

    private function buildCityKeyboard(array $cities, string $prefix): array
    {
        $buttons = [];
        foreach ($cities as $city) {
            $label     = $city['province_name']
                ? "{$city['name']} — {$city['province_name']}"
                : $city['name'];
            $buttons[] = ['text' => $label, 'callback_data' => "{$prefix}:{$city['id']}"];
        }
        return array_chunk($buttons, 2);
    }

    private function handleLocation(Update $update): void
    {
        $loc = $update->getLocation();
        $lat = (float) $loc['lat'];
        $lng = (float) $loc['lng'];

        $geo = $this->geoService->reverseGeocode($lat, $lng);

        if ($geo !== null && !empty($geo['city'])) {
            $name     = $geo['city'];
            $province = ($geo['country_code'] === 'ir')
                ? ($geo['state'] ?? 'ایران')
                : ($geo['country'] ?? '');
        } else {
            $name     = LocationRepository::FALLBACK_NAME;
            $province = '';
        }

        $tzName = ($geo !== null && !empty($geo['timezone']))
            ? $geo['timezone']
            : 'Asia/Tehran';

        $cityData = [
            'name'          => $name,
            'province_name' => $province,
            'latitude'      => $lat,
            'longitude'     => $lng,
            'timezone'      => $tzName,
        ];

        $this->sendPrayerTimes((int) $update->getChatId(), (int) $update->getUserId(), $cityData);
    }

    private function handleCal(Update $update): void
    {
        $view = $this->calendar->renderCurrentMonth();
        $this->api->sendMessage($update->getChatId(), $view['text'], [
            'reply_markup' => $view['reply_markup'],
        ]);
    }

    private function handleNowruz(Update $update): void
    {
        $arg = $update->isCommand('nowruz') ? $update->getCommandArg('nowruz') : '';
        $this->api->sendMessage(
            $update->getChatId(),
            $this->nowruzService->getMessage($arg)
        );
    }

    private function handleNotify(Update $update, string $arg): void
    {
        $userId = (int) $update->getUserId();
        $chatId = (int) $update->getChatId();
        $arg    = trim($arg);

        if ($arg === '') {
            $saved = $this->locationRepo->findByUserId($userId);
            if ($saved === null) {
                $this->api->sendMessage(
                    $chatId,
                    "اول یک شهر ذخیره کن، بعد اعلان را روشن کن.\nمثلاً <code>/ow کاشان</code>"
                );
                return;
            }
            $this->showNotifySettings($chatId, $userId);
            return;
        }

        $safeName = htmlspecialchars($arg, ENT_QUOTES, 'UTF-8');
        $matches  = $this->cityRepo->findAllByExactName($arg);

        if (count($matches) === 1) {
            $this->beginNotifyCity($chatId, $userId, $matches[0]);
            return;
        }

        if (count($matches) > 1) {
            $this->api->sendMessage(
                $chatId,
                "🔍 ��ند شهر با نام «{$safeName}» پیدا شد:\nیکی رو انتخاب کن 👇",
                ['reply_markup' => ['inline_keyboard' => $this->buildCityKeyboard($matches, 'ntcity')]]
            );
            return;
        }

        $city = $this->cityRepo->findCapitalByProvinceName($arg);
        if ($city) {
            $this->beginNotifyCity($chatId, $userId, $city);
            return;
        }

        $results = $this->cityRepo->searchByName($arg);
        if (empty($results)) {
            $this->api->sendMessage($chatId, "❌ شهر <b>{$safeName}</b> پیدا نشد.");
            return;
        }

        $this->api->sendMessage(
            $chatId,
            "🔍 چند شهر با نام مشابه پیدا شد:\nیکی رو انتخاب کن 👇",
            ['reply_markup' => ['inline_keyboard' => $this->buildCityKeyboard($results, 'ntcity')]]
        );
    }

    private function beginNotifyCity(int $chatId, int $userId, array $city): void
    {
        $cityId = isset($city['id']) ? (int) $city['id'] : null;
        $lat    = (float) $city['latitude'];
        $lng    = (float) $city['longitude'];
        $saved  = $this->locationRepo->findByUserId($userId);

        if ($saved === null || $this->locationRepo->isSame($saved, $cityId, $lat, $lng)) {
            if ($saved === null) {
                $this->locationRepo->upsert($userId, $cityId, $lat, $lng);
            }
            $this->showNotifySettings($chatId, $userId);
            return;
        }

        $this->userRepo->putContext($userId, 'notify_city', [
            'city_id' => $cityId,
            'lat'     => $lat,
            'lng'     => $lng,
        ]);

        $this->api->sendMessage(
            $chatId,
            $this->notifyService->confirmChangeText(
                $this->locationRepo->label($saved),
                $this->notifyService->placeLabelFromCity($city)
            ),
            ['reply_markup' => $this->notifyService->confirmChangeMarkup()]
        );
    }

    private function showNotifySettings(int $chatId, int $userId): void
    {
        $location = $this->locationRepo->findByUserId($userId);
        $settings = $this->notifyRepo->getSettings($userId);
        $this->api->sendMessage(
            $chatId,
            $this->notifyService->settingsText($this->locationRepo->label($location)),
            ['reply_markup' => $this->notifyService->settingsMarkup($settings)]
        );
    }

    private function handleConvert(Update $update, string $arg): void
    {
        $arg = trim($arg);
        if ($arg === '') {
            $this->api->sendMessage($update->getChatId(), $this->dateConvert->helpText());
            return;
        }

        $converted = $this->dateConvert->tryConvert($arg);
        if ($converted === null) {
            $this->api->sendMessage(
                $update->getChatId(),
                "❌ تاریخ معتبر نیست.\n\n" . $this->dateConvert->helpText()
            );
            return;
        }

        $this->api->sendMessage($update->getChatId(), $converted);
    }
}
