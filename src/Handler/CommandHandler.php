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

class CommandHandler
{
    public function __construct(
        private Api               $api,
        private UserRepository    $userRepo,
        private CityRepository    $cityRepo,
        private PrayerTimeService $prayerTime,
        private DateTimeService   $dateTime,
        private CalendarService   $calendar,
        private EventRepository   $eventRepo
    ) {}

    public function handle(Update $update): void
    {
        if ($update->hasLocation()) {
            $this->handleLocation($update);
            return;
        }

        $text = trim($update->getText());

        if ($update->isCommand('start')) {
            $this->handleStart($update);
        } elseif ($update->isCommand('today')) {
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
              . "• موقعیت مکانیت رو مستقیم ارسال کن 📍"
            : "سلام دوباره <b>{$name}</b>! 😊\n"
              . "<code>/today</code> بزن یا موقعیتت رو بفرست.";

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

        // بخش تاریخ
        $msg  = "⏰ ساعت: <code>{$now['time']}</code>\n\n";
        $msg .= "📅 <b>شمسی:</b>  {$now['formatted']}\n";
        $msg .= "📆 <b>میلادی:</b> {$now['g_day']} {$now['g_month_name']} {$now['g_year']}\n";

        if ($now['h_year'] > 0) {
            $msg .= "🌙 <b>قمری:</b>  {$now['h_day']} {$now['h_month_name']} {$now['h_year']}\n";
        }

        $msg .= str_repeat('─', 18) . "\n";

        // بخش مناسبت‌ها
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

        $this->api->sendMessage($update->getChatId(), $msg);
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

        $city = $this->cityRepo->findByName($cityName);
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
            "🔍 شهر <b>{$cityName}</b> پیدا نشد. منظورت اینه؟\n\n"
            . implode("\n", $suggestions)
        );
    }

    // ─── موقعیت مکانی ────────────────────────────────────────────
    private function handleLocation(Update $update): void
    {
        $loc     = $update->getLocation();
        $nearest = $this->cityRepo->findNearest($loc['lat'], $loc['lng']);

        if (!$nearest) {
            $this->api->sendMessage(
                $update->getChatId(),
                "❌ موقعیت شما شناسایی نشد. لطفاً دوباره امتحان کن."
            );
            return;
        }

        $dist = isset($nearest['distance'])
            ? ' — ' . $nearest['distance'] . ' کیلومتر'
            : '';

        $cityData = [
            'name'          => 'موقعیت شما 📍',
            'province_name' => "نزدیک به {$nearest['name']}، {$nearest['province_name']}{$dist}",
            'latitude'      => $loc['lat'],
            'longitude'     => $loc['lng'],
        ];

        $this->api->sendMessage($update->getChatId(), $this->prayerTime->getForCity($cityData), [
            'reply_markup' => ['remove_keyboard' => true],
        ]);
    }
     // ─── تقویم شمسی ────────────────────────────────────────────
    private function handleCal(Update $update): void
    {
        $view = $this->calendar->renderCurrentMonth();
        $this->api->sendMessage($update->getChatId(), $view['text'], [
            'reply_markup' => $view['reply_markup']
        ]);
    }
}
