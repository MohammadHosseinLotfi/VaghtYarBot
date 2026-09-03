<?php

namespace App\Repository;

class LocationRepository
{
    public const FALLBACK_NAME = 'موقعیت مکانی شما';

    public function __construct(private \PDO $db) {}

    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ul.user_id,
                   ul.city_id,
                   ul.lat,
                   ul.lng,
                   c.name_fa               AS city_name,
                   c.name_en,
                   COALESCE(p.name_fa, '') AS province_name
            FROM   user_locations ul
            LEFT JOIN cities     c ON c.id = ul.city_id
            LEFT JOIN provinces  p ON p.id = c.p_id
            WHERE  ul.user_id = ?
            LIMIT  1
        ");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upsert(int $userId, ?int $cityId, float $lat, float $lng): void
    {
        $this->db->prepare("
            INSERT INTO user_locations (user_id, city_id, lat, lng)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                city_id = VALUES(city_id),
                lat     = VALUES(lat),
                lng     = VALUES(lng)
        ")->execute([$userId, $cityId, $lat, $lng]);
    }

    public function label(?array $location): string
    {
        if ($location && !empty($location['city_id']) && !empty($location['city_name'])) {
            return (string) $location['city_name'];
        }
        return self::FALLBACK_NAME;
    }

    public function toCityPayload(array $location): array
    {
        $hasCity = !empty($location['city_id']) && !empty($location['city_name']);
        return [
            'id'            => $hasCity ? (int) $location['city_id'] : null,
            'name'          => $hasCity ? (string) $location['city_name'] : self::FALLBACK_NAME,
            'name_en'       => $location['name_en'] ?? null,
            'province_name' => $hasCity ? (string) ($location['province_name'] ?? '') : '',
            'latitude'      => (float) $location['lat'],
            'longitude'     => (float) $location['lng'],
        ];
    }

    public function isSame(?array $saved, ?int $cityId, float $lat, float $lng): bool
    {
        if ($saved === null) {
            return false;
        }

        $savedCity = !empty($saved['city_id']) ? (int) $saved['city_id'] : null;
        $newCity   = $cityId ? (int) $cityId : null;

        if ($savedCity !== null && $newCity !== null) {
            return $savedCity === $newCity;
        }

        if ($savedCity === null && $newCity === null) {
            return abs((float) $saved['lat'] - $lat) < 0.00001
                && abs((float) $saved['lng'] - $lng) < 0.00001;
        }

        return false;
    }
}
