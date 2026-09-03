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

    /**
     * تعطیل‌های رسمی یک ماه شمسی + ماه‌های قمری متناظر.
     * @param array<int, int[]> $hijriByMonth  مثلاً [9 => [1,2,3], 10 => [28,29]]
     * @return array{persian: array<int, true>, hijri: array<int, array<int, true>>}
     */
    public function findHolidays(int $persianMonth, array $hijriByMonth): array
    {
        $persian = [];
        $hijri   = [];

        $stmt = $this->db->prepare("
            SELECT day
            FROM calendar_events
            WHERE is_irregular = 0
              AND holiday = 1
              AND calendar = 'Persian'
              AND month = ?
              AND type IN ('Iran', 'AncientIran')
              AND day IS NOT NULL
        ");
        $stmt->execute([$persianMonth]);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $persian[(int) $row['day']] = true;
        }

        if ($hijriByMonth === []) {
            return ['persian' => $persian, 'hijri' => $hijri];
        }

        $conds  = [];
        $params = [];
        foreach ($hijriByMonth as $hm => $days) {
            $days = array_values(array_unique(array_map('intval', $days)));
            if ($days === []) {
                continue;
            }
            $ph = implode(',', array_fill(0, count($days), '?'));
            $conds[]  = "(month = ? AND day IN ({$ph}))";
            $params[] = (int) $hm;
            foreach ($days as $d) {
                $params[] = $d;
            }
        }

        if ($conds === []) {
            return ['persian' => $persian, 'hijri' => $hijri];
        }

        $stmt = $this->db->prepare("
            SELECT month, day
            FROM calendar_events
            WHERE is_irregular = 0
              AND holiday = 1
              AND calendar = 'Hijri'
              AND type = 'Iran'
              AND (" . implode(' OR ', $conds) . ")
        ");
        $stmt->execute($params);
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $hijri[(int) $row['month']][(int) $row['day']] = true;
        }

        return ['persian' => $persian, 'hijri' => $hijri];
    }
}
