<?php

namespace App\Handler;

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\CityRepository;
use App\Repository\EventRepository;
use App\Repository\LocationRepository;
use App\Service\CalendarService;
use App\Service\DateConvertService;
use App\Service\DateTimeService;
use App\Service\NowruzService;
use App\Service\PrayerTimeService;

class InlineHandler
{
    public function __construct(
        private Api                $api,
        private DateTimeService    $dateTime,
        private EventRepository    $eventRepo,
        private CityRepository     $cityRepo,
        private LocationRepository $locationRepo,
        private PrayerTimeService  $prayerTime,
        private CalendarService    $calendar,
        private NowruzService      $nowruz,
        private DateConvertService $dateConvert
    ) {}

    public function handle(Update $update): void
    {
        $queryId = $update->getInlineQueryId();
        if (!$queryId) {
            return;
        }

        $q       = $this->normalize($update->getInlineQuery());
        $userId  = $update->getInlineUserId();
        $results = $this->buildResults($q, $userId);

        if ($results === []) {
            $results[] = $this->article(
                'help',
                'راهنما',
                'امروز، اوقات کاشان، 1405/1/1، تقویم، نوروز',
                "نمونه:\n"
                . "• <code>امروز</code>\n"
                . "• <code>اوقات کاشان</code>\n"
                . "• <code>1405/01/01</code>\n"
                . "• <code>تقویم</code>\n"
                . "• <code>نوروز</code> / <code>نوروز 1408</code>"
            );
        }

        $this->api->answerInlineQuery($queryId, $results);
    }

    private function buildResults(string $q, ?int $userId): array
    {
        if ($q === '' || $q === 'امروز' || $q === 'today') {
            $results = [$this->todayArticle()];
            if ($q === '') {
                $prayer = $this->savedPrayerArticle($userId);
                if ($prayer) {
                    $results[] = $prayer;
                }
                $results[] = $this->calendarArticle();
                $results[] = $this->nowruzArticle('');
            }
            return $results;
        }

        if ($q === 'تقویم' || $q === 'cal') {
            return [$this->calendarArticle()];
        }

        if ($q === 'نوروز' || $q === 'nowruz' || preg_match('/^(?:نوروز|nowruz)\s+(.+)$/u', $q, $m)) {
            $arg = isset($m[1]) ? trim($m[1]) : '';
            return [$this->nowruzArticle($arg)];
        }

        if (preg_match('/^(?:اوقات|ow)\s+(.+)$/u', $q, $m)) {
            return $this->prayerArticles(trim($m[1]));
        }

        if ($q === 'اوقات' || $q === 'ow') {
            $saved = $this->savedPrayerArticle($userId);
            return $saved ? [$saved] : [$this->article(
                'ow-need-city',
                'اوقات شرعی',
                'نام شهر را بنویس: اوقات کاشان',
                "📍 نام شهر را بعد از اوقات بنویس.\nمثلاً <code>اوقات کاشان</code>"
            )];
        }

        $converted = $this->dateConvert->tryConvert($q);
        if ($converted !== null) {
            return [$this->article('conv', 'تبدیل تاریخ', $q, $converted)];
        }

        return $this->prayerArticles($q);
    }

    private function todayArticle(): array
    {
        $now = $this->dateTime->getNow();
        $msg  = "⏰ ساعت: <code>{$now['time']}</code>\n\n";
        $msg .= "📅 <b>شمسی:</b>  {$now['formatted']}\n";
        $msg .= "📆 <b>میلادی:</b> {$now['g_day']} {$now['g_month_name']} {$now['g_year']}\n";
        if ($now['h_year'] > 0) {
            $msg .= "🌙 <b>قمری:</b>  {$now['h_day']} {$now['h_month_name']} {$now['h_year']}\n";
        }
        $msg .= str_repeat('─', 18) . "\n";

        $events = $this->eventRepo->getTodayEvents(
            $now['j_month'], $now['j_day'],
            $now['h_month'], $now['h_day']
        );
        if (empty($events)) {
            $msg .= "✅ امروز مناسبت خاصی نیست.";
        } else {
            $msg .= "📌 <b>مناسبت‌های امروز:</b>\n";
            foreach ($events as $e) {
                $title = htmlspecialchars($e['title'], ENT_QUOTES, 'UTF-8');
                $icon  = $e['holiday'] ? '🔴' : '▫️';
                $msg  .= "{$icon} {$title}\n";
            }
        }

        return $this->article('today', 'امروز', $now['formatted'], $msg);
    }

    private function calendarArticle(): array
    {
        $view = $this->calendar->renderCurrentMonth();
        return $this->article('cal', 'تقویم شمسی', 'تقویم ماه جاری', $view['text'], $view['reply_markup']);
    }

    private function nowruzArticle(string $arg): array
    {
        $text  = $this->nowruz->getMessage($arg);
        $title = $arg === '' ? 'نوروز' : "نوروز {$arg}";
        return $this->article('nowruz-' . md5($arg), $title, 'لحظه تحویل سال', $text);
    }

    private function savedPrayerArticle(?int $userId): ?array
    {
        if (!$userId) {
            return null;
        }
        $saved = $this->locationRepo->findByUserId($userId);
        if ($saved === null) {
            return null;
        }
        $city  = $this->locationRepo->toCityPayload($saved);
        $label = $this->locationRepo->label($saved);
        return $this->article(
            'ow-saved',
            "اوقات شرعی {$label}",
            'شهر ذخیره‌شده',
            $this->prayerTime->getForCity($city)
        );
    }

    private function prayerArticles(string $cityName): array
    {
        $matches = $this->cityRepo->findAllByExactName($cityName);
        if (count($matches) === 0) {
            $cap = $this->cityRepo->findCapitalByProvinceName($cityName);
            if ($cap) {
                $matches = [$cap];
            } else {
                $matches = $this->cityRepo->searchByName($cityName);
            }
        }
        if ($matches === []) {
            $safe = htmlspecialchars($cityName, ENT_QUOTES, 'UTF-8');
            return [$this->article('ow-miss', 'شهر پیدا نشد', $cityName, "❌ شهر <b>{$safe}</b> پیدا نشد.")];
        }

        $out = [];
        foreach (array_slice($matches, 0, 5) as $i => $city) {
            $title = $city['name'] . (!empty($city['province_name']) ? " — {$city['province_name']}" : '');
            $out[] = $this->article(
                'ow-' . ($city['id'] ?? $i),
                "اوقات شرعی {$title}",
                $title,
                $this->prayerTime->getForCity($city)
            );
        }
        return $out;
    }

    private function article(string $id, string $title, string $description, string $text, ?array $markup = null): array
    {
        $item = [
            'type'                  => 'article',
            'id'                    => substr($id, 0, 64),
            'title'                 => $title,
            'description'           => $description,
            'input_message_content' => [
                'message_text' => $text,
                'parse_mode'   => 'HTML',
            ],
        ];
        if ($markup) {
            $item['reply_markup'] = $markup;
        }
        return $item;
    }

    private function normalize(string $text): string
    {
        $text = strtr(trim($text), [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4',
            '۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        ]);
        return trim($text);
    }
}
