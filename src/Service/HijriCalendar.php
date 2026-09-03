<?php

namespace App\Service;

use IntlCalendar;
use DateTimeZone;

/**
 * تبدیل قمری هم‌تراز با تقویم رسمی ایران / بادصبا.
 *
 * islamic-civil (مبدأ جمعه) یک روز عقب است.
 * islamic نجومی یک روز جلو می‌افتد.
 * islamic-tbla همان الگوریتم جدولی با مبدأ پنجشنبه است — دقیقاً یک روز
 * نسبت به civil جابه‌جا می‌شود و با ۸ شهریور ۱۴۰۵ = ۱۷ ربیع‌الاول می‌خواند.
 */
class HijriCalendar
{
    public static function fromTimestamp(int $ts): array
    {
        if (!class_exists(IntlCalendar::class)) {
            return [0, 0, 0];
        }

        $cal = IntlCalendar::createInstance(
            new DateTimeZone('Asia/Tehran'),
            'fa_IR@calendar=islamic-tbla'
        );
        $cal->setTime($ts * 1000);

        return [
            (int) $cal->get(IntlCalendar::FIELD_YEAR),
            (int) $cal->get(IntlCalendar::FIELD_MONTH) + 1,
            (int) $cal->get(IntlCalendar::FIELD_DAY_OF_MONTH),
        ];
    }
}
