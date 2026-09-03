<?php

namespace App\Service;

use App\Repository\EventRepository;
use IntlCalendar;
use DateTimeZone;
use DateTime;

class CalendarService
{
    private const WEEKDAYS = ['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'];

    private const MONTHS = [
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

    private const WEEKDAY_NAMES = [
        7 => 'شنبه',  1 => 'یک‌شنبه',   2 => 'دوشنبه',
        3 => 'سه‌شنبه', 4 => 'چهارشنبه', 5 => 'پنج‌شنبه', 6 => 'جمعه',
    ];

    public function __construct(private EventRepository $eventRepo) {}

    public function renderCurrentMonth(): array
    {
        [$y, $m, $d] = $this->getTodayJalali();
        return $this->renderDayView($y, $m, $d);
    }

    public function renderMonth(int $jy, int $jm): array
    {
        $keyboard  = $this->buildKeyboard($jy, $jm);
        $monthName = self::MONTHS[$jm] ?? (string)$jm;

        $text = "🗓 <b>تقویم {$monthName} {$jy}</b>\n"
              . "<i>روی هر روز کلیک کن تا اطلاعات روز رو ببینی.</i>";

        return [
            'text'         => $text,
            'keyboard'     => $keyboard,
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ];
    }

    public function renderDayView(int $jy, int $jm, int $jd): array
    {
        $keyboard  = $this->buildKeyboard($jy, $jm);
        $monthName = self::MONTHS[$jm] ?? (string)$jm;

        $text = $this->buildDayText($jy, $jm, $jd)
              . "\n\n🗓 <b>تقویم {$monthName} {$jy}</b>\n"
              . "<i>روی هر روز کلیک کن تا اطلاعات روز رو ببینی.</i>";

        return [
            'text'         => $text,
            'keyboard'     => $keyboard,
            'reply_markup' => ['inline_keyboard' => $keyboard],
        ];
    }

    public function buildDayText(int $jy, int $jm, int $jd): string
    {
        [$gy, $gm, $gd] = $this->jalaliToGregorian($jy, $jm, $jd);
        [$hy, $hm, $hd] = $this->jalaliToHijri($jy, $jm, $jd);

        $jMonthName = self::MONTHS[$jm]           ?? (string)$jm;
        $gMonthName = self::GREGORIAN_MONTHS[$gm] ?? (string)$gm;
        $hMonthName = self::HIJRI_MONTHS[$hm]     ?? (string)$hm;
        $weekday    = $this->getWeekdayName($jy, $jm, $jd);

        $text  = "📅 <b>{$jd} {$jMonthName} {$jy}</b> | {$weekday}\n";
        $text .= "📆 <b>{$gd} {$gMonthName} {$gy}</b>\n";
        $text .= "🌙 <b>{$hd} {$hMonthName} {$hy}</b>\n";
        $text .= str_repeat('─', 18) . "\n";

        $events = $this->eventRepo->getTodayEvents($jm, $jd, $hm, $hd);

        if (empty($events)) {
            $text .= "✅ <i>مناسبتی وجود ندارد</i>";
        } else {
            $text .= "📌 <b>مناسبت‌ها:</b>\n";
            foreach ($events as $e) {
                $title = htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8');
                $icon  = $e['holiday'] ? '🔴' : '▫️';
                $text .= "{$icon} {$title}\n";
            }
            $text = rtrim($text);
        }

        return $text;
    }

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

    public function jalaliToHijri(int $jy, int $jm, int $jd): array
    {
        $persian = $this->persianCalendar();
        $persian->clear();
        $persian->set(IntlCalendar::FIELD_YEAR,         $jy);
        $persian->set(IntlCalendar::FIELD_MONTH,        $jm - 1);
        $persian->set(IntlCalendar::FIELD_DAY_OF_MONTH, $jd);
        $ts = (int) ($persian->getTime() / 1000);

        return HijriCalendar::fromTimestamp($ts);
    }

    private function buildKeyboard(int $jy, int $jm): array
    {
        [$ty, $tm, $td] = $this->getTodayJalali();
        $todayDay    = ($jy === $ty && $jm === $tm) ? $td : null;
        $daysInMonth = $this->jalaliDaysInMonth($jy, $jm);
        $firstDowIdx = $this->jalaliFirstDayOfMonthWeekIndex($jy, $jm);
        $monthName   = self::MONTHS[$jm] ?? (string)$jm;
        $holidays    = $this->holidayDaysInMonth($jy, $jm);

        $keyboard = [];

        $keyboard[] = [['text' => "📅 {$monthName} {$jy}", 'callback_data' => 'noop']];

        // تلگرام ردیف را LTR می‌چیند؛ معکوس می‌کنیم تا شنبه سمت راست باشد.
        $keyboard[] = array_reverse(array_map(
            fn($w) => ['text' => $w, 'callback_data' => 'noop'],
            self::WEEKDAYS
        ));

        $day = 1;
        for ($row = 0; $row < 6; $row++) {
            if ($day > $daysInMonth) break;
            $buttons = [];
            for ($col = 0; $col < 7; $col++) {
                $cell = $row * 7 + $col;
                if ($cell < $firstDowIdx || $day > $daysInMonth) {
                    $buttons[] = ['text' => ' ', 'callback_data' => 'noop'];
                } else {
                    $isToday   = ($todayDay !== null && $day === $todayDay);
                    $isFriday  = ($col === 6);
                    $isHoliday = $isFriday || !empty($holidays[$day]);
                    $btn = [
                        'text'          => $isToday ? "[{$day}]" : (string) $day,
                        'callback_data' => "calday:{$jy}:{$jm}:{$day}",
                    ];
                    if ($isHoliday) {
                        $btn['style'] = 'danger';
                    } elseif ($isToday) {
                        $btn['style'] = 'primary';
                    }
                    $buttons[] = $btn;
                    $day++;
                }
            }
            $keyboard[] = array_reverse($buttons);
        }

        [$py, $pm] = $this->prevMonth($jy, $jm);
        [$ny, $nm] = $this->nextMonth($jy, $jm);

        $keyboard[] = [
            ['text' => 'ماه بعد ▶', 'callback_data' => "cal:{$ny}:{$nm}"],
            ['text' => '📅 امروز',   'callback_data' => 'cal:today', 'style' => 'primary'],
            ['text' => '◀ ماه قبل', 'callback_data' => "cal:{$py}:{$pm}"],
        ];

        return $keyboard;
    }

    /** @return array<int, true> */
    private function holidayDaysInMonth(int $jy, int $jm): array
    {
        $daysInMonth = $this->jalaliDaysInMonth($jy, $jm);
        $hijriByMonth = [];
        $dayToHijri   = [];

        for ($d = 1; $d <= $daysInMonth; $d++) {
            [, $hm, $hd] = $this->jalaliToHijri($jy, $jm, $d);
            $dayToHijri[$d] = [$hm, $hd];
            $hijriByMonth[$hm][] = $hd;
        }

        $flags = $this->eventRepo->findHolidays($jm, $hijriByMonth);
        $out   = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            if (!empty($flags['persian'][$d])) {
                $out[$d] = true;
                continue;
            }
            [$hm, $hd] = $dayToHijri[$d];
            if (!empty($flags['hijri'][$hm][$hd])) {
                $out[$d] = true;
            }
        }

        return $out;
    }

    public function getTodayJalali(): array
    {
        $cal = $this->persianCalendar();
        $cal->setTime((int)(microtime(true) * 1000));
        return [
            (int) $cal->get(IntlCalendar::FIELD_YEAR),
            (int) $cal->get(IntlCalendar::FIELD_MONTH) + 1,
            (int) $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
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
