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

    public function getCommandArg(string $cmd): string
    {
        $text = trim($this->getText());
        $prefix = "/{$cmd} ";
        if (str_starts_with($text, $prefix)) {
            return trim(substr($text, strlen($prefix)));
        }
        return '';
    }

    // ---- Callback Query ----
    public function isCallbackQuery(): bool
    {
        return !empty($this->data['callback_query']);
    }

    public function getCallbackQueryId(): ?string
    {
        return $this->data['callback_query']['id'] ?? null;
    }

    public function getCallbackData(): string
    {
        return $this->data['callback_query']['data'] ?? '';
    }

    public function getCallbackChatId(): ?int
    {
        return $this->data['callback_query']['message']['chat']['id'] ?? null;
    }

    public function getCallbackMessageId(): ?int
    {
        return $this->data['callback_query']['message']['message_id'] ?? null;
    }
}
