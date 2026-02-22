<?php

namespace App\Service;

class DateTimeService
{
    public function __construct()
    {
        require_once __DIR__ . '/../../lib/jdf.php';
        date_default_timezone_set('Asia/Tehran');
    }

    public function getNow(): array
    {
        $ts = time();
        return [
            'j_year'     => (int) jdate('Y', $ts),
            'j_month'    => (int) jdate('m', $ts),
            'j_day'      => (int) jdate('d', $ts),
            'day_name'   => jdate('l', $ts),
            'month_name' => jdate('F', $ts),
            'time'       => date('H:i:s', $ts),
            'formatted'  => jdate('l، j F Y', $ts),
        ];
    }
}
