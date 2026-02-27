<?php

namespace App\Service;

use IntlCalendar;
use DateTimeZone;

class DateTimeService
{
    private const HIJRI_MONTHS = [
        1  => 'محرم',         2  => 'صفر',
        3  => 'ربیع‌الاول',   4  => 'ربیع‌الثانی',
        5  => 'جمادی‌الاول',  6  => 'جمادی‌الثانی',
        7  => 'رجب',          8  => 'شعبان',
        9  => 'رمضان',        10 => 'شوال',
        11 => 'ذی‌القعده',    12 => 'ذی‌الحجه',
    ];

    private const GREGORIAN_MONTHS = [
        1  => 'ژانویه',   2  => 'فوریه',    3  => 'مارس',
        4  => 'آوریل',    5  => 'مه',       6  => 'ژوئن',
        7  => 'ژوئیه',   8  => 'اوت',      9  => 'سپتامبر',
        10 => 'اکتبر',   11 => 'نوامبر',   12 => 'دسامبر',
    ];

    public function __construct()
    {
        require_once __DIR__ . '/../../lib/jdf.php';
    }

    private function getDateArray(int $ts): array
    {
        $persian = IntlCalendar::createInstance(
            new DateTimeZone('Asia/Tehran'),
            'fa_IR@calendar=persian'
        );
        $persian->setTime($ts * 1000);
        $jYear  = (int) $persian->get(IntlCalendar::FIELD_YEAR);
        $jMonth = (int) $persian->get(IntlCalendar::FIELD_MONTH) + 1;
        $jDay   = (int) $persian->get(IntlCalendar::FIELD_DAY_OF_MONTH);

        $formatted = jdate('l، j F Y', $ts);
        $dayName   = jdate('l', $ts);
        $monthName = jdate('F', $ts);

        $gYear  = (int) date('Y', $ts);
        $gMonth = (int) date('n', $ts);
        $gDay   = (int) date('j', $ts);

        [$hYear, $hMonth, $hDay] = $this->getHijriDate($ts);

        return [
            'j_year'       => $jYear,
            'j_month'      => $jMonth,
            'j_day'        => $jDay,
            'day_name'     => $dayName,
            'month_name'   => $monthName,
            'formatted'    => $formatted,
            'time'         => date('H:i:s', $ts),
            'g_year'       => $gYear,
            'g_month'      => $gMonth,
            'g_day'        => $gDay,
            'g_month_name' => self::GREGORIAN_MONTHS[$gMonth] ?? (string) $gMonth,
            'h_year'       => $hYear,
            'h_month'      => $hMonth,
            'h_day'        => $hDay,
            'h_month_name' => self::HIJRI_MONTHS[$hMonth] ?? (string) $hMonth,
        ];
    }

    public function getNow(): array
    {
        return $this->getDateArray(time());
    }

    /**
     * offset به ثانیه — مثبت = آینده، منفی = گذشته
     * مثال: getByOffset(2 * 86400)  →  ۲ روز بعد
     *        getByOffset(-7 * 86400) →  ۷ روز قبل
     */
    public function getByOffset(int $offsetSeconds): array
    {
        return $this->getDateArray(time() + $offsetSeconds);
    }

    public function getTomorrow(): array
    {
        return $this->getDateArray(time() + 86400);
    }

    public function getYesterday(): array
    {
        return $this->getDateArray(time() - 86400);
    }

    private function getHijriDate(int $ts): array
    {
        if (!class_exists(IntlCalendar::class)) {
            return [0, 0, 0];
        }
        $cal = IntlCalendar::createInstance(
            new DateTimeZone('Asia/Tehran'),
            'fa_IR@calendar=islamic-civil'
        );
        $cal->setTime($ts * 1000);
        return [
            (int) $cal->get(IntlCalendar::FIELD_YEAR),
            (int) $cal->get(IntlCalendar::FIELD_MONTH) + 1, // 0-indexed
            (int) $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }
}
