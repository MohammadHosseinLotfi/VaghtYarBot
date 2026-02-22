<?php

namespace App\Service;

require_once __DIR__ . '/../../lib/PrayerTimesCalculator.php';

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

    public function __construct(private DateTimeService $dateTime) {}

    public function getForCity(array $city): string
    {
        // timezone باید قبل از new PrayerTimesCalculator ست بشه
        // چون کتابخونه از date_default_timezone_get() استفاده می‌کنه
        date_default_timezone_set('Asia/Tehran');

        $calc = new \PrayerTimesCalculator(
            method:        'Tehran',
            highLatMethod: 'NightMiddle',
            iterations:    1
        );

        // در صورت نیاز می‌تونی offset بزنی:
        // $calc->tune(['fajr' => +1, 'maghrib' => +1]);

        // getTimesSimple → آرایه ساده ['fajr' => '05:19', ...]
        $times = $calc->getTimesSimple(
            date:     date('Y-m-d'),
            coords:   [(float) $city['latitude'], (float) $city['longitude']],
            timezone: 3.5,
            dst:      0,
            format:   '24h'
        );

        return $this->format($city, $times);
    }

    private function format(array $city, array $times): string
    {
        $now     = $this->dateTime->getNow();
        $lines   = [];
        $lines[] = "🕌 <b>اوقات شرعی {$city['name']}</b> ({$city['province_name']})";
        $lines[] = "📅 {$now['formatted']}";
        $lines[] = "";

        foreach (self::LABELS as $key => [$icon, $label]) {
            if (empty($times[$key]) || $times[$key] === '---') continue;
            $lines[] = "{$icon} {$label} : <code>{$times[$key]}</code>";
        }

        return implode("\n", $lines);
    }
}
