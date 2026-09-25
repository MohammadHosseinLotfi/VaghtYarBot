<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Service\CalendarService;
use App\Service\PrayerTimeService;
use App\Service\NotifyService;
use App\Repository\CityRepository;
use App\Repository\UserRepository;
use App\Repository\LocationRepository;
use App\Repository\NotifyRepository;

class CallbackHandler
{
    private ?string $inlineId = null;
    private ?int $chatId = null;
    private ?int $msgId = null;

    public function __construct(
        private Api                $api,
        private CalendarService    $calendar,
        private CityRepository     $cityRepo,
        private PrayerTimeService  $prayerTime,
        private UserRepository     $userRepo,
        private LocationRepository $locationRepo,
        private NotifyRepository   $notifyRepo,
        private NotifyService      $notifyService
    ) {}

    public function handle(Update $update): void
    {
        $data   = $update->getCallbackData();
        $cbId   = $update->getCallbackQueryId();
        $userId = $update->getCallbackUserId();
        $this->chatId   = $update->getCallbackChatId();
        $this->msgId    = $update->getCallbackMessageId();
        $this->inlineId = $update->getCallbackInlineMessageId();

        if (!$cbId || !$userId) {
            return;
        }
        if (!$this->inlineId && (!$this->chatId || !$this->msgId)) {
            return;
        }

        $this->userRepo->save($userId);

        if ($data === 'save:place') {
            $this->savePlace($cbId, $userId);
            return;
        }

        if (preg_match('/^nt:([a-z]+)$/', $data, $m)) {
            $this->toggleNotify($cbId, $userId, $m[1]);
            return;
        }

        if (preg_match('/^ntcity:(\d+)$/', $data, $m)) {
            $city = $this->cityRepo->findById((int) $m[1]);
            if (!$city) {
                $this->api->answerCallbackQuery($cbId, '❌ شهر پیدا نشد.', true);
                return;
            }
            $this->beginNotifyCity($cbId, $userId, $city, true);
            return;
        }

        if ($data === 'ntchg:ok') {
            $this->confirmCityChange($cbId, $userId);
            return;
        }

        if ($data === 'ntchg:no') {
            $this->userRepo->forgetContext($userId, 'notify_city');
            $this->edit('❌ عملیات لغو شد.');
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        if (preg_match('/^ow:(\d+)$/', $data, $m)) {
            $city = $this->cityRepo->findById((int) $m[1]);
            if ($city) {
                $markup = $this->saveMarkup($userId, $city);
                $opts   = $markup ? ['reply_markup' => $markup] : [];
                $this->edit($this->prayerTime->getForCity($city), $opts);
                $this->api->answerCallbackQuery($cbId);
            } else {
                $this->api->answerCallbackQuery($cbId, '❌ شهر پیدا نشد.', true);
            }
            return;
        }

        if (preg_match('/^cal:(\d{4}):(\d{1,2})$/', $data, $m)) {
            $view = $this->calendar->renderMonth((int) $m[1], (int) $m[2]);
            $this->edit($view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        if ($data === 'cal:today') {
            $view = $this->calendar->renderCurrentMonth();
            $this->edit($view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId, '📅 برگشتی به ماه جاری');
            return;
        }

        if (preg_match('/^calday:(\d{4}):(\d{1,2}):(\d{1,2})$/', $data, $m)) {
            $view = $this->calendar->renderDayView((int) $m[1], (int) $m[2], (int) $m[3]);
            $this->edit($view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        if (preg_match('/^hol:(\d{4}):(\d{1,2})$/', $data, $m)) {
            $view = $this->calendar->renderHolidaysMonth((int) $m[1], (int) $m[2]);
            $this->edit($view['text'], [
                'reply_markup' => $view['reply_markup'],
            ]);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        $this->api->answerCallbackQuery($cbId);
    }

    private function savePlace(string $cbId, int $userId): void
    {
        $pending = $this->userRepo->getContext($userId)['save'] ?? null;
        if (!is_array($pending) || !isset($pending['lat'], $pending['lng'])) {
            $this->api->answerCallbackQuery($cbId, 'این دکمه منقضی شده. دوباره اوقات را بگیر.', true);
            return;
        }

        $cityId = !empty($pending['city_id']) ? (int) $pending['city_id'] : null;
        $lat    = (float) $pending['lat'];
        $lng    = (float) $pending['lng'];

        $this->locationRepo->upsert($userId, $cityId, $lat, $lng);
        $this->userRepo->forgetContext($userId, 'save');

        $toast = $cityId
            ? (($this->cityRepo->findById($cityId)['name'] ?? 'شهر') . ' ذخیره شد')
            : 'موقعیت مکانی شما ذخیره شد';

        $this->api->answerCallbackQuery($cbId, $toast);
    }

    private function toggleNotify(string $cbId, int $userId, string $prayer): void
    {
        if (!$this->notifyRepo->isPrayer($prayer)) {
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        $location = $this->locationRepo->findByUserId($userId);
        if ($location === null) {
            $this->api->answerCallbackQuery($cbId, 'اول یک شهر ذخیره کن.', true);
            return;
        }

        $settings = $this->notifyRepo->toggle($userId, $prayer);
        $label    = $this->locationRepo->label($location);
        $this->edit(
            $this->notifyService->settingsText($label),
            ['reply_markup' => $this->notifyService->settingsMarkup($settings)]
        );
        $this->api->answerCallbackQuery($cbId);
    }

    public function beginNotifyCity(
        string $cbId,
        int $userId,
        array $city,
        bool $edit
    ): void {
        $cityId = isset($city['id']) ? (int) $city['id'] : null;
        $lat    = (float) $city['latitude'];
        $lng    = (float) $city['longitude'];
        $saved  = $this->locationRepo->findByUserId($userId);

        if ($saved === null || $this->locationRepo->isSame($saved, $cityId, $lat, $lng)) {
            if ($saved === null) {
                $this->locationRepo->upsert($userId, $cityId, $lat, $lng);
            }
            $this->showSettings($userId, $edit);
            $this->api->answerCallbackQuery($cbId);
            return;
        }

        $this->userRepo->putContext($userId, 'notify_city', [
            'city_id' => $cityId,
            'lat'     => $lat,
            'lng'     => $lng,
        ]);

        $text   = $this->notifyService->confirmChangeText(
            $this->locationRepo->label($saved),
            $this->notifyService->placeLabelFromCity($city)
        );
        $markup = $this->notifyService->confirmChangeMarkup();

        if ($edit) {
            $this->edit($text, ['reply_markup' => $markup]);
        } else {
            $this->api->sendMessage((int) $this->chatId, $text, ['reply_markup' => $markup]);
        }
        $this->api->answerCallbackQuery($cbId);
    }

    private function confirmCityChange(string $cbId, int $userId): void
    {
        $pending = $this->userRepo->getContext($userId)['notify_city'] ?? null;
        if (!is_array($pending) || !isset($pending['lat'], $pending['lng'])) {
            $this->api->answerCallbackQuery($cbId, 'این درخواست منقضی شده.', true);
            return;
        }

        $cityId = !empty($pending['city_id']) ? (int) $pending['city_id'] : null;
        $this->locationRepo->upsert($userId, $cityId, (float) $pending['lat'], (float) $pending['lng']);
        $this->userRepo->forgetContext($userId, 'notify_city');

        $this->showSettings($userId, true);
        $this->api->answerCallbackQuery($cbId);
    }

    private function showSettings(int $userId, bool $edit): void
    {
        $location = $this->locationRepo->findByUserId($userId);
        $label    = $this->locationRepo->label($location);
        $settings = $this->notifyRepo->getSettings($userId);
        $text     = $this->notifyService->settingsText($label);
        $markup   = $this->notifyService->settingsMarkup($settings);

        if ($edit) {
            $this->edit($text, ['reply_markup' => $markup]);
            return;
        }
        $this->api->sendMessage((int) $this->chatId, $text, ['reply_markup' => $markup]);
    }

    private function edit(string $text, array $options = []): void
    {
        if ($this->inlineId) {
            $this->api->editMessageText(null, null, $text, array_merge($options, [
                'inline_message_id' => $this->inlineId,
            ]));
            return;
        }
        $this->api->editMessageText($this->chatId, $this->msgId, $text, $options);
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
}
