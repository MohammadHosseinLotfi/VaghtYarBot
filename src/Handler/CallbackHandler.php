<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Service\CalendarService;

class CallbackHandler
{
    public function __construct(
        private Api $api,
        private CalendarService $calendar
    ) {}

    public function handle(Update $update): void
    {
        $data   = $update->getCallbackData();
        $cbId   = $update->getCallbackQueryId();
        $chatId = $update->getCallbackChatId();
        $msgId  = $update->getCallbackMessageId();

        if (!$cbId || !$chatId || !$msgId) return;

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
            $jy = (int)$m[1];
            $jm = (int)$m[2];
            $jd = (int)$m[3];

            $view = $this->calendar->renderDayView($jy, $jm, $jd);

            $this->api->editMessageText($chatId, $msgId, $view['text'], [
                'reply_markup' => $view['reply_markup'],   // همون کیبورد، بدون تغییر
            ]);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        $this->api->answerCallbackQuery($cbId);
    }
}
