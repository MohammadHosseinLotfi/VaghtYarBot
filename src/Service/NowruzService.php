<?php

namespace App\Service;

require_once __DIR__ . '/../../lib/NowruzCalculator.php';

class NowruzService
{
    /**
     * IRST = UTC+03:30 — بدون DST
     * نوروز همیشه قبل از شروع DST (۱ فروردین نیمه‌شب) اتفاق می‌افتد
     */
    private const TEHRAN_OFFSET = 12600; // 3*3600 + 30*60

    /** تعداد روزهایی که پیام «مبارک» نمایش داده می‌شود */
    private const MUBARAK_DAYS = 4;

    public function getMessage(): string
    {
        $now       = time();
        $currentGY = (int) gmdate('Y', $now + self::TEHRAN_OFFSET);

        [$thisTs, $fromLookup] = \NowruzCalculator::getEquinoxUTC($currentGY);
        $elapsed = $now - $thisTs;

        if ($elapsed < 0) {
            // نوروز هنوز نرسیده — نمایش نوروز امسال
            $targetTs    = $thisTs;
            $targetGY    = $currentGY;
            $fromLookup  = $fromLookup;
            $mode        = 'upcoming';
        } elseif ($elapsed < self::MUBARAK_DAYS * 86400) {
            // ۱ تا ۴ فروردین — ایام مبارک
            $targetTs    = $thisTs;
            $targetGY    = $currentGY;
            $mode        = 'mubarak';
        } else {
            // بعد از ۴ روز — نوروز سال آینده
            [$targetTs, $fromLookup] = \NowruzCalculator::getEquinoxUTC($currentGY + 1);
            $targetGY = $currentGY + 1;
            $mode     = 'upcoming';
        }

        // ── اطلاعات لحظه تحویل ──────────────────────────────────
        $tTehran = $targetTs + self::TEHRAN_OFFSET;

        $tH = (int) gmdate('G', $tTehran);
        $tM = (int) gmdate('i', $tTehran);
        $tS = (int) gmdate('s', $tTehran);
        $gY = (int) gmdate('Y', $tTehran);
        $gM = (int) gmdate('n', $tTehran);
        $gD = (int) gmdate('j', $tTehran);
        $dow = (int) gmdate('w', $tTehran);

        // تبدیل به شمسی و قمری
        [$jy, $jm, $jd] = \NowruzCalculator::toJalali($gY, $gM, $gD);
        [$hy, $hm, $hd] = \NowruzCalculator::toHijri($gY, $gM, $gD);

        $jYear = $targetGY - 621; // سال شمسی که آغاز می‌شود
        $isLeap = \NowruzCalculator::isJalaliLeap($jYear);

        // ── فرمت‌بندی رشته‌ها ───────────────────────────────────
        $timeStr  = \NowruzCalculator::faNum(sprintf('%02d:%02d:%02d', $tH, $tM, $tS));
        $weekday  = \NowruzCalculator::dayNameFa($dow);
        $srcIcon  = $fromLookup ? '📊' : '🔭';

        $jalaliStr = \NowruzCalculator::faNum(sprintf('%04d/%02d/%02d', $jy, $jm, $jd))
                   . ' ' . \NowruzCalculator::jalaliMonthFa($jm)
                   . ' | ' . $weekday;

        $gregStr   = $gD . ' ' . \NowruzCalculator::gregMonthFa($gM) . ' ' . $gY;

        $hijriStr  = \NowruzCalculator::faNum((string)$hd)
                   . ' ' . \NowruzCalculator::hijriMonthFa($hm)
                   . ' ' . \NowruzCalculator::faNum((string)$hy);

        $leapLine  = $isLeap
            ? "\n🔄 سال <b>{$jYear}</b> کبیسه است (اسفند ۳۰ روزه)"
            : '';

        $sep = str_repeat('─', 20);

        // ── بلوک تاریخ مشترک ────────────────────────────────────
        $dateBlock = "⏰ <b>لحظه تحویل:</b> <code>{$timeStr}</code> (تهران) {$srcIcon}\n"
                   . "📅 شمسی:   {$jalaliStr}\n"
                   . "🌍 میلادی: {$gregStr}\n"
                   . "🌙 قمری:   {$hijriStr}"
                   . $leapLine;

        // ── ساخت پیام نهایی بر اساس حالت ───────────────────────
        if ($mode === 'mubarak') {
            $dayNum = intdiv((int)($now - $thisTs), 86400) + 1;
            return "🌸 <b>نوروز {$jYear} مبارک!</b> 🎉\n"
                 . "{$sep}\n"
                 . $dateBlock . "\n"
                 . "{$sep}\n"
                 . "🗓 روز <b>{$dayNum}م</b> از سال نو";
        }

        // upcoming
        $remaining = $targetTs - $now;
        return "🌸 <b>نوروز {$jYear}</b> {$srcIcon}\n"
             . "{$sep}\n"
             . $dateBlock . "\n"
             . "{$sep}\n"
             . $this->countdownText($remaining);
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
