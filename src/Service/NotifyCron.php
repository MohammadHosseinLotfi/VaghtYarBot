<?php

namespace App\Service;

use App\Repository\LocationRepository;
use App\Repository\NotifyRepository;
use App\Telegram\Api;

class NotifyCron
{
    public function __construct(
        private NotifyRepository  $notifyRepo,
        private PrayerTimeService $prayerTime,
        private Api               $api
    ) {}

    public function run(): int
    {
        $this->notifyRepo->purgeOldSent();

        $now  = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Tehran'));
        $ymd  = $now->format('Y-m-d');
        $mins = [
            $now->format('H:i'),
            $now->modify('-1 minute')->format('H:i'),
        ];

        $users = $this->notifyRepo->listEnabledWithLocation();
        if ($users === []) {
            return 0;
        }

        $groups = [];
        foreach ($users as $row) {
            $key = !empty($row['city_id'])
                ? 'c:' . (int) $row['city_id']
                : 'g:' . round((float) $row['lat'], 4) . ':' . round((float) $row['lng'], 4);
            $groups[$key][] = $row;
        }

        $sent = 0;
        foreach ($groups as $rows) {
            $sample = $rows[0];
            $times  = $this->prayerTime->computeTimes((float) $sample['lat'], (float) $sample['lng']);
            $label  = !empty($sample['city_id']) && !empty($sample['city_name'])
                ? (string) $sample['city_name']
                : LocationRepository::FALLBACK_NAME;

            foreach ($rows as $row) {
                $sent += $this->notifyUser($row, $times, $mins, $ymd, $label);
            }
        }

        return $sent;
    }

    private function notifyUser(array $row, array $times, array $mins, string $ymd, string $label): int
    {
        $sent = 0;
        $uid  = (int) $row['user_id'];

        foreach (NotifyRepository::PRAYERS as $key => $prayerLabel) {
            if (empty($row[$key])) {
                continue;
            }
            $time = $times[$key] ?? null;
            if ($time === null || $time === '---') {
                continue;
            }
            $hhmm = substr((string) $time, 0, 5);
            if (!in_array($hhmm, $mins, true)) {
                continue;
            }
            if (!$this->notifyRepo->markSent($uid, $key, $ymd)) {
                continue;
            }

            $ok = $this->api->sendMessage($uid, $this->message($prayerLabel, $label, $hhmm));
            if ($ok) {
                $sent++;
                usleep(40000);
            }
        }

        return $sent;
    }

    private function message(string $prayerLabel, string $placeLabel, string $hhmm): string
    {
        $place = htmlspecialchars($placeLabel, ENT_QUOTES, 'UTF-8');
        $name  = htmlspecialchars($prayerLabel, ENT_QUOTES, 'UTF-8');
        return "🕌 <b>{$name}</b> — {$place}\n⏰ <code>{$hhmm}</code>";
    }
}
