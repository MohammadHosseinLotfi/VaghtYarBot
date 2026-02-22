<?php
require_once __DIR__ . '/../config/config.php';

$pdo = getDB();

// ─── Provinces ───────────────────────────────────────────────
echo "Importing provinces...\n";
$data  = json_decode(file_get_contents(__DIR__ . '/province.json'), true);
$rows  = $data[1]['data'];
$stmt  = $pdo->prepare("INSERT IGNORE INTO provinces (id, name, capital, code) VALUES (?, ?, ?, ?)");
foreach ($rows as $r) {
    $stmt->execute([(int)$r['id'], $r['name'], $r['capital'], $r['code']]);
}
echo count($rows) . " provinces imported.\n";

// ─── Cities ──────────────────────────────────────────────────
echo "Importing cities...\n";
$data  = json_decode(file_get_contents(__DIR__ . '/city.json'), true);
$rows  = $data[1]['data'];
$stmt  = $pdo->prepare("INSERT IGNORE INTO cities (id, name, province_id, latitude, longitude) VALUES (?, ?, ?, ?, ?)");
foreach ($rows as $r) {
    $stmt->execute([(int)$r['id'], $r['name'], (int)$r['province_id'], (float)$r['latitude'], (float)$r['longitude']]);
}
echo count($rows) . " cities imported.\n";
echo "Done!\n";
