<?php
/**
 * Persian Calendar Events Importer
 * Source: persian-calendar/persian-calendar
 * ⚠️ بعد از ایمپورت این فایل را حذف کن
 */

// ══════════════════════════════════════════
//  تنظیمات — فقط اینجا رو ویرایش کن
// ══════════════════════════════════════════
const IMPORT_TOKEN = 'your_secret_token_here'; // عوضش کن
const DB_HOST      = 'localhost';
const DB_NAME      = 'your_database';
const DB_USER      = 'your_username';
const DB_PASS      = 'your_password';
const DB_TABLE     = 'calendar_events';
const JSON_FILE    = __DIR__ . '/events.json'; // اگه فایل رو آپلود کردی
const JSON_URL     = 'https://raw.githubusercontent.com/persian-calendar/persian-calendar/main/PersianCalendar/data/events.json';
// ══════════════════════════════════════════

header('Content-Type: text/html; charset=utf-8');

// ─── بررسی توکن ────────────────────────────────────────────────
if (empty($_GET['token']) || $_GET['token'] !== IMPORT_TOKEN) {
    http_response_code(403);
    die('<h1>403 Forbidden</h1>');
}

$doCreateTable = isset($_GET['create_table']);
$doFresh       = isset($_GET['fresh']);

echo <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
  <meta charset="utf-8">
  <title>Calendar Importer</title>
  <style>
    body { font-family: monospace; background:#1e1e1e; color:#d4d4d4; padding:20px; }
    .ok  { color:#4ec9b0; }
    .err { color:#f44747; }
    .inf { color:#9cdcfe; }
    .warn{ color:#dcdcaa; }
    pre  { direction:ltr; line-height:1.8; }
  </style>
</head>
<body><pre>
HTML;

ob_implicit_flush(true);

// ─── اتصال به دیتابیس ──────────────────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        ]
    );
    echo '<span class="ok">✓ اتصال به دیتابیس موفق</span>' . PHP_EOL;
} catch (PDOException $e) {
    die('<span class="err">✗ خطای دیتابیس: ' . $e->getMessage() . '</span>');
}

// ─── ساخت جدول (در صورت نیاز) ─────────────────────────────────
if ($doCreateTable) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `" . DB_TABLE . "` (
          `id`           INT UNSIGNED     NOT NULL AUTO_INCREMENT,
          `calendar`     ENUM('Persian','Hijri','Gregorian') NOT NULL,
          `month`        TINYINT UNSIGNED NOT NULL,
          `day`          TINYINT UNSIGNED DEFAULT NULL,
          `title`        VARCHAR(512)     NOT NULL,
          `type`         VARCHAR(20)      NOT NULL DEFAULT 'Iran',
          `holiday`      TINYINT(1)       NOT NULL DEFAULT 0,
          `is_irregular` TINYINT(1)       NOT NULL DEFAULT 0,
          `rule`         VARCHAR(30)      DEFAULT NULL,
          `nth`          TINYINT          DEFAULT NULL,
          `weekday`      TINYINT UNSIGNED DEFAULT NULL,
          `offset`       TINYINT          DEFAULT NULL,
          `year`         SMALLINT UNSIGNED DEFAULT NULL,
          PRIMARY KEY (`id`),
          INDEX `idx_lookup`  (`calendar`,`month`,`day`),
          INDEX `idx_holiday` (`holiday`),
          INDEX `idx_type`    (`type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo '<span class="ok">✓ جدول آماده شد</span>' . PHP_EOL;
}

// ─── خواندن JSON ───────────────────────────────────────────────
$json = null;

if (file_exists(JSON_FILE)) {
    $json = file_get_contents(JSON_FILE);
    echo '<span class="ok">✓ فایل JSON محلی خوانده شد</span>' . PHP_EOL;
} else {
    echo '<span class="inf">↓ دانلود از GitHub...</span>' . PHP_EOL;

    // تلاش با cURL اول (پایدارتر روی هاست)
    if (function_exists('curl_init')) {
        $ch = curl_init(JSON_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT      => 'PHP-CalendarImporter/1.0',
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($json === false || $httpCode !== 200) {
            $json = null;
        }
    }

    // fallback با file_get_contents
    if ($json === null && ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(['http' => ['timeout' => 30]]);
        $json = @file_get_contents(JSON_URL, false, $ctx);
    }

    if (empty($json)) {
        die('<span class="err">✗ دانلود JSON ناموفق — فایل را دستی آپلود کن</span>');
    }
    echo '<span class="ok">✓ دانلود موفق</span>' . PHP_EOL;
}

$data = json_decode($json, true);
if (!$data) {
    die('<span class="err">✗ خطا در parse کردن JSON: ' . json_last_error_msg() . '</span>');
}

// ─── پاکسازی جدول قبلی ────────────────────────────────────────
if ($doFresh) {
    $pdo->exec("TRUNCATE TABLE `" . DB_TABLE . "`");
    echo '<span class="warn">⚑ جدول پاکسازی شد (fresh)</span>' . PHP_EOL;
}

echo PHP_EOL;

// ─── Prepared Statement ───────────────────────────────────────
$stmt = $pdo->prepare("
    INSERT INTO `" . DB_TABLE . "`
        (calendar, month, day, title, type, holiday, is_irregular, rule, nth, weekday, `offset`, year)
    VALUES
        (:calendar, :month, :day, :title, :type, :holiday, :is_irregular, :rule, :nth, :weekday, :offset, :year)
");

$counts = [
    'Persian'   => 0,
    'Hijri'     => 0,
    'Gregorian' => 0,
    'Irregular' => 0,
];
$errors = 0;

/**
 * اجرای یک insert با مدیریت خطا
 */
function doInsert(PDOStatement $stmt, array $params): bool {
    try {
        return $stmt->execute($params);
    } catch (PDOException $e) {
        echo '<span class="err">  ✗ ' . mb_substr($params[':title'], 0, 40) . ' — ' . $e->getMessage() . '</span>' . PHP_EOL;
        return false;
    }
}

/**
 * ساخت آرایه پارامتر برای رکوردهای عادی
 */
function makeRow(string $cal, array $item): array {
    return [
        ':calendar'     => $cal,
        ':month'        => (int)$item['month'],
        ':day'          => isset($item['day']) ? (int)$item['day'] : null,
        ':title'        => $item['title'],
        ':type'         => $item['type'],
        ':holiday'      => empty($item['holiday']) ? 0 : 1,
        ':is_irregular' => 0,
        ':rule'         => null,
        ':nth'          => null,
        ':weekday'      => null,
        ':offset'       => null,
        ':year'         => null,
    ];
}

// ─── تقویم شمسی ──────────────────────────────────────────────
foreach ($data['Persian Calendar'] ?? [] as $item) {
    if (doInsert($stmt, makeRow('Persian', $item))) {
        $counts['Persian']++;
    } else {
        $errors++;
    }
}
echo sprintf('<span class="ok">📅 شمسی       : %d رکورد</span>', $counts['Persian']) . PHP_EOL;

// ─── تقویم قمری ──────────────────────────────────────────────
foreach ($data['Hijri Calendar'] ?? [] as $item) {
    if (doInsert($stmt, makeRow('Hijri', $item))) {
        $counts['Hijri']++;
    } else {
        $errors++;
    }
}
echo sprintf('<span class="ok">🌙 قمری        : %d رکورد</span>', $counts['Hijri']) . PHP_EOL;

// ─── تقویم میلادی ─────────────────────────────────────────────
foreach ($data['Gregorian Calendar'] ?? [] as $item) {
    if (doInsert($stmt, makeRow('Gregorian', $item))) {
        $counts['Gregorian']++;
    } else {
        $errors++;
    }
}
echo sprintf('<span class="ok">🗓  میلادی      : %d رکورد</span>', $counts['Gregorian']) . PHP_EOL;

// ─── مناسبت‌های متغیر ─────────────────────────────────────────
foreach ($data['Irregular Recurring'] ?? [] as $item) {
    $params = [
        ':calendar'     => $item['calendar'],
        ':month'        => (int)$item['month'],
        ':day'          => isset($item['day']) ? (int)$item['day'] : null,
        ':title'        => $item['title'],
        ':type'         => $item['type'],
        ':holiday'      => empty($item['holiday']) ? 0 : 1,
        ':is_irregular' => 1,
        ':rule'         => $item['rule'],
        ':nth'          => isset($item['nth'])     ? (int)$item['nth']     : null,
        ':weekday'      => isset($item['weekday']) ? (int)$item['weekday'] : null,
        ':offset'       => isset($item['offset'])  ? (int)$item['offset']  : null,
        ':year'         => isset($item['year'])    ? (int)$item['year']    : null,
    ];

    if (doInsert($stmt, $params)) {
        $counts['Irregular']++;
    } else {
        $errors++;
    }
}
echo sprintf('<span class="ok">🔄 متغیر        : %d رکورد</span>', $counts['Irregular']) . PHP_EOL;

// ─── نتیجه نهایی ─────────────────────────────────────────────
$total = array_sum($counts);
echo PHP_EOL;
echo '══════════════════════════════════' . PHP_EOL;
echo sprintf('<span class="ok">✅ مجموع ایمپورت‌شده : %d رکورد</span>', $total) . PHP_EOL;
if ($errors > 0) {
    echo sprintf('<span class="err">⚠  تعداد خطا         : %d</span>', $errors) . PHP_EOL;
}
echo '══════════════════════════════════' . PHP_EOL;
echo PHP_EOL;
echo '<span class="warn">⚠ این فایل را الان حذف کن!</span>' . PHP_EOL;

echo '</pre></body></html>';
