<?php

namespace App\Telegram;

class Api
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = "https://api.telegram.org/bot" . $_ENV['BOT_TOKEN'] . "/";
    }

    public function sendMessage(int|string $chatId, string $text, array $options = []): void
    {
        $this->request('sendMessage', array_merge([
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ], $options));
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
