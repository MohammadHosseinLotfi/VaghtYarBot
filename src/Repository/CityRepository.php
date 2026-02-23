<?php

namespace App\Repository;

class CityRepository
{
    public function __construct(private \PDO $db) {}

    // ─── جستجوی دقیق (exact + trim) ─────────────────────────────
    public function findByName(string $name): ?array
    {
        // نرمال‌سازی: فاصله‌های اضافه و نیم‌فاصله
        $name = $this->normalize($name);

        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   p.name AS province_name
            FROM cities c
            LEFT JOIN provinces p ON p.id = c.province_id
            WHERE c.name = ?
            LIMIT 1
        ");
        $stmt->execute([$name]);
        return $stmt->fetch() ?: null;
    }

    // ─── جستجوی آزاد (LIKE) ──────────────────────────────────────
    public function searchByName(string $name): array
    {
        $name = $this->normalize($name);

        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   p.name AS province_name
            FROM cities c
            LEFT JOIN provinces p ON p.id = c.province_id
            WHERE c.name LIKE ?
            LIMIT 8
        ");
        $stmt->execute(["%{$name}%"]);
        return $stmt->fetchAll();
    }

    // ─── جستجو بر اساس نام استان ─────────────────────────────────
    // وقتی کاربر می‌گه "اوقات اصفهان" و اصفهان یه استان هم هست
    public function findCapitalByProvinceName(string $provinceName): ?array
    {
        $provinceName = $this->normalize($provinceName);

        $stmt = $this->db->prepare("
            SELECT c.id, c.name, c.latitude, c.longitude,
                   p.name AS province_name
            FROM cities c
            JOIN provinces p ON p.id = c.province_id
            WHERE p.name = ?
              AND c.name = p.capital
            LIMIT 1
        ");
        $stmt->execute([$provinceName]);
        return $stmt->fetch() ?: null;
    }

    // ─── نزدیک‌ترین شهر (Haversine) ──────────────────────────────
    public function findNearest(float $lat, float $lng): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id, c.name, c.latitude, c.longitude,
                p.name AS province_name,
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

    // ─── نرمال‌سازی نام ───────────────────────────────────────────
    private function normalize(string $name): string
    {
        $name = str_replace("\u{200C}", ' ', $name); // نیم‌فاصله → فاصله
        $name = preg_replace('/\s+/', ' ', $name);
        return trim($name);
    }
}
