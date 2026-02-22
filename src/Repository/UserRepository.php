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
}
