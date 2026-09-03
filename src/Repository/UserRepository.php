<?php

namespace App\Repository;

class UserRepository
{
    public function __construct(private \PDO $db) {}

    public function isNew(int $id): bool
    {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch() === false;
    }

    public function save(int $id): void
    {
        $this->db->prepare("
            INSERT INTO users (id)
            VALUES (?)
            ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP
        ")->execute([$id]);
    }

    public function getContext(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT context FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false || $raw === null || $raw === '') {
            return [];
        }
        $data = json_decode((string) $raw, true);
        return is_array($data) ? $data : [];
    }

    public function putContext(int $userId, string $key, mixed $value): void
    {
        $ctx = $this->getContext($userId);
        $ctx[$key] = $value;
        $this->writeContext($userId, $ctx);
    }

    public function forgetContext(int $userId, string $key): void
    {
        $ctx = $this->getContext($userId);
        unset($ctx[$key]);
        $this->writeContext($userId, $ctx);
    }

    private function writeContext(int $userId, array $ctx): void
    {
        $json = $ctx === [] ? null : json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $this->db->prepare("UPDATE users SET context = ? WHERE id = ?")->execute([$json, $userId]);
    }
}
