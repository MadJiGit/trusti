<?php

ini_set('max_execution_time', 300);

if (str_contains($_SERVER['REQUEST_URI'], 'favicon')) {
    http_response_code(404);
    exit();
}

$dirname = dirname(__DIR__, 1);
require_once $dirname . '/src/EmailWorker.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($dirname);
$dotenv->load();

$worker = new EmailWorker($_ENV['EMAIL_PROVIDER']);
$emails = $worker->getEmails();

static $cleanupProcessingCounter = 0;
static $cleanupSentCounter = 0;
static $deliveryCheckCounter = 0;
$processingOlderThan = 60; // seconds
$sentOlderThan = 90; // seconds

// after time clean up stuck emails in SENT state older than $sentOlderThan seconds
if($cleanupSentCounter++ >= 5) {
    $worker->prettyPrint("Running cleanup of stuck emails in SENT state older than $sentOlderThan seconds...");
    $worker->cleanupSent($sentOlderThan);
    $cleanupSentCounter = 0;
}

// after time clean up stuck emails in PROCESSING state older than $processingOlderThan seconds
if($cleanupProcessingCounter++ >= 2) {
    $worker->prettyPrint("Running cleanup of stuck emails in PROCESSING state older than $processingOlderThan seconds...");
    $worker->cleanupProcessing($processingOlderThan);
    $cleanupProcessingCounter = 0;
}

// check delivery status for SENT emails every 2 cycles
if($deliveryCheckCounter++ >= 2) {
    $worker->prettyPrint("Checking delivery status for SENT emails...");
    $worker->checkDeliveryStatus();
    $deliveryCheckCounter = 0;
}

if($emails === []) {
    $worker->prettyPrint("No emails found in queue.");
    exit(0);
}

foreach ($emails as $email) {

    $worker->cleanupProcessing(60);
    $worker->setProcessing($email['id']);

    try {
        $worker->sendEmail($email);
    } catch (Exception $e) {
            echo '<pre>' . var_export($e, true) . '</pre>';
    }

    // if timer is under 5 seconds helps to reproduce hitting rate limits
    sleep(10);
}