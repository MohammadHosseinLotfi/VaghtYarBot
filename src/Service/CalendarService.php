<?php

namespace App\Service;

use IntlCalendar;
use DateTimeZone;
use DateTime;

class CalendarService
{
    private const WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    private const MONTHS = [
        1=>'فروردین',  2=>'اردیبهشت', 3=>'خرداد', 4=>'تیر',
        5=>'مرداد',    6=>'شهریور',   7=>'مهر',   8=>'آبان',
        9=>'آذر',      10=>'دی',      11=>'بهمن', 12=>'اسفند'
    ];

    private const GREGORIAN_MONTHS = [
        1=>'ژانویه',   2=>'فوریه',    3=>'مارس',      4=>'آوریل',
        5=>'مه',       6=>'ژوئن',     7=>'ژوئیه',     8=>'اوت',
        9=>'سپتامبر',  10=>'اکتبر',   11=>'نوامبر',   12=>'دسامبر',
    ];

    private const WEEKDAY_NAMES = [
        7 => 'شنبه',  1 => 'یک‌شنبه',    2 => 'دوشنبه',
        3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنج‌شنبه', 6 => 'جمعه',
    ];

    // ── نقطه ورود اصلی ─────────────────────────────────────────

    /** اولین بار که /cal زده میشه: ماه جاری + اطلاعات امروز */
    public function renderCurrentMonth(): array
    {
        [$y, $m, $d] = $this->getTodayJalali();
        return $this->renderDayView($y, $m, $d);
    }

    /** ناوبری ماه قبل/بعد: فقط متن عنوان ماه */
    public function renderMonth(int $jy, int $jm): array
    {
        $keyboard  = $this->buildKeyboard($jy, $jm);
        $monthName = self::MONTHS[$jm] ?? (string)$jm;

        $text = "🗓 <b>تقویم {$monthName} {$jy}</b>\n"
              . "<i>روی هر روز کلیک کن تا تاریخ میلادی رو ببینی.</i>";

        return [
            'text'         => $text,
            'keyboard'     => $keyboard,
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ];
    }

    /** کلیک روی یک روز: کیبورد همون ماه + متن اطلاعات روز */
    public function renderDayView(int $jy, int $jm, int $jd): array
    {
        $keyboard  = $this->buildKeyboard($jy, $jm);
        $monthName = self::MONTHS[$jm] ?? (string)$jm;

        $text = $this->buildDayText($jy, $jm, $jd)
              . "\n\n🗓 <b>تقویم {$monthName} {$jy}</b>\n"
              . "<i>روی هر روز کلیک کن تا تاریخ میلادی رو ببینی.</i>";

        return [
            'text'         => $text,
            'keyboard'     => $keyboard,
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ];
    }

    /** متن خالص اطلاعات یک روز (شمسی + میلادی + روز هفته) */
    public function buildDayText(int $jy, int $jm, int $jd): string
    {
        [$gy, $gm, $gd] = $this->jalaliToGregorian($jy, $jm, $jd);

        $jMonthName = self::MONTHS[$jm]          ?? (string)$jm;
        $gMonthName = self::GREGORIAN_MONTHS[$gm] ?? (string)$gm;
        $weekday    = $this->getWeekdayName($jy, $jm, $jd);

        return "📅 <b>{$jd} {$jMonthName} {$jy}</b> | {$weekday}\n"
             . "📆 <b>{$gd} {$gMonthName} {$gy}</b>";
    }

    // ── تبدیل تاریخ ────────────────────────────────────────────

    public function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $cal = $this->persianCalendar();
        $cal->clear();
        $cal->set(IntlCalendar::FIELD_YEAR,         $jy);
        $cal->set(IntlCalendar::FIELD_MONTH,        $jm - 1);
        $cal->set(IntlCalendar::FIELD_DAY_OF_MONTH, $jd);

        $dt = new DateTime('@' . (int)($cal->getTime() / 1000));
        $dt->setTimezone(new DateTimeZone('Asia/Tehran'));

        return [(int)$dt->format('Y'), (int)$dt->format('n'), (int)$dt->format('j')];
    }

    // ── ساخت کیبورد ─────────────────────────────────────────────

    private function buildKeyboard(int $jy, int $jm): array
    {
        // تشخیص خودکار امروز
        [$ty, $tm, $td] = $this->getTodayJalali();
        $todayDay = ($jy === $ty && $jm === $tm) ? $td : null;

        $daysInMonth = $this->jalaliDaysInMonth($jy, $jm);
        $firstDowIdx = $this->jalaliFirstDayOfMonthWeekIndex($jy, $jm);
        $monthName   = self::MONTHS[$jm] ?? (string)$jm;

        $keyboard = [];

        // ردیف ۱ — عنوان ماه (plain text)
        $keyboard[] = [['text' => "📅 {$monthName} {$jy}", 'callback_data' => 'noop']];

        // ردیف ۲ — نام روزهای هفته
        $keyboard[] = array_map(
            fn($w) => ['text' => $w, 'callback_data' => 'noop'],
            self::WEEKDAYS
        );

        // ردیف‌های روزها
        $day = 1;
        for ($row = 0; $row < 6; $row++) {
            if ($day > $daysInMonth) break;
            $buttons = [];
            for ($col = 0; $col < 7; $col++) {
                $cell = $row * 7 + $col;
                if ($cell < $firstDowIdx || $day > $daysInMonth) {
                    $buttons[] = ['text' => ' ', 'callback_data' => 'noop'];
                } else {
                    // امروز با [ ] مشخص میشه — plain text، بدون HTML
                    $isToday   = ($todayDay !== null && $day === $todayDay);
                    $label     = $isToday ? "[{$day}]" : (string)$day;
                    $buttons[] = [
                        'text'          => $label,
                        'callback_data' => "calday:{$jy}:{$jm}:{$day}",
                    ];
                    $day++;
                }
            }
            $keyboard[] = $buttons;
        }

        // ردیف آخر — ناوبری
        [$py, $pm] = $this->prevMonth($jy, $jm);
        [$ny, $nm] = $this->nextMonth($jy, $jm);

        $keyboard[] = [
            ['text' => '◀ ماه قبل', 'callback_data' => "cal:{$py}:{$pm}"],
            ['text' => '📅 امروز',   'callback_data' => 'cal:today'],
            ['text' => 'ماه بعد ▶', 'callback_data' => "cal:{$ny}:{$nm}"],
        ];

        return $keyboard;
    }

    // ── ابزارها ─────────────────────────────────────────────────

    public function getTodayJalali(): array
    {
        $cal = $this->persianCalendar();
        $cal->setTime((int)(microtime(true) * 1000));
        return [
            (int)$cal->get(IntlCalendar::FIELD_YEAR),
            (int)$cal->get(IntlCalendar::FIELD_MONTH) + 1,
            (int)$cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }

    private function getWeekdayName(int $jy, int $jm, int $jd): string
    {
        $cal = $this->persianCalendar();
        $cal->clear();
        $cal->set(IntlCalendar::FIELD_YEAR,         $jy);
        $cal->set(IntlCalendar::FIELD_MONTH,        $jm - 1);
        $cal->set(IntlCalendar::FIELD_DAY_OF_MONTH, $jd);
        return self::WEEKDAY_NAMES[(int)$cal->get(IntlCalendar::FIELD_DAY_OF_WEEK)] ?? '';
    }

    private function persianCalendar(): IntlCalendar
    {
        if (!class_exists(IntlCalendar::class)) {
            throw new \RuntimeException('PHP intl extension is required.');
        }
        return IntlCalendar::createInstance(
            new DateTimeZone('Asia/Tehran'),
            'fa_IR@calendar=persian'
        );
    }

    private function jalaliFirstDayOfMonthWeekIndex(int $jy, int $jm): int
    {
        $cal = $this->persianCalendar();
        $cal->clear();
        $cal->set(IntlCalendar::FIELD_YEAR,         $jy);
        $cal->set(IntlCalendar::FIELD_MONTH,        $jm - 1);
        $cal->set(IntlCalendar::FIELD_DAY_OF_MONTH, 1);

        return match ((int)$cal->get(IntlCalendar::FIELD_DAY_OF_WEEK)) {
            7 => 0, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6,
            default => 0,
        };
    }

    private function jalaliDaysInMonth(int $jy, int $jm): int
    {
        if ($jm <= 6)  return 31;
        if ($jm <= 11) return 30;
        return $this->isJalaliLeapYear($jy) ? 30 : 29;
    }

    private function isJalaliLeapYear(int $jy): bool
    {
        return in_array($jy % 33, [1, 5, 9, 13, 17, 22, 26, 30], true);
    }

    private function prevMonth(int $y, int $m): array
    {
        if (--$m < 1) { $m = 12; $y--; }
        return [$y, $m];
    }

    private function nextMonth(int $y, int $m): array
    {
        if (++$m > 12) { $m = 1; $y++; }
        return [$y, $m];
    }
}
