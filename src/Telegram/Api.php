<?php

namespace App\Telegram;

class Api
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = "https://api.telegram.org/bot" . $_ENV['BOT_TOKEN'] . "/";
    }

    public function sendChatAction(int|string $chatId, string $action = 'typing'): void
    {
        $this->request('sendChatAction', [
            'chat_id' => $chatId,
            'action'  => $action,
        ]);
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): bool
    {
        $res = $this->request('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $options));
        return !empty($res['ok']);
    }

    public function sendReplyKeyboard(
        int|string $chatId,
        string     $text,
        array      $keyboard,
        bool       $persistent = false
    ): bool {
        return $this->sendMessage($chatId, $text, [
            'reply_markup' => [
                'keyboard'          => $keyboard,
                'resize_keyboard'   => true,
                'one_time_keyboard' => !$persistent,
            ],
        ]);
    }

    public function removeReplyKeyboard(int|string $chatId, string $text): void
    {
        $this->sendMessage($chatId, $text, [
            'reply_markup' => ['remove_keyboard' => true],
        ]);
    }

    public function editMessageText(
        int|string|null $chatId,
        ?int            $messageId,
        string          $text,
        array           $options = []
    ): void {
        $payload = array_merge([
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $options);
        if (empty($payload['inline_message_id'])) {
            $payload['chat_id']    = $chatId;
            $payload['message_id'] = $messageId;
        }
        $this->request('editMessageText', $payload);
    }

    public function answerInlineQuery(string $inlineQueryId, array $results): void
    {
        $this->request('answerInlineQuery', [
            'inline_query_id' => $inlineQueryId,
            'results'         => $results,
            'cache_time'      => 5,
            'is_personal'     => true,
        ]);
    }

    public function answerCallbackQuery(
        string  $callbackQueryId,
        ?string $text      = null,
        bool    $showAlert = false
    ): void {
        $payload = ['callback_query_id' => $callbackQueryId];
        if ($text !== null)  $payload['text']       = $text;
        if ($showAlert)      $payload['show_alert'] = true;
        $this->request('answerCallbackQuery', $payload);
    }

    private function request(string $method, array $data): array
    {
        $ch = curl_init($this->baseUrl . $method);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true) ?? [];
    }
}
