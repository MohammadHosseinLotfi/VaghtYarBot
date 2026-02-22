<?php

namespace App\Repository;

class EventRepository
{
    public function __construct(private \PDO $db) {}

    /**
     * مناسبت‌های امروز — شمسی (Iran + AncientIran) و قمری (Iran)
     */
    public function getTodayEvents(
        int $persianMonth, int $persianDay,
        int $hijriMonth,   int $hijriDay
    ): array {
        $stmt = $this->db->prepare("
            SELECT title, holiday, calendar, type
            FROM calendar_events
            WHERE is_irregular = 0
              AND (
                (calendar = 'Persian' AND month = :pm AND day = :pd AND type IN ('Iran', 'AncientIran'))
                OR
                (calendar = 'Hijri'   AND month = :hm AND day = :hd AND type = 'Iran')
              )
            ORDER BY holiday DESC, calendar ASC, id ASC
        ");
        $stmt->execute([
            ':pm' => $persianMonth,
            ':pd' => $persianDay,
            ':hm' => $hijriMonth,
            ':hd' => $hijriDay,
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
