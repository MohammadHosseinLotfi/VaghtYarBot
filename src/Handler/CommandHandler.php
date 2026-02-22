<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
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
        private CalendarService   $calendar
    ) {}

    public function handle(Update $update): void
    {
        $text = trim($update->getText());

        if ($update->isCommand('start')) {
            $this->handleStart($update);

        } elseif ($update->isCommand('now')) {
            $this->handleNow($update);

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
            ? "سلام <b>{$name}</b> عزیز 👋\n\nبه ربات وقت‌یار خوش اومدی 🕌"
            : "سلام دوباره <b>{$name}</b>! 😊";

        $this->api->sendMessage($update->getChatId(), $msg);
    }

    // ─── /now ────────────────────────────────────────────────────
    private function handleNow(Update $update): void
    {
        $now = $this->dateTime->getNow();
        $msg = "🗓 <b>{$now['formatted']}</b>\n"
             . "⏰ ساعت: <code>{$now['time']}</code>";

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
            $msg = $this->prayerTime->getForCity($city);
            $this->api->sendMessage($update->getChatId(), $msg);
            return;
        }

        // جستجوی تقریبی
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

        $msg = "🔍 شهر <b>{$cityName}</b> پیدا نشد. منظورت اینه؟\n\n"
             . implode("\n", $suggestions);

        $this->api->sendMessage($update->getChatId(), $msg);
    }

    private function handleCal(Update $update): void
    {
        $view = $this->calendar->renderCurrentMonth();
        $this->api->sendMessage($update->getChatId(), $view['text'], [
            'reply_markup' => $view['reply_markup']
        ]);
    }
}
