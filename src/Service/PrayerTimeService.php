<?php

namespace App\Service;

class PrayerTimeService
{
    private const LABELS = [
        's' => ['🌙', 'اذان صبح  '],
        't' => ['🌅', 'طلوع آفتاب'],
        'z' => ['☀️', 'اذان ظهر  '],
        'g' => ['🌆', 'غروب آفتاب'],
        'm' => ['🌙', 'اذان مغرب '],
        'n' => ['🌑', 'نیمه شب   '],
    ];

    public function __construct(private DateTimeService $dateTime)
    {
        require_once __DIR__ . '/../../lib/owghat_function.php';
    }

    public function getForCity(array $city): string
    {
        $now = $this->dateTime->getNow();

        // ترتیب صحیح: owghat($month, $day, $longitude, $latitude, $seconds, $dst, $farsi)
        $times = owghat(
            $now['j_month'],
            $now['j_day'],
            (float) $city['longitude'],
            (float) $city['latitude'],
            1,   // بدون ثانیه
            0,   // بدون تابستانی
            0    // اعداد لاتین
        );

        return $this->format($city, $now, $times);
    }

    private function format(array $city, array $now, array $times): string
    {
        $lines   = [];
        $lines[] = "🕌 <b>اوقات شرعی {$city['name']}</b> ({$city['province_name']})";
        $lines[] = "📅 {$now['formatted']}";
        $lines[] = "";

        foreach (self::LABELS as $key => [$icon, $label]) {
            if (empty($times[$key])) continue;
            $lines[] = "{$icon} {$label} : <code>{$times[$key]}</code>";
        }

        return implode("\n", $lines);
    }
}
