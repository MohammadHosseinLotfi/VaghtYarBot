<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Service\PrayerTimeService;
use App\Service\DateTimeService;
use App\Service\CalendarService;
use App\Service\GeoService;
use App\Service\NowruzService;

class CommandHandler
{
    public function __construct(
        private Api               $api,
        private UserRepository    $userRepo,
        private CityRepository    $cityRepo,
        private PrayerTimeService $prayerTime,
        private DateTimeService   $dateTime,
        private CalendarService   $calendar,
        private EventRepository   $eventRepo,
        private GeoService        $geoService,
        private NowruzService     $nowruzService
    ) {}

    public function handle(Update $update): void
    {
        if ($update->hasLocation()) {
            $this->handleLocation($update);
            return;
        }

        $text   = trim($update->getText());
        $offset = null;
        $label  = null;

        if ($update->isCommand('start')) {
            $this->handleStart($update);
        } elseif ($update->isCommand('today') || preg_match('/^امروز$/u', $text)) {
            $this->handleToday($update);
        } elseif ($update->isCommand('ow')) {
            $cityName = $update->getCommandArg('ow');
            $this->handlePrayerTimes($update, $cityName);
        } elseif (preg_match('/^اوقات\s+(.+)$/u', $text, $m)) {
            $this->handlePrayerTimes($update, trim($m[1]));
        } elseif ($update->isCommand('cal')) {
            $this->handleCal($update);
        } elseif (preg_match('/^تقویم$/u', $text)) {
            $this->handleCal($update);
        } elseif ($update->isCommand('nowruz')) {
            $this->handleNowruz($update);
        } elseif ($this->parseDateOffset($text, $offset, $label)) {
            $this->handleDateOffset($update, $offset, $label);
        }
    }

    // ─── /start ──────────────────────────────────────────────────
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
              . "• <code>/cal</code> — تقویم شمسی\n"
              . "• <code>/nowruz</code> — لحظه تحویل سال + شمارش معکوس 🌸\n"
              . "• <code>/nowruz 1406</code> — تحویل سال دلخواه\n"
              . "• موقعیت مکانیت رو مستقیم ارسال کن 📍"
            : "سلام دوباره <b>{$name}</b>! 😊\n\n"
              . "• <code>/today</code> — تاریخ و ساعت + مناسبت‌های امروز\n"
              . "• <code>/ow نام شهر</code> — اوقات شرعی (مثلاً <code>/ow کاشان</code>)\n"
              . "• <code>/cal</code> — تقویم شمسی\n"
              . "• <code>/nowruz</code> — لحظه تحویل سال + شمارش معکوس 🌸\n"
            . "• <code>/nowruz 1406</code> — تحویل سال دلخواه\n";


        if ($update->isGroup()) {
            $this->api->sendMessage($update->getChatId(), $msg);
            return;
        }

        $this->api->sendReplyKeyboard(
            $update->getChatId(),
            $msg,
            [[
                ['text' => '📍 ارسال موقعیت مکانی', 'request_location' => true],
            ]]
        );
    }

    // ─── /today ───────────────────────────────────────────────────
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

    // ─── تاریخ با offset ─────────────────────────────────────────
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

    /**
     * ورودی فارسی رو parse می‌کنه و offset ثانیه‌ای برمی‌گردونه
     * مثال‌های پشتیبانی‌شده:
     *   فردا / پس‌فردا
     *   دیروز / پریروز
     *   ۲ روز بعد / 2 روز بعد
     *   ۳ هفته قبل
     *   ۱ ماه بعد
     */
    private function parseDateOffset(string $text, ?int &$offset, ?string &$label): bool
    {
        // تبدیل اعداد فارسی به انگلیسی
        $text = strtr($text, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        $text = trim($text);

        // کلمات ثابت
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

        // الگو: عدد + واحد + جهت
        // مثال: ۲ روز بعد / 3 هفته قبل / 1 ماه بعد
        $pattern = '/^(\d+)\s+(روز|هفته|ماه|سال)\s+(بعد|دیگه|دیگر|قبل|پیش)$/u';
        if (!preg_match($pattern, $text, $m)) {
            return false;
        }

        $n        = (int) $m[1];
        $unit     = $m[2];
        $dir      = $m[3];
        $sign     = in_array($dir, ['بعد','دیگه','دیگر']) ? +1 : -1;

        $seconds = match ($unit) {
            'روز'   => $n * 86400,
            'هفته'  => $n * 7 * 86400,
            'ماه'   => $n * 30 * 86400,   // تقریبی — کافیه
            'سال'   => $n * 365 * 86400,
            default => 0,
        };

        if ($seconds === 0) return false;

        $offset = $sign * $seconds;

        $unitLabel = match ($unit) {
            'روز'  => $n === 1 ? 'روز' : 'روز',
            'هفته' => $n === 1 ? 'هفته' : 'هفته',
            'ماه'  => $n === 1 ? 'ماه' : 'ماه',
            'سال'  => $n === 1 ? 'سال' : 'سال',
            default => $unit,
        };

        $dirLabel = in_array($dir, ['بعد','دیگه','دیگر']) ? 'بعد' : 'قبل';
        $label    = "{$n} {$unitLabel} {$dirLabel}";

        return true;
    }

    // ─── اوقات شرعی ──────────────────────────────────────────────
    private function handlePrayerTimes(Update $update, string $cityName): void
    {
        if (empty($cityName)) {
            $this->api->sendMessage(
                $update->getChatId(),
                "📍 نام شهر رو بعد از دستور بنویس:\n<code>/ow کاشان</code>"
            );
            return;
        }

        $matches = $this->cityRepo->findAllByExactName($cityName);

        if (count($matches) === 1) {
            $this->api->sendMessage(
                $update->getChatId(),
                $this->prayerTime->getForCity($matches[0])
            );
            return;
        }

        if (count($matches) > 1) {
            $this->api->sendMessage(
                $update->getChatId(),
                "🔍 چند شهر با نام «{$cityName}» پیدا شد:\nیکی رو انتخاب کن 👇",
                ['reply_markup' => ['inline_keyboard' => $this->buildCityKeyboard($matches)]]
            );
            return;
        }

        $city = $this->cityRepo->findCapitalByProvinceName($cityName);
        if ($city) {
            $this->api->sendMessage(
                $update->getChatId(),
                $this->prayerTime->getForCity($city)
            );
            return;
        }

        $results = $this->cityRepo->searchByName($cityName);
        if (empty($results)) {
            $this->api->sendMessage(
                $update->getChatId(),
                "❌ شهر <b>{$cityName}</b> پیدا نشد.\nنام شهر رو دقیق‌تر بنویس."
            );
            return;
        }

        $suggestions = array_map(
            fn($c) => "• <code>/ow {$c['name']}</code> ({$c['province_name']})",
            $results
        );
        $this->api->sendMessage(
            $update->getChatId(),
            "🔍 منظورت اینه؟\n\n" . implode("\n", $suggestions)
        );
    }

    private function buildCityKeyboard(array $cities): array
    {
        $buttons = [];
        foreach ($cities as $city) {
            $label     = $city['province_name']
                ? "{$city['name']} — {$city['province_name']}"
                : $city['name'];
            $buttons[] = ['text' => $label, 'callback_data' => "ow:{$city['id']}"];
        }
        return array_chunk($buttons, 2); // هر ردیف ۲ دکمه
    }

    // ─── موقعیت مکانی ────────────────────────────────────────────
    private function handleLocation(Update $update): void
    {
        $loc = $update->getLocation();
        $lat = (float) $loc['lat'];
        $lng = (float) $loc['lng'];

        $geo = $this->geoService->reverseGeocode($lat, $lng);

        if ($geo !== null && !empty($geo['city'])) {

            if ($geo['country_code'] === 'ir') {
                $name     = $geo['city'];
                $province = $geo['state'] ?? 'ایران';
            } else {
                $name     = $geo['city'];
                $province = $geo['country'] ?? '';
            }

        } else {
            $name     = 'موقعیت ارسال‌شده 📍';
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
            'timezone'      => $tzName
        ];

        $this->api->sendMessage(
            $update->getChatId(),
            $this->prayerTime->getForCity($cityData),
            ['reply_markup' => ['remove_keyboard' => true]]
        );
    }

     // ─── تقویم شمسی ────────────────────────────────────────────
    private function handleCal(Update $update): void
    {
        $view = $this->calendar->renderCurrentMonth();
        $this->api->sendMessage($update->getChatId(), $view['text'], [
            'reply_markup' => $view['reply_markup']
        ]);
    }

    // ─── نوروز ──────────────────────────────────────────────────────
    private function handleNowruz(Update $update): void
    {
        $arg = $update->getCommandArg('nowruz');
        $this->api->sendMessage(
            $update->getChatId(),
            $this->nowruzService->getMessage($arg)
        );
    }
}
