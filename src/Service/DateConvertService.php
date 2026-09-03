<?php

namespace App\Service;

use App\Repository\EventRepository;
use DateTimeImmutable;
use DateTimeZone;

class DateConvertService
{
    private const JALALI_MONTHS = [
        1 => 'فروردین',  2 => 'اردیبهشت', 3 => 'خرداد',  4 => 'تیر',
        5 => 'مرداد',    6 => 'شهریور',   7 => 'مهر',    8 => 'آبان',
        9 => 'آذر',     10 => 'دی',      11 => 'بهمن',  12 => 'اسفند',
    ];

    private const GREGORIAN_MONTHS = [
        1  => 'ژانویه',   2  => 'فوریه',   3  => 'مارس',
        4  => 'آوریل',    5  => 'مه',      6  => 'ژوئن',
        7  => 'ژوئیه',   8  => 'اوت',     9  => 'سپتامبر',
        10 => 'اکتبر',   11 => 'نوامبر',  12 => 'دسامبر',
    ];

    private const HIJRI_MONTHS = [
        1  => 'محرم',        2  => 'صفر',          3  => 'ربیع‌الاول',
        4  => 'ربیع‌الثانی', 5  => 'جمادی‌الاول',  6  => 'جمادی‌الثانی',
        7  => 'رجب',         8  => 'شعبان',         9  => 'رمضان',
        10 => 'شوال',        11 => 'ذی‌القعده',     12 => 'ذی‌الحجه',
    ];

    private const WEEKDAYS = [
        0 => 'یک‌شنبه', 1 => 'دوشنبه', 2 => 'سه‌شنبه', 3 => 'چهارشنبه',
        4 => 'پنج‌شنبه', 5 => 'جمعه', 6 => 'شنبه',
    ];

    public function __construct(private EventRepository $eventRepo)
    {
        require_once __DIR__ . '/../../lib/jdf.php';
    }

    public function helpText(): string
    {
        return "📅 تاریخ شمسی یا میلادی را بفرست.\n\n"
             . "مثال:\n"
             . "• <code>1405/6/8</code>\n"
             . "• <code>2026-08-30</code>\n"
             . "• <code>/conv 1405/06/08</code>";
    }

    public function tryConvert(string $text): ?string
    {
        $parsed = $this->parse($text);
        if ($parsed === null) {
            return null;
        }

        [$kind, $y, $m, $d] = $parsed;
        $result = $this->convert($kind, $y, $m, $d);
        return $result;
    }

    /** @return array{0: 'jalali'|'gregorian', 1: int, 2: int, 3: int}|null */
    public function parse(string $text): ?array
    {
        $text = strtr(trim($text), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
            '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);

        if (preg_match('/^(\d{4})[\/\-\.](\d{1,2})[\/\-\.](\d{1,2})$/', $text, $m)) {
            return $this->classify((int) $m[1], (int) $m[2], (int) $m[3]);
        }

        if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/', $text, $m)) {
            return $this->classify((int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return null;
    }

    /** @return array{0: 'jalali'|'gregorian', 1: int, 2: int, 3: int}|null */
    private function classify(int $year, int $month, int $day): ?array
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        if ($year >= 1800 && $year <= 2100) {
            if (!checkdate($month, $day, $year)) {
                return null;
            }
            return ['gregorian', $year, $month, $day];
        }

        if ($year >= 1200 && $year <= 1500) {
            if (!function_exists('jcheckdate') || !jcheckdate($month, $day, $year)) {
                return null;
            }
            return ['jalali', $year, $month, $day];
        }

        return null;
    }

    public function convert(string $kind, int $y, int $m, int $d): string
    {
        if ($kind === 'gregorian') {
            [$gy, $gm, $gd] = [$y, $m, $d];
            [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);
        } else {
            [$jy, $jm, $jd] = [$y, $m, $d];
            [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);
        }

        $dt = new DateTimeImmutable(
            sprintf('%04d-%02d-%02d 12:00:00', $gy, $gm, $gd),
            new DateTimeZone('Asia/Tehran')
        );
        [$hy, $hm, $hd] = HijriCalendar::fromTimestamp($dt->getTimestamp());
        $weekday = self::WEEKDAYS[(int) $dt->format('w')] ?? '';

        $msg  = "📅 <b>شمسی</b>\n";
        $msg .= $this->pair($jy, $jm, $jd, self::JALALI_MONTHS);
        if ($weekday !== '') {
            $msg .= "{$weekday}\n";
        }
        $msg .= "\n📆 <b>میلادی</b>\n";
        $msg .= $this->pair($gy, $gm, $gd, self::GREGORIAN_MONTHS);
        $msg .= "\n🌙 <b>قمری</b>\n";
        $msg .= $this->pair($hy, $hm, $hd, self::HIJRI_MONTHS);
        $msg .= str_repeat('─', 18) . "\n";

        $events = $this->eventRepo->getTodayEvents((int) $jm, (int) $jd, (int) $hm, (int) $hd);
        if (empty($events)) {
            $msg .= "✅ مناسبت خاصی نیست.";
        } else {
            $msg .= "📌 <b>مناسبت‌ها:</b>\n";
            foreach ($events as $e) {
                $title = htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8');
                $icon  = $e['holiday'] ? '🔴' : '▫️';
                $msg  .= "{$icon} {$title}\n";
            }
            $msg = rtrim($msg);
        }

        return $msg;
    }

    private function pair(int $y, int $m, int $d, array $months): string
    {
        $numeric = sprintf('%04d/%02d/%02d', $y, $m, $d);
        $named   = sprintf('%04d %s %02d', $y, $months[$m] ?? (string) $m, $d);
        return "<code>{$numeric}</code>\n{$named}\n";
    }
}
