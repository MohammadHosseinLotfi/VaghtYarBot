<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Service\CalendarService;
use App\Service\PrayerTimeService;
use App\Repository\CityRepository;

class CallbackHandler
{
    public function __construct(
        private Api               $api,
        private CalendarService   $calendar,
        private CityRepository    $cityRepo,
        private PrayerTimeService $prayerTime
    ) {}

    public function handle(Update $update): void
    {
        $data   = $update->getCallbackData();
        $cbId   = $update->getCallbackQueryId();
        $chatId = $update->getCallbackChatId();
        $msgId  = $update->getCallbackMessageId();

        if (!$cbId || !$chatId || !$msgId) return;

        if (preg_match('/^ow:(\d+)$/', $data, $m)) {
            $city = $this->cityRepo->findById((int)$m[1]);
            if ($city) {
                $this->api->editMessageText(
                    $chatId, $msgId,
                    $this->prayerTime->getForCity($city)
                );
                $this->api->answerCallbackQuery($cbId);
            } else {
                $this->api->answerCallbackQuery($cbId, '❌ شهر پیدا نشد.', true);
            }
            return;
        }

        if (preg_match('/^cal:(\d{4}):(\d{1,2})$/', $data, $m)) {
            $view = $this->calendar->renderMonth((int)$m[1], (int)$m[2]);
            $this->api->editMessageText($chatId, $msgId, $view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        if ($data === 'cal:today') {
            $view = $this->calendar->renderCurrentMonth();
            $this->api->editMessageText($chatId, $msgId, $view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId, '📅 برگشتی به ماه جاری');
            return;
        }

        if (preg_match('/^calday:(\d{4}):(\d{1,2}):(\d{1,2})$/', $data, $m)) {
            $view = $this->calendar->renderDayView((int)$m[1], (int)$m[2], (int)$m[3]);
            $this->api->editMessageText($chatId, $msgId, $view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        $this->api->answerCallbackQuery($cbId);
    }
}
