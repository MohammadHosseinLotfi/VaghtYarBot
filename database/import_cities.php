<?php
// database/import_cities.php
// اجرا: php import_cities.php

$host = 'localhost';
$db   = 'stockifa_VaghtYarBot';
$user = 'stockifa_VaghtYarBot';
$pass = 'stockifa_VaghtYarBot';

$pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// ───────────────────────────────────────────────
// تابع نرمال‌سازی نام (نیم‌فاصله → فاصله، فاصله‌های اضافه، trim)
// ───────────────────────────────────────────────
function normalize(string $name): string {
    $name = str_replace("\u{200C}", ' ', $name); // نیم‌فاصله به فاصله
    $name = preg_replace('/\s+/', ' ', $name);   // چند فاصله → یکی
    return trim($name);
}

// ───────────────────────────────────────────────
// تابع پاک‌سازی نام شهر (حذف پرانتز و محتوای آن)
// مثال: جعفرآباد(اردبیل) → جعفرآباد
// ───────────────────────────────────────────────
function cleanCityName(string $name): string {
    $name = preg_replace('/\s*\(.*?\)/', '', $name);
    return normalize($name);
}

// ───────────────────────────────────────────────
// ۱. بارگذاری province.json
// ساختار: [0 => metadata, 1 => {type, name, data:[...]}]
// ───────────────────────────────────────────────
$provinceJson = json_decode(file_get_contents(__DIR__ . '/province.json'), true);
$provinceMap  = [];

foreach ($provinceJson[1]['data'] as $p) {
    $provinceMap[normalize($p['name'])] = (int)$p['id'];
}

// نگاشت دستی اختلاف نام‌ها
// کلید = نام در districts.json  |  مقدار = نام در province.json
$provinceAliases = [
    'چهار محال بختیاری' => 'چهارمحال و بختیاری',
];

// ───────────────────────────────────────────────
// ۲. بارگذاری districts از DB → نگاشت (نام نرمال + province_id) به id
// ───────────────────────────────────────────────
$districtRows = $pdo->query("SELECT id, name, province_id FROM districts")->fetchAll(PDO::FETCH_ASSOC);
$districtMap  = []; // "province_id|نام_نرمال" → district_id

foreach ($districtRows as $d) {
    $key = $d['province_id'] . '|' . normalize($d['name']);
    $districtMap[$key] = (int)$d['id'];
}

// ───────────────────────────────────────────────
// ۳. خواندن districts.json
// ───────────────────────────────────────────────
$json = json_decode(file_get_contents(__DIR__ . '/districts.json'), true);

// ───────────────────────────────────────────────
// ۴. Insert
// ───────────────────────────────────────────────
$sql = "INSERT IGNORE INTO cities 
            (name, province_id, district_id, latitude, longitude)
        VALUES 
            (:name, :province_id, :district_id, :lat, :lon)";
$stmt = $pdo->prepare($sql);

$inserted    = 0;
$skipped     = 0;
$noDistrict  = [];  // شهرستان‌هایی که در districts پیدا نشدن

foreach ($json as $provinceName => $counties) {

    // پیدا کردن province_id
    $normalProvince = normalize($provinceName);
    $mappedName     = $provinceAliases[$normalProvince] ?? $normalProvince;
    $province_id    = $provinceMap[$mappedName] ?? ($provinceMap[$normalProvince] ?? null);

    if (!$province_id) {
        echo "⚠️  استان پیدا نشد: $provinceName\n";
        continue;
    }

    foreach ($counties as $countyName => $towns) {

        // پیدا کردن district_id
        $normalCounty = normalize($countyName);
        $districtKey  = $province_id . '|' . $normalCounty;
        $district_id  = $districtMap[$districtKey] ?? null;

        if (!$district_id) {
            $noDistrict[] = "$provinceName > $countyName";
            // بازهم شهرها رو وارد می‌کنیم، فقط district_id = NULL
        }

        foreach ($towns as $cityName => $coords) {

            $cleanName = cleanCityName((string)$cityName);

            if (empty($cleanName)) {
                $skipped++;
                continue;
            }

            $lat = isset($coords['lat'])  ? (float)$coords['lat']  : null;
            $lon = isset($coords['long']) ? (float)$coords['long'] : null;

            if ($lat === null || $lon === null) {
                $skipped++;
                continue;
            }

            $stmt->execute([
                ':name'        => $cleanName,
                ':province_id' => $province_id,
                ':district_id' => $district_id, // null اگر پیدا نشد
                ':lat'         => $lat,
                ':lon'         => $lon,
            ]);

            if ($stmt->rowCount() > 0) {
                $inserted++;
            } else {
                $skipped++;
            }
        }
    }
}

// ───────────────────────────────────────────────
// ۵. گزارش
// ───────────────────────────────────────────────
echo "\n✅ وارد شد: $inserted شهر\n";
echo "⏭️  تکراری/رد شده: $skipped\n";

if ($noDistrict) {
    $unique = array_unique($noDistrict);
    echo "\n⚠️  شهرستان‌هایی که district_id نداشتند (" . count($unique) . " عدد):\n";
    foreach ($unique as $item) {
        echo "   - $item\n";
    }
}
