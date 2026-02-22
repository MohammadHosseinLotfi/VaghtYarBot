<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Repository\CityRepository;
use App\Service\PrayerTimeService;
use App\Service\DateTimeService;

class CommandHandler
{
    public function __construct(
        private Api               $api,
        private UserRepository    $userRepo,
        private CityRepository    $cityRepo,
        private PrayerTimeService $prayerTime,
        private DateTimeService   $dateTime
    ) {}

    public function handle(Update $update): void
    {
        $text = trim($update->getText());

        if ($update->isCommand('start')) {
            $this->handleStart($update);

        } elseif ($update->isCommand('now')) {
            $this->handleNow($update);

        } elseif (preg_match('/^اوقات\s+(.+)$/u', $text, $m)) {
            $this->handlePrayerTimes($update, trim($m[1]));
        }
    }

    // ─── /start ──────────────────────────────────────────────
    private function handleStart(Update $update): void
    {
        $userId = $update->getUserId();
        $isNew  = $this->userRepo->isNew($userId);

        $this->userRepo->save($userId);

        $name = htmlspecialchars($update->getFirstName(), ENT_QUOTES, 'UTF-8');

        $msg = $isNew
            ? "سلام <b>{$name}</b> عزیز 👋\n\nبه ربات وقت‌یار خوش اومدی 🕌\n\n"
              . "برای دریافت اوقات شرعی بنویس:\n"
              . "<code>اوقات تهران</code>\n"
              . "یا برای زمان الان: /now"
            : "سلام دوباره <b>{$name}</b>! 😊";

        $this->api->sendMessage($update->getChatId(), $msg);
    }

    // ─── /now ────────────────────────────────────────────────
    private function handleNow(Update $update): void
    {
        $now = $this->dateTime->getNow();
        $msg = "🗓 <b>{$now['formatted']}</b>\n"
             . "⏰ ساعت: <code>{$now['time']}</code>";

        $this->api->sendMessage($update->getChatId(), $msg);
    }

    // ─── اوقات [شهر] ─────────────────────────────────────────
    private function handlePrayerTimes(Update $update, string $cityName): void
    {
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

        // پیشنهاد شهرهای مشابه
        $suggestions = array_map(
            fn($c) => "• <code>اوقات {$c['name']}</code> ({$c['province_name']})",
            $results
        );

        $msg = "🔍 شهر <b>{$cityName}</b> پیدا نشد. منظورت اینه؟\n\n"
             . implode("\n", $suggestions);

        $this->api->sendMessage($update->getChatId(), $msg);
    }
}
