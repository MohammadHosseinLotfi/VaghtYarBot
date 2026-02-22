<?php

require_once __DIR__ . '/config/bootstrap.php';

use App\Telegram\Api;
use App\Telegram\Update;
use App\Repository\UserRepository;
use App\Handler\CommandHandler;

$input = json_decode(file_get_contents('php://input'), true);
if (empty($input)) exit;

$update = new Update($input);
if (!$update->getMessage()) exit;

$handler = new CommandHandler(new Api(), new UserRepository(getDB()));
$handler->handle($update);
