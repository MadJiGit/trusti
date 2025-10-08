<?php
$dirname = dirname(__FILE__, 2);
require_once $dirname . '/vendor/autoload.php';

use Ramsey\Uuid\Uuid;
use Dotenv\Dotenv;
use Trusti\Repository\EmailRepository;
use Trusti\Repository\StatusRepository;
use Trusti\Service\StatusCache;

$dotenv = Dotenv::createImmutable($dirname);
$dotenv->load();

$statusCache = new StatusCache((new StatusRepository())->getAllStatuses());
$emailRepository = new EmailRepository($statusCache);

echo $argc . PHP_EOL;

//foreach ($argv as $index => $arg) {
//    echo "Аргумент $index: $arg\n";
//}

if($argc != 4) {
    echo 'Usage: php push-email.php <email> <subject> <body>' . PHP_EOL;
    exit(1);
}

$email = $argv[1];
$subject = $argv[2] . ' date ' . date('Y-m-d H:i:s');
$body = $argv[3] . ' sent at ' . date('Y-m-d H:i:s');
$idempotency_key = Uuid::uuid4()->toString();

//if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
//    echo "Error: Invalid email address format" . PHP_EOL;
//    exit(1);
//}

$id = $emailRepository->addEmail($email, $subject, $body, $idempotency_key);
echo "Email with id $id successfully added to queue" . PHP_EOL;