<?php

namespace App\Telegram;

class Update
{
    public function __construct(private array $data) {}

    public function getMessage(): ?array   { return $this->data['message'] ?? null; }
    public function getMessageId(): ?int   { return $this->data['message']['message_id'] ?? null; }
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
        $cmd  = preg_quote($cmd, '/');
        if (!preg_match('/^\/' . $cmd . '(?:@\S+)?(?:\s+(.*))?$/us', $text, $m)) {
            return '';
        }
        return trim($m[1] ?? '');
    }

    public function isGroup(): bool
    {
        $chatId = $this->getChatId();
        return $chatId !== null && $chatId < 0;
    }

    public function hasLocation(): bool
    {
        return !empty($this->data['message']['location']);
    }

    public function getLocation(): array
    {
        return [
            'lat' => (float)($this->data['message']['location']['latitude']  ?? 0.0),
            'lng' => (float)($this->data['message']['location']['longitude'] ?? 0.0),
        ];
    }

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

    public function getCallbackUserId(): ?int
    {
        return $this->data['callback_query']['from']['id'] ?? null;
    }
}
