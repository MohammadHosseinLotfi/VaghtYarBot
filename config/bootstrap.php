<?php
date_default_timezone_set('Asia/Tehran');
use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$dotenv->required([
    'BOT_TOKEN',
    'DB_HOST',
    'DB_NAME',
    'DB_USER',
    'DB_PASS',
]);
