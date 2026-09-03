<?php

namespace App\Service;

require_once __DIR__ . '/../../lib/PrayerTimesCalculator.php';

use IntlCalendar;
use DateTimeZone;

class PrayerTimeService
{
    private const LABELS = [
        'fajr'     => ['🌙', 'اذان صبح  '],
        'sunrise'  => ['🌅', 'طلوع آفتاب'],
        'dhuhr'    => ['☀️', 'اذان ظهر  '],
        'asr'      => ['🌤', 'اذان عصر  '],
        'sunset'   => ['🌆', 'غروب آفتاب'],
        'maghrib'  => ['🌙', 'اذان مغرب '],
        'isha'     => ['🌃', 'اذان عشاء '],
        'midnight' => ['🌑', 'نیمه‌شب    '],
    ];

    private const NEXT_PRAYERS = [
        'fajr'    => 'اذان صبح',
        'dhuhr'   => 'اذان ظهر',
        'asr'     => 'اذان عصر',
        'maghrib' => 'اذان مغرب',
        'isha'    => 'اذان عشاء',
    ];

    public function __construct(private DateTimeService $dateTime) {}

    public function getForCity(array $city): string
    {
        $times = $this->computeTimes(
            (float) $city['latitude'],
            (float) $city['longitude'],
            $city['timezone'] ?? 'Asia/Tehran'
        );
        return $this->format($city, $times);
    }

    public function computeTimes(float $lat, float $lng, string $tzName = 'Asia/Tehran'): array
    {
        $tz = new \DateTimeZone($tzName);

        $now  = new \DateTimeImmutable('now', $tz);
        $date = $now->format('Y-m-d');

        $currentOffsetHours  = $tz->getOffset($now) / 3600;
        $jan                 = new \DateTimeImmutable($now->format('Y') . '-01-15 12:00:00', $tz);
        $standardOffsetHours = $tz->getOffset($jan) / 3600;
        $dst                 = ($currentOffsetHours > $standardOffsetHours) ? 1 : 0;

        $calc = new \PrayerTimesCalculator(
            method:        'Tehran',
            highLatMethod: 'NightMiddle',
            iterations:    1
        );

        return $calc->getTimesSimple(
            date:     $date,
            coords:   [$lat, $lng],
            timezone: $standardOffsetHours,
            dst:      $dst,
            format:   '24h'
        );
    }

    private function format(array $city, array $times): string
    {
        $now   = $this->dateTime->getNow();
        $lines = [];

        $header = !empty($city['province_name'])
            ? "🕌 <b>اوقات شرعی {$city['name']}</b> ({$city['province_name']})"
            : "🕌 <b>اوقات شرعی {$city['name']}</b>";

        $lines[] = $header;
        $lines[] = "📅 {$now['formatted']}";
        $lines[] = '';

        foreach (self::LABELS as $key => [$icon, $label]) {
            if (empty($times[$key]) || $times[$key] === '---') continue;
            $lines[] = "{$icon} {$label} : <code>{$times[$key]}</code>";
        }

        $nextLine = $this->getNextPrayerLine($times);
        if ($nextLine !== null) {
            $lines[] = '';
            $lines[] = $nextLine;
        }

        $ramadan = $this->getRamadanLine($times);
        if ($ramadan !== null) {
            $lines[] = '';
            $lines[] = $ramadan;
        }

        return implode("\n", $lines);
    }

    private function getNextPrayerLine(array $times): ?string
    {
        $nowMin = $this->timeToMinutes(date('H:i'));

        foreach (self::NEXT_PRAYERS as $key => $label) {
            if (empty($times[$key]) || $times[$key] === '---') continue;

            $pMin = $this->timeToMinutes($times[$key]);

            if ($pMin > $nowMin) {
                $diff = $pMin - $nowMin;
                return "⏳ " . $this->toDuration($diff) . " مانده تا {$label}";
            }
        }

        // همه اذان‌های امروز گذشته — محاسبه مانده تا فجر فردا
        if (!empty($times['fajr']) && $times['fajr'] !== '---') {
            $fajrMin   = $this->timeToMinutes($times['fajr']);
            $remaining = (24 * 60 - $nowMin) + $fajrMin;
            return "⏳ " . $this->toDuration($remaining) . " مانده تا اذان صبح";
        }

        return null;
    }

    private function getRamadanLine(array $times): ?string
    {
        if (!$this->isRamadan()) return null;

        $nowMin     = $this->timeToMinutes(date('H:i'));
        $fajrMin    = (!empty($times['fajr'])    && $times['fajr']    !== '---') ? $this->timeToMinutes($times['fajr'])    : null;
        $maghribMin = (!empty($times['maghrib']) && $times['maghrib'] !== '---') ? $this->timeToMinutes($times['maghrib']) : null;

        if ($fajrMin !== null && $nowMin < $fajrMin) {
            $duration = $this->toDuration($fajrMin - $nowMin);
            return "🌙 <b>ماه مبارک رمضان</b>\n"
                 . "🍽 سحر ساعت <code>{$times['fajr']}</code> — {$duration} دیگه";
        }

        if ($fajrMin !== null && $maghribMin !== null
            && $nowMin >= $fajrMin && $nowMin < $maghribMin) {
            $duration = $this->toDuration($maghribMin - $nowMin);
            return "🌙 <b>ماه مبارک رمضان</b>\n"
                 . "🍽 افطار ساعت <code>{$times['maghrib']}</code> — {$duration} دیگه";
        }

        return null;
    }

    private function isRamadan(): bool
    {
        if (!class_exists(IntlCalendar::class)) return false;

        $cal = IntlCalendar::createInstance(
            new DateTimeZone('Asia/Tehran'),
            'fa_IR@calendar=islamic-civil'
        );
        $cal->setTime((int)(microtime(true) * 1000));

        // FIELD_MONTH صفر-پایه است: 0=محرم ... 8=رمضان
        return $cal->get(IntlCalendar::FIELD_MONTH) === 8;
    }

    private function timeToMinutes(string $time): int
    {
        $parts = explode(':', trim($time));
        return (int)$parts[0] * 60 + (int)($parts[1] ?? 0);
    }

    private function toDuration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h > 0 && $m > 0) return "{$h} ساعت و {$m} دقیقه";
        if ($h > 0)            return "{$h} ساعت";
        return "{$m} دقیقه";
    }
}
