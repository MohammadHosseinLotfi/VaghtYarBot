<?php

namespace App\Telegram;

class Update
{
    public function __construct(private array $data) {}

    public function getMessage(): ?array   { return $this->data['message'] ?? null; }
    public function getChatId(): ?int      { return $this->data['message']['chat']['id'] ?? null; }
    public function getUserId(): ?int      { return $this->data['message']['from']['id'] ?? null; }
    public function getFirstName(): string { return $this->data['message']['from']['first_name'] ?? ''; }
    public function getText(): string      { return $this->data['message']['text'] ?? ''; }

    public function isCommand(string $cmd): bool
    {
        $text = $this->getText();
        return $text === "/{$cmd}"
            || str_starts_with($text, "/{$cmd}@")
            || str_starts_with($text, "/{$cmd} ");
    }
}
