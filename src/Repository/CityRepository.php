<?php

namespace App\Repository;

class CityRepository
{
    public function __construct(private \PDO $db) {}

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude, p.name AS province_name
            FROM cities c
            JOIN provinces p ON p.id = c.province_id
            WHERE c.name = ?
            LIMIT 1
        ");
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    public function searchByName(string $name): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude, p.name AS province_name
            FROM cities c
            JOIN provinces p ON p.id = c.province_id
            WHERE c.name LIKE ?
            LIMIT 5
        ");
        $stmt->execute(["%{$name}%"]);
        return $stmt->fetchAll();
    }
}
