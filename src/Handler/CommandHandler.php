<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;

class CommandHandler
{
    public function __construct(
        private Api            $api,
        private UserRepository $userRepo
    ) {}

    public function handle(Update $update): void
    {
        if ($update->isCommand('start')) {
            $this->handleStart($update);
            return;
        }
    }

    private function handleStart(Update $update): void
    {
        $userId = $update->getUserId();
        $isNew  = $this->userRepo->isNew($userId);

        $this->userRepo->save($userId);

        $name = htmlspecialchars($update->getFirstName(), ENT_QUOTES, 'UTF-8');

        $msg = $isNew
            ? "سلام <b>{$name}</b> عزیز 👋\n\nبه ربات وقت‌یار خوش اومدی 🕌\nبرای شروع شهرت رو انتخاب کن:"
            : "سلام دوباره <b>{$name}</b>! 😊\n\nبه ربات وقت‌یار برگشتی 🕌";

        $this->api->sendMessage($update->getChatId(), $msg);
    }
}
