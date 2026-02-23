<?php

namespace App\Service;

class GeoService
{
    private const URL        = 'https://nominatim.openstreetmap.org/reverse';
    private const TIMEOUT    = 4;
    private const USER_AGENT = 'VaghtYarBot/1.0 (Telegram Prayer Times Bot)';

    /**
     * مختصات → اطلاعات مکان
     * @return array{city:?string, state:?string, country:?string, country_code:string}|null
     */
    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $url = self::URL . '?' . http_build_query([
            'lat'             => $lat,
            'lon'             => $lng,
            'format'          => 'json',
            'accept-language' => 'fa',
            'zoom'            => 10,
        ]);

        $json = $this->fetch($url);
        if ($json === null) return null;

        $data = json_decode($json, true);
        if (!isset($data['address'])) return null;

        $addr = $data['address'];

        return [
            'city'         => $addr['city']     ?? $addr['town']
                           ?? $addr['village']  ?? $addr['county'] ?? null,
            'state'        => $addr['state']    ?? $addr['province'] ?? null,
            'country'      => $addr['country']  ?? null,
            'country_code' => strtolower($addr['country_code'] ?? ''),
        ];
    }

    // ─── HTTP با cURL / fallback به file_get_contents ────────────
    private function fetch(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_USERAGENT      => self::USER_AGENT,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_FOLLOWLOCATION => true,
            ]);
            $result   = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return ($result !== false && $httpCode === 200) ? $result : null;
        }

        if (ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => [
                'timeout'    => self::TIMEOUT,
                'user_agent' => self::USER_AGENT,
            ]]);
            $result = @file_get_contents($url, false, $ctx);
            return $result !== false ? $result : null;
        }

        return null;
    }
}
