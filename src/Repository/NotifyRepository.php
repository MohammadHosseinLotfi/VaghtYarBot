<?php

namespace App\Repository;

class NotifyRepository
{
    public const PRAYERS = [
        'fajr'     => 'اذان صبح',
        'sunrise'  => 'طلوع آفتاب',
        'dhuhr'    => 'اذان ظهر',
        'asr'      => 'اذان عصر',
        'sunset'   => 'غروب آفتاب',
        'maghrib'  => 'اذان مغرب',
        'isha'     => 'اذان عشاء',
        'midnight' => 'نیمه‌شب',
    ];

    private const COLUMNS = 'fajr, sunrise, dhuhr, asr, sunset, maghrib, isha, midnight';

    public function __construct(private \PDO $db) {}

    public function isPrayer(string $key): bool
    {
        return isset(self::PRAYERS[$key]);
    }

    public function getSettings(int $userId): array
    {
        $this->ensureRow($userId);
        $stmt = $this->db->prepare("SELECT " . self::COLUMNS . " FROM user_notify_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch() ?: [];
        $out = [];
        foreach (self::PRAYERS as $key => $_) {
            $out[$key] = !empty($row[$key]);
        }
        return $out;
    }

    public function toggle(int $userId, string $prayer): array
    {
        if (!$this->isPrayer($prayer)) {
            throw new \InvalidArgumentException('invalid prayer');
        }
        $this->ensureRow($userId);
        $this->db->prepare("
            UPDATE user_notify_settings
            SET `{$prayer}` = IF(`{$prayer}` = 1, 0, 1)
            WHERE user_id = ?
        ")->execute([$userId]);
        return $this->getSettings($userId);
    }

    /**
     * کاربران دارای حداقل یک اعلان روشن و مکان ذخیره‌شده.
     */
    public function listEnabledWithLocation(): array
    {
        $stmt = $this->db->query("
            SELECT ul.user_id,
                   ul.city_id,
                   ul.lat,
                   ul.lng,
                   c.name_fa AS city_name,
                   ns.fajr, ns.sunrise, ns.dhuhr, ns.asr,
                   ns.sunset, ns.maghrib, ns.isha, ns.midnight
            FROM user_notify_settings ns
            INNER JOIN user_locations ul ON ul.user_id = ns.user_id
            LEFT JOIN cities c ON c.id = ul.city_id
            WHERE ns.fajr = 1 OR ns.sunrise = 1 OR ns.dhuhr = 1 OR ns.asr = 1
               OR ns.sunset = 1 OR ns.maghrib = 1 OR ns.isha = 1 OR ns.midnight = 1
        ");
        return $stmt->fetchAll();
    }

    public function markSent(int $userId, string $prayer, string $forDate): bool
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO notify_sent (user_id, prayer, for_date)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $prayer, $forDate]);
        return $stmt->rowCount() === 1;
    }

    public function purgeOldSent(int $keepDays = 3): void
    {
        $this->db->prepare("DELETE FROM notify_sent WHERE for_date < DATE_SUB(CURDATE(), INTERVAL ? DAY)")
            ->execute([$keepDays]);
    }

    private function ensureRow(int $userId): void
    {
        $this->db->prepare("
            INSERT IGNORE INTO user_notify_settings (user_id) VALUES (?)
        ")->execute([$userId]);
    }
}
