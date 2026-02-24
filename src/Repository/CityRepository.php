<?php
namespace App\Repository;

class CityRepository
{
    public function __construct(private \PDO $db) {}

    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   COALESCE(p.name, '') AS province_name
            FROM cities c
            LEFT JOIN provinces p ON p.id = c.province_id
            WHERE c.name = ?
            LIMIT 1
        ");
        $stmt->execute([$this->normalize($name)]);
        return $stmt->fetch() ?: null;
    }

    public function searchByName(string $name): array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   COALESCE(p.name, '') AS province_name
            FROM cities c
            LEFT JOIN provinces p ON p.id = c.province_id
            WHERE c.name LIKE ?
            LIMIT 8
        ");
        $stmt->execute(['%' . $this->normalize($name) . '%']);
        return $stmt->fetchAll();
    }

    /** وقتی کاربر نام استان میده، مرکز استان رو پیدا کن */
    public function findCapitalByProvinceName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   p.name AS province_name
            FROM cities c
            JOIN provinces p ON p.id = c.province_id
            WHERE p.name = ? AND c.name = p.capital
            LIMIT 1
        ");
        $stmt->execute([$this->normalize($name)]);
        return $stmt->fetch() ?: null;
    }

    public function findNearest(float $lat, float $lng): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   COALESCE(p.name, '') AS province_name,
                   ROUND(
                       6371 * ACOS(
                           COS(RADIANS(:lat)) * COS(RADIANS(c.latitude))
                           * COS(RADIANS(c.longitude) - RADIANS(:lng))
                           + SIN(RADIANS(:lat)) * SIN(RADIANS(c.latitude))
                       ), 1
                   ) AS distance
            FROM cities c
            LEFT JOIN provinces p ON p.id = c.province_id
            ORDER BY distance ASC
            LIMIT 1
        ");
        $stmt->execute(['lat' => $lat, 'lng' => $lng]);
        return $stmt->fetch() ?: null;
    }

    private function normalize(string $s): string
    {
        $s = str_replace("\u{200C}", ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
