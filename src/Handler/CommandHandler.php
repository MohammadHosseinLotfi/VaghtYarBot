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
        // ── اول location چک کن — چون text ندارد ──────────────────
        if ($update->hasLocation()) {
            $this->handleLocation($update);
            return;
        }

        $text = trim($update->getText());

        if ($update->isCommand('start')) {
            $this->handleStart($update);

        } elseif ($update->isCommand('now')) {
            $this->handleNow($update);

        } elseif ($update->isCommand('ow')) {
            $this->handlePrayerTimes($update, $update->getCommandArg('ow'));

        } elseif (preg_match('/^اوقات\s+(.+)$/u', $text, $m)) {
            $this->handlePrayerTimes($update, trim($m[1]));
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
              . "برای دریافت اوقات شرعی:\n"
              . "• دستور <code>/ow نام شهر</code> مثلاً <code>/ow کاشان</code>\n"
              . "• یا موقعیت مکانیت رو مستقیم ارسال کن 📍"
            : "سلام دوباره <b>{$name}</b>! 😊\n"
              . "موقعیتت رو بفرست یا <code>/ow نام شهر</code> بزن.";

        // دکمه Request Location در Reply Keyboard
        $this->api->sendReplyKeyboard(
            $update->getChatId(),
            $msg,
            [[
                ['text' => '📍 ارسال موقعیت مکانی', 'request_location' => true],
            ]]
        );
    }

    // ─── /now ────────────────────────────────────────────────────
    private function handleNow(Update $update): void
    {
        $now = $this->dateTime->getNow();
        $msg = "🗓 <b>{$now['formatted']}</b>\n"
             . "⏰ ساعت: <code>{$now['time']}</code>";
        $this->api->sendMessage($update->getChatId(), $msg);
    }

    // ─── اوقات شرعی با نام شهر ───────────────────────────────────
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

        // آرایه مصنوعی برای PrayerTimeService — سازگار با getForCity
        $cityData = [
            'name'          => 'موقعیت شما 📍',
            'province_name' => "نزدیک به {$nearest['name']}، {$nearest['province_name']}{$dist}",
            'latitude'      => $loc['lat'],
            'longitude'     => $loc['lng'],
        ];

        $msg = $this->prayerTime->getForCity($cityData);

        // ارسال نتیجه + حذف خودکار Reply Keyboard
        $this->api->sendMessage($update->getChatId(), $msg, [
            'reply_markup' => ['remove_keyboard' => true],
        ]);
    }
}
