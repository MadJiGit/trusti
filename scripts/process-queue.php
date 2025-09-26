#!/usr/bin/env php
<?php

ini_set('max_execution_time', 0);

$dirname = dirname(__DIR__, 1);
require_once $dirname . '/vendor/autoload.php';
require_once $dirname . '/src/EmailWorker.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable($dirname);
$dotenv->load();

$worker = new EmailWorker($_ENV['EMAIL_PROVIDER']);

$insideSleep = 5;
$outsideSleep = 5;

// if timer is under 10 seconds mailtrap return 429 Too Many Requests - time limit
// if hit rate limits, can see in DB status "queued" and retries increasing + 1
if($_ENV['EMAIL_PROVIDER'] == 'mailtrap') {
    $insideSleep = 10;
    $outsideSleep = 10;
}

static $cleanupProcessingCounter = 0;
static $cleanupSentCounter = 0;
static $deliveryCheckCounter = 0;
$processingOlderThan = 60; // seconds
$sentOlderThan = 90; // seconds

while(true) {
    $worker->prettyPrint("NEW CYCLE");

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

    $emails = $worker->getEmails();
    if($emails === []) {
        $worker->prettyPrint("No emails found in queue.");
    }

    foreach ($emails as $email) {
        $worker->setProcessing($email['id']);

        $worker->prettyPrint("Sleeping for $insideSleep seconds INSIDE to check status PROCESSING in DB.");
        sleep($insideSleep);

        try {
            $worker->sendEmail($email);
        } catch (Exception $e) {
            $worker->prettyPrint($e);
        }
    }

    $worker->prettyPrint("Sleeping for $outsideSleep seconds OUTSIDE...");
    sleep($outsideSleep);
}

