<?php

declare(strict_types=1);

/**
 * ════════════════════════════════════════════════════════════════════════
 *  Islamic Prayer Times Calculator  —  اوقات شرعی
 * ════════════════════════════════════════════════════════════════════════
 *
 *  ترکیب دو الگوریتم:
 *    ① الگوریتم تکراری praytimes.org (Hamid Zarrabi-Zadeh)
 *    ② الگوریتم نجومی U.S. Naval Observatory
 *
 *  ویژگی‌ها:
 *    — ۱۱ روش محاسبه (تهران، جعفری، MWL، ISNA، مصر، مکه، کراچی، فرانسه، روسیه، سنگاپور، ترکیه)
 *    — محاسبه تکراری برای دقت بیشتر
 *    — پشتیبانی از امساک
 *    — اصلاح عرض‌های جغرافیایی بالا
 *    — پشتیبانی از ارتفاع از سطح دریا
 *    — تنظیم آفست دقیقه‌ای (tune)
 *    — خروجی چند فرمتی (24h / 12h / 12hNS / Float)
 *    — تشخیص خودکار منطقه زمانی و DST
 *    — سازگار با PHP ≥ 8.1
 *
 *  نسخه    : 2.0.0
 *  مجوز    : GNU LGPL v3.0
 *
 *  نمونه استفاده:
 *
 *    $calc  = new PrayerTimesCalculator(method: 'Tehran');
 *    $times = $calc->getTimes('2026-02-22', [35.6892, 51.3890, 1191], 3.5);
 *    print_r($times);
 *
 * ════════════════════════════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════════════════════════
//  کلاس کمکی ریاضیات درجه‌ای (Degree-based Math)
// ═══════════════════════════════════════════════════════════════════════

final class DMath
{
    /** درجه → رادیان */
    public static function dtr(float $d): float
    {
        return ($d * M_PI) / 180.0;
    }

    /** رادیان → درجه */
    public static function rtd(float $r): float
    {
        return ($r * 180.0) / M_PI;
    }

    /** سینوس (ورودی درجه) */
    public static function sin(float $d): float
    {
        return sin(self::dtr($d));
    }

    /** کسینوس (ورودی درجه) */
    public static function cos(float $d): float
    {
        return cos(self::dtr($d));
    }

    /** تانژانت (ورودی درجه) */
    public static function tan(float $d): float
    {
        return tan(self::dtr($d));
    }

    /** آرک‌سینوس (خروجی درجه) — با clamping */
    public static function arcsin(float $d): float
    {
        return self::rtd(asin(max(-1.0, min(1.0, $d))));
    }

    /** آرک‌کسینوس (خروجی درجه) — با clamping */
    public static function arccos(float $d): float
    {
        return self::rtd(acos(max(-1.0, min(1.0, $d))));
    }

    /** آرک‌تانژانت (خروجی درجه) */
    public static function arctan(float $d): float
    {
        return self::rtd(atan($d));
    }

    /** آرک‌کتانژانت (خروجی درجه) */
    public static function arccot(float $x): float
    {
        return self::rtd(atan(1.0 / $x));
    }

    /** آرک‌تانژانت دو‌متغیره (خروجی درجه) */
    public static function arctan2(float $y, float $x): float
    {
        return self::rtd(atan2($y, $x));
    }

    /** نرمال‌سازی زاویه در [0, 360) */
    public static function fixAngle(float $a): float
    {
        return self::fix($a, 360.0);
    }

    /** نرمال‌سازی ساعت در [0, 24) */
    public static function fixHour(float $a): float
    {
        return self::fix($a, 24.0);
    }

    /** نرمال‌سازی عمومی در [0, b) */
    public static function fix(float $a, float $b): float
    {
        $a -= $b * floor($a / $b);
        return ($a < 0) ? $a + $b : $a;
    }
}


// ═══════════════════════════════════════════════════════════════════════
//  کلاس اصلی محاسبه اوقات شرعی
// ═══════════════════════════════════════════════════════════════════════

class PrayerTimesCalculator
{
    // ─────────────────────────────────────────────────────────────────
    //  نام اوقات
    // ─────────────────────────────────────────────────────────────────

    /** @var array<string, array{en: string, fa: string}> */
    private const TIME_LABELS = [
        'imsak'    => ['en' => 'Imsak',    'fa' => 'امساک'],
        'fajr'     => ['en' => 'Fajr',     'fa' => 'اذان صبح (فجر)'],
        'sunrise'  => ['en' => 'Sunrise',  'fa' => 'طلوع آفتاب'],
        'dhuhr'    => ['en' => 'Dhuhr',    'fa' => 'ظهر شرعی'],
        'asr'      => ['en' => 'Asr',      'fa' => 'اذان عصر'],
        'sunset'   => ['en' => 'Sunset',   'fa' => 'غروب آفتاب'],
        'maghrib'  => ['en' => 'Maghrib',  'fa' => 'اذان مغرب'],
        'isha'     => ['en' => 'Isha',     'fa' => 'اذان عشاء'],
        'midnight' => ['en' => 'Midnight', 'fa' => 'نیمه‌شب شرعی'],
    ];

    // ─────────────────────────────────────────────────────────────────
    //  روش‌های محاسبه
    // ─────────────────────────────────────────────────────────────────

    /**
     * هر روش شامل:
     *   fajr     → زاویه فجر (درجه)
     *   isha     → زاویه عشاء (درجه) یا 'XX min' (دقیقه پس از مغرب)
     *   maghrib  → زاویه مغرب (درجه) یا '0 min' (= غروب) — پیش‌فرض: '0 min'
     *   midnight → 'Standard' (غروب تا طلوع) یا 'Jafari' (غروب تا فجر) — پیش‌فرض: 'Standard'
     *   asr      → 'Standard' (فقهای سه‌گانه) یا 'Hanafi' — پیش‌فرض: 'Standard'
     */
    private const METHODS = [
        'Tehran' => [
            'name'     => 'موسسه ژئوفیزیک دانشگاه تهران',
            'name_en'  => 'Institute of Geophysics, University of Tehran',
            'params'   => [
                'fajr'     => 17.7,
                'isha'     => 14,
                'maghrib'  => 4.5,
                'midnight' => 'Jafari',
            ],
        ],
        'Jafari' => [
            'name'     => 'موسسه لواء قم (جعفری)',
            'name_en'  => 'Shia Ithna-Ashari, Leva Institute, Qum',
            'params'   => [
                'fajr'     => 16,
                'isha'     => 14,
                'maghrib'  => 4,
                'midnight' => 'Jafari',
            ],
        ],
        'MWL' => [
            'name'     => 'اتحادیه جهانی مسلمانان',
            'name_en'  => 'Muslim World League',
            'params'   => [
                'fajr' => 18,
                'isha' => 17,
            ],
        ],
        'ISNA' => [
            'name'     => 'جامعه اسلامی آمریکای شمالی',
            'name_en'  => 'Islamic Society of North America',
            'params'   => [
                'fajr' => 15,
                'isha' => 15,
            ],
        ],
        'Egypt' => [
            'name'     => 'اداره نقشه‌برداری مصر',
            'name_en'  => 'Egyptian General Authority of Survey',
            'params'   => [
                'fajr' => 19.5,
                'isha' => 17.5,
            ],
        ],
        'Makkah' => [
            'name'     => 'دانشگاه ام‌القرای مکه',
            'name_en'  => 'Umm Al-Qura University, Makkah',
            'params'   => [
                'fajr' => 18.5,
                'isha' => '90 min',  // ۹۰ دقیقه بعد از مغرب (رمضان: ۱۲۰)
            ],
        ],
        'Karachi' => [
            'name'     => 'دانشگاه علوم اسلامی کراچی',
            'name_en'  => 'University of Islamic Sciences, Karachi',
            'params'   => [
                'fajr' => 18,
                'isha' => 18,
            ],
        ],
        'France' => [
            'name'     => 'مسلمانان فرانسه (UOIF)',
            'name_en'  => 'Union of Islamic Organizations of France',
            'params'   => [
                'fajr' => 12,
                'isha' => 12,
            ],
        ],
        'Russia' => [
            'name'     => 'اداره مسلمانان روسیه',
            'name_en'  => 'Spiritual Administration of Muslims of Russia',
            'params'   => [
                'fajr' => 16,
                'isha' => 15,
            ],
        ],
        'Singapore' => [
            'name'     => 'شورای دینی سنگاپور (MUIS)',
            'name_en'  => 'Majlis Ugama Islam Singapura',
            'params'   => [
                'fajr' => 20,
                'isha' => 18,
            ],
        ],
        'Turkey' => [
            'name'     => 'دیانت ترکیه',
            'name_en'  => 'Diyanet İşleri Başkanlığı, Turkey',
            'params'   => [
                'fajr'    => 18,
                'isha'    => 17,
                'maghrib' => 2.3,
            ],
        ],
    ];

    /** پارامترهای پیش‌فرض (اگر در روش تعریف نشده باشند) */
    private const DEFAULT_PARAMS = [
        'imsak'    => '10 min',   // ۱۰ دقیقه قبل از فجر
        'maghrib'  => '0 min',    // غروب استاندارد
        'midnight' => 'Standard', // نصف‌الیل سنی
        'asr'      => 'Standard', // شافعی/مالکی/حنبلی/جعفری
    ];

    // ─────────────────────────────────────────────────────────────────
    //  ویژگی‌های شیء
    // ─────────────────────────────────────────────────────────────────

    private string $calcMethod;

    /** تنظیمات فعال محاسبه */
    private array $setting = [
        'imsak'    => '10 min',
        'fajr'     => 0,
        'sunrise'  => 0,
        'dhuhr'    => 0,
        'asr'      => 'Standard',
        'sunset'   => 0,
        'maghrib'  => '0 min',
        'isha'     => 0,
        'midnight' => 'Standard',
        'highLats' => 'NightMiddle',
    ];

    /** فرمت زمان — '24h' | '12h' | '12hNS' | 'Float' */
    private string $timeFormat = '24h';

    /** پسوندهای AM/PM */
    private array $timeSuffixes = ['am', 'pm'];

    /** رشته نمایشی برای زمان نامعتبر */
    private string $invalidTime = '---';

    /** تعداد تکرار محاسبات (هرچه بیشتر، دقیق‌تر — ۱ معمولاً کافی است) */
    private int $numIterations = 1;

    /** آفست‌های دقیقه‌ای کاربر (tune) */
    private array $offset = [];

    // ─── مختصات داخلی ───

    private float $lat      = 0.0;
    private float $lng      = 0.0;
    private float $elv      = 0.0;
    private float $timeZone = 0.0;
    private float $jDate    = 0.0;


    // ═══════════════════════════════════════════════════════════════════
    //  سازنده
    // ═══════════════════════════════════════════════════════════════════

    /**
     * @param string $method        روش محاسبه
     * @param string $highLatMethod روش عرض‌های بالا: NightMiddle | AngleBased | OneSeventh | None
     * @param int    $iterations    تعداد تکرار محاسبات (پیش‌فرض: ۱)
     */
    public function __construct(
        string $method        = 'Tehran',
        string $highLatMethod = 'NightMiddle',
        int    $iterations    = 1,
    ) {
        $this->numIterations = max(1, $iterations);

        // تنظیم روش محاسبه
        $this->setMethod(
            array_key_exists($method, self::METHODS) ? $method : 'Tehran'
        );

        // روش عرض‌های بالا
        $validHL = ['NightMiddle', 'AngleBased', 'OneSeventh', 'None'];
        $this->setting['highLats'] = in_array($highLatMethod, $validHL, true)
            ? $highLatMethod
            : 'NightMiddle';
    }


    // ═══════════════════════════════════════════════════════════════════
    //  رابط عمومی (Public API)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * تنظیم روش محاسبه
     */
    public function setMethod(string $method): static
    {
        if (isset(self::METHODS[$method])) {
            $this->calcMethod = $method;

            // ابتدا پیش‌فرض‌ها، سپس پارامترهای روش
            $params = array_merge(
                self::DEFAULT_PARAMS,
                self::METHODS[$method]['params']
            );
            $this->adjust($params);
        }
        return $this;
    }

    /**
     * تنظیم دستی پارامترها
     *
     * @example $calc->adjust(['fajr' => 17.7, 'isha' => 14, 'asr' => 'Hanafi']);
     */
    public function adjust(array $params): static
    {
        foreach ($params as $key => $val) {
            $this->setting[$key] = $val;
        }
        return $this;
    }

    /**
     * اضافه کردن آفست دقیقه‌ای به هر وقت
     *
     * @example $calc->tune(['fajr' => +2, 'dhuhr' => -1, 'maghrib' => +3]);
     */
    public function tune(array $offsets): static
    {
        foreach ($offsets as $key => $val) {
            $this->offset[$key] = (float) $val;
        }
        return $this;
    }

    // ─── Getters ───

    public function getMethod(): string  { return $this->calcMethod; }
    public function getSetting(): array  { return $this->setting; }
    public function getOffsets(): array   { return $this->offset; }

    /**
     * لیست تمام روش‌های محاسبه
     *
     * @return array<string, array{name: string, name_en: string}>
     */
    public static function getMethods(): array
    {
        $result = [];
        foreach (self::METHODS as $key => $m) {
            $result[$key] = [
                'name'    => $m['name'],
                'name_en' => $m['name_en'],
            ];
        }
        return $result;
    }


    // ═══════════════════════════════════════════════════════════════════
    //  متد اصلی محاسبه
    // ═══════════════════════════════════════════════════════════════════

    /**
     * محاسبه اوقات شرعی
     *
     * @param  array|string|\DateTimeInterface $date     تاریخ: [Y,m,d] یا 'Y-m-d' یا DateTime
     * @param  array                          $coords   [lat, lng] یا [lat, lng, elevation_m]
     * @param  float|string|null              $timezone آفست UTC (ساعت) یا 'auto'
     * @param  int|string|null                $dst      ساعت تابستانی (0|1) یا 'auto'
     * @param  string                         $format   '24h' | '12h' | '12hNS' | 'Float'
     *
     * @return array{
     *     method: string,
     *     method_en: string,
     *     date: string,
     *     location: array{lat: float, lng: float, elevation: float, timezone: float},
     *     times: array<string, array{label: string, label_en: string, time: string}>
     * }
     */
    public function getTimes(
        array|string|\DateTimeInterface $date,
        array                          $coords,
        float|string|null              $timezone = null,
        int|string|null                $dst      = null,
        string                         $format   = '24h',
    ): array {
        // ── مختصات ──
        $this->lat = (float) $coords[0];
        $this->lng = (float) $coords[1];
        $this->elv = isset($coords[2]) ? max(0.0, (float) $coords[2]) : 0.0;
        $this->timeFormat = $format;

        // ── تبدیل تاریخ ──
        $dateArray = $this->normalizeDate($date);

        // ── منطقه زمانی ──
        if ($timezone === null || $timezone === 'auto') {
            $timezone = $this->getTimeZone($dateArray);
        }
        if ($dst === null || $dst === 'auto') {
            $dst = $this->getDst($dateArray);
        }
        $this->timeZone = (float) $timezone + ($dst ? 1.0 : 0.0);

        // ── تاریخ ژولیوسی ──
        $this->jDate = $this->julian($dateArray[0], $dateArray[1], $dateArray[2])
                       - $this->lng / (15.0 * 24.0);

        // ── محاسبه ──
        $rawTimes = $this->computeTimes();

        // ── ساخت خروجی ساختاریافته ──
        $dateStr = sprintf('%04d-%02d-%02d', $dateArray[0], $dateArray[1], $dateArray[2]);
        $cfg     = self::METHODS[$this->calcMethod];

        $structured = [];
        foreach (self::TIME_LABELS as $key => $labels) {
            $structured[$key] = [
                'label'    => $labels['fa'],
                'label_en' => $labels['en'],
                'time'     => $rawTimes[$key] ?? $this->invalidTime,
            ];
        }

        return [
            'method'    => $cfg['name'],
            'method_en' => $cfg['name_en'],
            'date'      => $dateStr,
            'location'  => [
                'lat'       => $this->lat,
                'lng'       => $this->lng,
                'elevation' => $this->elv,
                'timezone'  => $this->timeZone,
            ],
            'times' => $structured,
        ];
    }

    /**
     * خروجی ساده — فقط آرایه نام => زمان
     *
     * @return array<string, string>
     */
    public function getTimesSimple(
        array|string|\DateTimeInterface $date,
        array                          $coords,
        float|string|null              $timezone = null,
        int|string|null                $dst      = null,
        string                         $format   = '24h',
    ): array {
        $full = $this->getTimes($date, $coords, $timezone, $dst, $format);
        $result = [];
        foreach ($full['times'] as $key => $item) {
            $result[$key] = $item['time'];
        }
        return $result;
    }


    // ═══════════════════════════════════════════════════════════════════
    //  محاسبات نجومی (هسته)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * موقعیت خورشید: میل (declination) و معادله زمان (equation of time)
     * الگوریتم: U.S. Naval Observatory — دقت ~۱ دقیقه قوسی
     *
     * @return array{declination: float, equation: float}
     */
    private function sunPosition(float $jd): array
    {
        $D = $jd - 2451545.0;
        $g = DMath::fixAngle(357.529 + 0.98560028 * $D);
        $q = DMath::fixAngle(280.459 + 0.98564736 * $D);
        $L = DMath::fixAngle($q + 1.915 * DMath::sin($g) + 0.020 * DMath::sin(2.0 * $g));

        $e    = 23.439 - 0.00000036 * $D;
        $RA   = DMath::arctan2(DMath::cos($e) * DMath::sin($L), DMath::cos($L)) / 15.0;
        $RA   = DMath::fixHour($RA);
        $eqt  = $q / 15.0 - $RA;
        $decl = DMath::arcsin(DMath::sin($e) * DMath::sin($L));

        return ['declination' => $decl, 'equation' => $eqt];
    }

    /**
     * ظهر شرعی (وسط‌الروز)
     */
    private function midDay(float $time): float
    {
        $eqt = $this->sunPosition($this->jDate + $time)['equation'];
        return DMath::fixHour(12.0 - $eqt);
    }

    /**
     * زمان رسیدن خورشید به زاویه مشخص زیر افق
     *
     * @param string $direction 'ccw' = قبل از ظهر (فجر/طلوع) | 'cw' = بعد از ظهر (مغرب/عشاء)
     */
    private function sunAngleTime(float $angle, float $time, string $direction = 'cw'): float
    {
        $decl = $this->sunPosition($this->jDate + $time)['declination'];
        $noon = $this->midDay($time);

        $cosVal = (-DMath::sin($angle) - DMath::sin($decl) * DMath::sin($this->lat))
                  / (DMath::cos($decl) * DMath::cos($this->lat));

        // clamping برای جلوگیری از NaN — در عرض‌های بالا ممکن است خارج [-1,1] شود
        $cosVal = max(-1.0, min(1.0, $cosVal));

        $t = (1.0 / 15.0) * DMath::arccos($cosVal);

        return $noon + ($direction === 'ccw' ? -$t : $t);
    }

    /**
     * محاسبه زمان عصر
     *
     * @param float $factor ضریب سایه (1 = استاندارد، 2 = حنفی)
     */
    private function asrTime(float $factor, float $time): float
    {
        $decl  = $this->sunPosition($this->jDate + $time)['declination'];
        $angle = -DMath::arccot($factor + DMath::tan(abs($this->lat - $decl)));
        return $this->sunAngleTime($angle, $time);
    }

    /**
     * زاویه افق واقعی با احتساب ارتفاع و کسر جوّی
     */
    private function riseSetAngle(float $elevation): float
    {
        return 0.833 + 0.0347 * sqrt(max(0.0, $elevation));
    }


    // ═══════════════════════════════════════════════════════════════════
    //  خط لوله محاسبات
    // ═══════════════════════════════════════════════════════════════════

    /**
     * محاسبه تمام اوقات — تکراری → تنظیم → نیمه‌شب → tune → فرمت
     */
    private function computeTimes(): array
    {
        // حدس اولیه (ساعت)
        $times = [
            'imsak'   => 5.0,
            'fajr'    => 5.0,
            'sunrise' => 6.0,
            'dhuhr'   => 12.0,
            'asr'     => 13.0,
            'sunset'  => 18.0,
            'maghrib' => 18.0,
            'isha'    => 18.0,
        ];

        // تکرار برای همگرایی
        for ($i = 1; $i <= $this->numIterations; $i++) {
            $times = $this->computePrayerTimes($times);
        }

        // تنظیمات (timezone + highLat + minute-based offsets)
        $times = $this->adjustTimes($times);

        // نیمه‌شب شرعی
        $times['midnight'] = match ($this->setting['midnight']) {
            'Jafari' => $times['sunset'] + $this->timeDiff($times['sunset'], $times['fajr']) / 2.0,
            default  => $times['sunset'] + $this->timeDiff($times['sunset'], $times['sunrise']) / 2.0,
        };

        // آفست‌های کاربر
        $times = $this->tuneTimes($times);

        // فرمت‌دهی
        return $this->modifyFormats($times);
    }

    /**
     * یک تکرار محاسبه تمام اوقات
     */
    private function computePrayerTimes(array $times): array
    {
        $times  = $this->dayPortion($times);
        $params = $this->setting;

        return [
            'imsak'   => $this->sunAngleTime($this->evalParam($params['imsak']),   $times['imsak'],   'ccw'),
            'fajr'    => $this->sunAngleTime($this->evalParam($params['fajr']),     $times['fajr'],    'ccw'),
            'sunrise' => $this->sunAngleTime($this->riseSetAngle($this->elv),       $times['sunrise'], 'ccw'),
            'dhuhr'   => $this->midDay($times['dhuhr']),
            'asr'     => $this->asrTime($this->asrFactor($params['asr']),           $times['asr']),
            'sunset'  => $this->sunAngleTime($this->riseSetAngle($this->elv),       $times['sunset']),
            'maghrib' => $this->sunAngleTime($this->evalParam($params['maghrib']),  $times['maghrib']),
            'isha'    => $this->sunAngleTime($this->evalParam($params['isha']),     $times['isha']),
        ];
    }

    /**
     * اعمال منطقه زمانی، اصلاح عرض بالا، و آفست‌های دقیقه‌ای
     */
    private function adjustTimes(array $times): array
    {
        $params = $this->setting;

        // شیفت به منطقه زمانی محلی
        foreach ($times as &$t) {
            $t += $this->timeZone - $this->lng / 15.0;
        }
        unset($t);

        // اصلاح عرض‌های جغرافیایی بالا
        if (!empty($params['highLats']) && $params['highLats'] !== 'None') {
            $times = $this->adjustHighLats($times);
        }

        // آفست‌های دقیقه‌ای (imsak, maghrib, isha)
        if ($this->isMin($params['imsak'])) {
            $times['imsak'] = $times['fajr'] - $this->evalParam($params['imsak']) / 60.0;
        }
        if ($this->isMin($params['maghrib'])) {
            $times['maghrib'] = $times['sunset'] + $this->evalParam($params['maghrib']) / 60.0;
        }
        if ($this->isMin($params['isha'])) {
            $times['isha'] = $times['maghrib'] + $this->evalParam($params['isha']) / 60.0;
        }

        // آفست ظهر
        $times['dhuhr'] += $this->evalParam($params['dhuhr']) / 60.0;

        return $times;
    }


    // ═══════════════════════════════════════════════════════════════════
    //  اصلاح عرض‌های جغرافیایی بالا
    // ═══════════════════════════════════════════════════════════════════

    /**
     * اصلاح اوقات برای مناطقی که شفق از بین نمی‌رود
     */
    private function adjustHighLats(array $times): array
    {
        $params    = $this->setting;
        $nightTime = $this->timeDiff($times['sunset'], $times['sunrise']);

        $times['imsak']   = $this->adjustHLTime($times['imsak'],   $times['sunrise'], $this->evalParam($params['imsak']),   $nightTime, 'ccw');
        $times['fajr']    = $this->adjustHLTime($times['fajr'],    $times['sunrise'], $this->evalParam($params['fajr']),    $nightTime, 'ccw');
        $times['isha']    = $this->adjustHLTime($times['isha'],    $times['sunset'],  $this->evalParam($params['isha']),    $nightTime, 'cw');
        $times['maghrib'] = $this->adjustHLTime($times['maghrib'], $times['sunset'],  $this->evalParam($params['maghrib']), $nightTime, 'cw');

        return $times;
    }

    /**
     * اصلاح یک وقت خاص برای عرض جغرافیایی بالا
     */
    private function adjustHLTime(
        float  $time,
        float  $base,
        float  $angle,
        float  $night,
        string $direction = 'cw',
    ): float {
        $portion  = $this->nightPortion($angle, $night);
        $timeDiff = ($direction === 'ccw')
            ? $this->timeDiff($time, $base)
            : $this->timeDiff($base, $time);

        if (is_nan($time) || is_infinite($time) || $timeDiff > $portion) {
            $time = $base + ($direction === 'ccw' ? -$portion : $portion);
        }
        return $time;
    }

    /**
     * سهم شب بر اساس روش عرض بالا
     */
    private function nightPortion(float $angle, float $night): float
    {
        $portion = match ($this->setting['highLats'] ?? 'NightMiddle') {
            'AngleBased' => (1.0 / 60.0) * $angle,
            'OneSeventh' => 1.0 / 7.0,
            default      => 0.5,  // NightMiddle
        };
        return $portion * $night;
    }


    // ═══════════════════════════════════════════════════════════════════
    //  توابع کمکی
    // ═══════════════════════════════════════════════════════════════════

    /**
     * ضریب سایه عصر
     */
    private function asrFactor(mixed $asrParam): float
    {
        return match ($asrParam) {
            'Standard' => 1.0,
            'Hanafi'   => 2.0,
            default    => (float) $this->evalParam($asrParam),
        };
    }

    /**
     * نرمال‌سازی تاریخ به [year, month, day]
     */
    private function normalizeDate(array|string|\DateTimeInterface $date): array
    {
        if ($date instanceof \DateTimeInterface) {
            return [
                (int) $date->format('Y'),
                (int) $date->format('n'),
                (int) $date->format('j'),
            ];
        }

        if (is_string($date)) {
            if (empty($date)) {
                return [(int) date('Y'), (int) date('n'), (int) date('j')];
            }
            $ts = strtotime($date);
            return [
                (int) date('Y', $ts),
                (int) date('n', $ts),
                (int) date('j', $ts),
            ];
        }

        // آرایه [year, month, day]
        return [
            (int) ($date[0] ?? date('Y')),
            (int) ($date[1] ?? date('n')),
            (int) ($date[2] ?? date('j')),
        ];
    }

    /**
     * تبدیل تاریخ گرگوری به عدد روز ژولیوسی (JD)
     * مرجع: Astronomical Algorithms — Jean Meeus
     */
    private function julian(int $year, int $month, int $day): float
    {
        if ($month <= 2) {
            $year  -= 1;
            $month += 12;
        }
        $A  = (int) floor($year / 100);
        $B  = 2 - $A + (int) floor($A / 4);
        return (float) (
            (int) floor(365.25 * ($year + 4716))
            + (int) floor(30.6001 * ($month + 1))
            + $day + $B - 1524.5
        );
    }

    /**
     * تبدیل ساعات به کسر روز (÷24) برای محاسبات تکراری
     */
    private function dayPortion(array $times): array
    {
        foreach ($times as &$t) {
            $t /= 24.0;
        }
        unset($t);
        return $times;
    }

    /**
     * اعمال آفست‌های tune
     */
    private function tuneTimes(array $times): array
    {
        foreach ($times as $key => &$t) {
            $t += ($this->offset[$key] ?? 0.0) / 60.0;
        }
        unset($t);
        return $times;
    }

    /**
     * فرمت‌دهی تمام اوقات
     */
    private function modifyFormats(array $times): array
    {
        foreach ($times as &$t) {
            $t = $this->formatTime((float) $t, $this->timeFormat);
        }
        unset($t);
        return $times;
    }

    /**
     * تبدیل عدد اعشاری ساعت به رشته زمان
     */
    private function formatTime(float $time, string $format): string
    {
        if (is_nan($time) || is_infinite($time)) {
            return $this->invalidTime;
        }

        if ($format === 'Float') {
            return (string) round($time, 6);
        }

        // گرد کردن به نزدیک‌ترین دقیقه
        $time    = DMath::fixHour($time + 0.5 / 60.0);
        $hours   = (int) floor($time);
        $minutes = (int) floor(($time - $hours) * 60.0);

        $suffix = '';
        if ($format === '12h') {
            $suffix = ' ' . $this->timeSuffixes[$hours < 12 ? 0 : 1];
        }

        $hourStr = match ($format) {
            '24h'   => str_pad((string) $hours, 2, '0', STR_PAD_LEFT),
            default => (string) ((($hours + 12 - 1) % 12) + 1),
        };

        return $hourStr . ':' . str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) . $suffix;
    }

    /**
     * استخراج مقدار عددی از رشته پارامتر
     *   '90 min' → 90.0 | '17.7' → 17.7 | 18 → 18.0
     */
    private function evalParam(mixed $str): float
    {
        if (is_numeric($str)) {
            return (float) $str;
        }
        preg_match('/^[+-]?[0-9]*\.?[0-9]+/', trim((string) $str), $m);
        return isset($m[0]) ? (float) $m[0] : 0.0;
    }

    /**
     * بررسی آیا پارامتر بر حسب دقیقه است ('90 min')
     */
    private function isMin(mixed $arg): bool
    {
        return str_contains((string) $arg, 'min');
    }

    /**
     * اختلاف مثبت دو زمان (با مدیریت عبور از نیمه‌شب)
     */
    private function timeDiff(float $time1, float $time2): float
    {
        return DMath::fixHour($time2 - $time1);
    }


    // ═══════════════════════════════════════════════════════════════════
    //  تشخیص خودکار منطقه زمانی
    // ═══════════════════════════════════════════════════════════════════

    /**
     * آفست UTC استاندارد (بدون DST) بر حسب ساعت
     */
    public function getTimeZone(array $date): float
    {
        $year = (int) $date[0];
        $t1   = $this->gmtOffset([$year, 1, 1]);
        $t2   = $this->gmtOffset([$year, 7, 1]);
        return min($t1, $t2);
    }

    /**
     * آیا ساعت تابستانی فعال است؟ (0 یا 1)
     */
    public function getDst(array $date): int
    {
        return (int) ($this->gmtOffset($date) !== $this->getTimeZone($date));
    }

    /**
     * آفست GMT بر حسب ساعت برای تاریخ مشخص (با استفاده از PHP timezone)
     */
    private function gmtOffset(array $date): float
    {
        try {
            $tz = new \DateTimeZone(date_default_timezone_get());
            $dt = new \DateTime(
                sprintf('%04d-%02d-%02d 12:00:00', (int) $date[0], (int) $date[1], (int) $date[2]),
                $tz,
            );
            return $tz->getOffset($dt) / 3600.0;
        } catch (\Exception) {
            return 0.0;
        }
    }


    // ═══════════════════════════════════════════════════════════════════
    //  متد سازگار با فایل اول (backward compatibility)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * محاسبه ساده — سازگار با رابط فایل اول
     *
     * @param  float  $lat       عرض جغرافیایی
     * @param  float  $lng       طول جغرافیایی
     * @param  float  $timezone  آفست UTC
     * @param  string $date      تاریخ (Y-m-d)
     * @param  int    $elevation ارتفاع (متر)
     *
     * @return array  خروجی ساختاریافته
     */
    public function calculate(
        float  $lat,
        float  $lng,
        float  $timezone,
        string $date      = '',
        int    $elevation = 0,
    ): array {
        if (empty($date)) {
            $date = date('Y-m-d');
        }

        return $this->getTimes(
            date:     $date,
            coords:   [$lat, $lng, $elevation],
            timezone: $timezone,
            dst:      0,
            format:   '24h',
        );
    }
}
