<?php
define('BOT_TOKEN', '8217453643:AAHfsT9RSX0EZbv5BaMmKBItH_d0geNvjJk');

define('DB_HOST',    'localhost');
define('DB_NAME',    'stockifa_VaghtYarBot');
define('DB_USER',    'stockifa_VaghtYarBot');
define('DB_PASS',    'stockifa_VaghtYarBot');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
