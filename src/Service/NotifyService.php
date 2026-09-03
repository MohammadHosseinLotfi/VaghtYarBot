<?php

namespace App\Service;

use App\Repository\LocationRepository;
use App\Repository\NotifyRepository;

class NotifyService
{
    public function __construct(
        private NotifyRepository   $notifyRepo,
        private LocationRepository $locationRepo
    ) {}

    public function settingsText(string $placeLabel): string
    {
        $place = htmlspecialchars($placeLabel, ENT_QUOTES, 'UTF-8');
        return "🔔 <b>اعلان اوقات شرعی — {$place}</b>\n\n"
             . "هر وقتی را که می‌خواهی خبر بگیری تیک بزن. تغییرات همان لحظه ذخیره می‌شود.";
    }

    public function settingsMarkup(array $settings): array
    {
        $rows = [];
        foreach (NotifyRepository::PRAYERS as $key => $label) {
            $on = !empty($settings[$key]);
            $rows[] = [[
                'text'          => ($on ? '☑️ ' : '⬜️ ') . $label,
                'callback_data' => 'nt:' . $key,
            ]];
        }
        return ['inline_keyboard' => $rows];
    }

    public function confirmChangeText(string $currentLabel, string $newLabel): string
    {
        $current = htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8');
        $new     = htmlspecialchars($newLabel, ENT_QUOTES, 'UTF-8');
        return "⚠️ شما در حال تغییر شهر خود هستید.\n\n"
             . "شهر فعلی: <b>{$current}</b>\n"
             . "شهر جدید: <b>{$new}</b>\n\n"
             . "آیا از این عملیات مطمئن هستید؟";
    }

    public function confirmChangeMarkup(): array
    {
        return ['inline_keyboard' => [[
            ['text' => '✅ تأیید', 'callback_data' => 'ntchg:ok'],
            ['text' => '❌ لغو',   'callback_data' => 'ntchg:no'],
        ]]];
    }

    public function placeLabelFromCity(array $city): string
    {
        if (!empty($city['id']) && !empty($city['name'])) {
            return (string) $city['name'];
        }
        return LocationRepository::FALLBACK_NAME;
    }
}
