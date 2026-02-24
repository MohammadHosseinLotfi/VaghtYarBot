<?php

namespace App\Service;

require_once __DIR__ . '/../../lib/NowruzCalculator.php';

class NowruzService
{
    private const TEHRAN_OFFSET = 12600; // UTC+03:30
    private const MUBARAK_DAYS  = 4;
    private const YEAR_MIN      = 1350;
    private const YEAR_MAX      = 1500;

    public function getMessage(string $arg = ''): string
    {
        $now       = time();
        $currentGY = (int) gmdate('Y', $now + self::TEHRAN_OFFSET);

        // ── سال مشخص از آرگومان ──────────────────────────────────
        $arg = trim($arg);
        if ($arg !== '') {
            $reqJYear = $this->parseYear($arg);

            if ($reqJYear === null) {
                $min = \NowruzCalculator::faNum((string) self::YEAR_MIN);
                $max = \NowruzCalculator::faNum((string) self::YEAR_MAX);
                return "❌ سال وارد‌شده معتبر نیست.\n"
                     . "یک عدد بین {$min} تا {$max} وارد کن.\n\n"
                     . "مثال: <code>/nowruz 1406</code>";
            }

            [$targetTs, $fromLookup] = \NowruzCalculator::getEquinoxUTC($reqJYear + 621);
            $mode = ($targetTs < $now) ? 'past' : 'upcoming';
            return $this->buildMessage($now, $reqJYear, $targetTs, $fromLookup, $mode);
        }

        // ── حالت خودکار ──────────────────────────────────────────
        [$thisTs, $fromLookup] = \NowruzCalculator::getEquinoxUTC($currentGY);
        $elapsed = $now - $thisTs;

        if ($elapsed < 0) {
            return $this->buildMessage(
                $now, $currentGY - 621, $thisTs, $fromLookup, 'upcoming'
            );
        }

        if ($elapsed < self::MUBARAK_DAYS * 86400) {
            return $this->buildMessage(
                $now, $currentGY - 621, $thisTs, $fromLookup, 'mubarak'
            );
        }

        [$nextTs, $fromLookup] = \NowruzCalculator::getEquinoxUTC($currentGY + 1);
        return $this->buildMessage(
            $now, $currentGY - 620, $nextTs, $fromLookup, 'upcoming'
        );
    }

    // ── ساخت پیام — تمام حالت‌ها ─────────────────────────────────
    private function buildMessage(
        int  $now,
        int  $jYear,
        int  $targetTs,
        bool $fromLookup,
        string $mode
    ): string {
        $tTehran = $targetTs + self::TEHRAN_OFFSET;

        $tH  = (int) gmdate('G', $tTehran);
        $tM  = (int) gmdate('i', $tTehran);
        $tS  = (int) gmdate('s', $tTehran);
        $gY  = (int) gmdate('Y', $tTehran);
        $gM  = (int) gmdate('n', $tTehran);
        $gD  = (int) gmdate('j', $tTehran);
        $dow = (int) gmdate('w', $tTehran);

        [$jy, $jm, $jd] = \NowruzCalculator::toJalali($gY, $gM, $gD);
        [$hy, $hm, $hd] = \NowruzCalculator::toHijri($gY, $gM, $gD);

        $isLeap   = \NowruzCalculator::isJalaliLeap($jYear);
        $jYearFa  = \NowruzCalculator::faNum((string) $jYear);
        $srcIcon  = $fromLookup ? '📊' : '🔭';
        $timeStr  = \NowruzCalculator::faNum(sprintf('%02d:%02d:%02d', $tH, $tM, $tS));
        $weekday  = \NowruzCalculator::dayNameFa($dow);

        $jalaliStr = \NowruzCalculator::faNum(sprintf('%04d/%02d/%02d', $jy, $jm, $jd))
                   . ' ' . \NowruzCalculator::jalaliMonthFa($jm)
                   . ' | ' . $weekday;

        $gregStr  = $gD . ' ' . \NowruzCalculator::gregMonthFa($gM) . ' ' . $gY;

        $hijriStr = \NowruzCalculator::faNum((string) $hd)
                  . ' ' . \NowruzCalculator::hijriMonthFa($hm)
                  . ' ' . \NowruzCalculator::faNum((string) $hy);

        $leapLine = $isLeap
            ? "\n🔄 سال <b>{$jYearFa}</b> کبیسه است (اسفند ۳۰ روزه)"
            : '';

        $sep = str_repeat('─', 20);

        $dateBlock = "⏰ <b>لحظه تحویل:</b> <code>{$timeStr}</code> (تهران) {$srcIcon}\n"
                   . "📅 شمسی:   {$jalaliStr}\n"
                   . "🌍 میلادی: {$gregStr}\n"
                   . "🌙 قمری:   {$hijriStr}"
                   . $leapLine;

        switch ($mode) {
            case 'mubarak':
                $dayNum = intdiv($now - $targetTs, 86400) + 1;
                return "🌸 <b>نوروز {$jYearFa} مبارک!</b> 🎉\n"
                     . "{$sep}\n"
                     . $dateBlock . "\n"
                     . "{$sep}\n"
                     . "🗓 روز <b>{$dayNum}م</b> از سال نو";

            case 'past':
                return "📅 <b>تحویل سال {$jYearFa} شمسی</b>\n"
                     . "{$sep}\n"
                     . $dateBlock;

            default: // upcoming
                $remaining = $targetTs - $now;
                return "🌸 <b>نوروز {$jYearFa}</b> {$srcIcon}\n"
                     . "{$sep}\n"
                     . $dateBlock . "\n"
                     . "{$sep}\n"
                     . $this->countdownText($remaining);
        }
    }

    // ── تجزیه و اعتبارسنجی آرگومان سال ──────────────────────────
    private function parseYear(string $arg): ?int
    {
        // تبدیل اعداد فارسی و عربی به لاتین
        $arg = strtr($arg, [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4',
            '٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9',
        ]);

        if (!ctype_digit($arg)) return null;

        $year = (int) $arg;
        if ($year < self::YEAR_MIN || $year > self::YEAR_MAX) return null;

        return $year;
    }

    // ── شمارش معکوس ───────────────────────────────────────────────
    private function countdownText(int $seconds): string
    {
        $days  = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $mins  = intdiv($seconds % 3600, 60);
        $secs  = $seconds % 60;

        $parts = [];
        if ($days  > 0) $parts[] = "{$days} روز";
        if ($hours > 0) $parts[] = "{$hours} ساعت";
        if ($mins  > 0) $parts[] = "{$mins} دقیقه";
        if ($secs  > 0 && $days === 0 && $hours === 0) {
            $parts[] = "{$secs} ثانیه";
        }

        return "⏳ <b>مانده تا نوروز:</b>\n" . implode(' و ', $parts);
    }
}
