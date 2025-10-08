<?php
namespace Trusti\Service;

use Trusti\EmailProvider\EmailProviderFactory;
use Trusti\EmailProvider\EmailProviderInterface;
use Exception;
use Trusti\Repository\EmailRepository;

class EmailWorker
{
    private EmailRepository $emailRepository;
    private EmailProviderInterface $emailProvider;
    private string $logFile;

    public function __construct($provider, StatusCache $statusCache)
    {
        $this->emailRepository = new EmailRepository($statusCache);
        $this->emailProvider = $this->createProvider($provider);

//        $workerPid = getenv('WORKER_ID') ?: 'default';
        $workerPid = getmypid();
        $this->logFile = __DIR__ . "/../logs/worker_{$workerPid}.log";
    }

    /**
     * @throws Exception
     */
    public function sendEmail(array $email): void
    {
        $result = $this->emailProvider->send($email['email'], $email['subject'], $email['body'], $_ENV['EMAIL_FROM'], $email['idempotency_key']);

        $status = $result['success'];

        self::prettyPrint($result);

        switch ($status) {
            case true:
                $this->emailRepository->setSent($email['id'], $result['provider'], $result['message_id']);
                break;
            case false:
                $http_code = $result['http_code'];
                $error_message = $result['errors'] ?? $result['error'] ?? $result['message'] ?? 'unknown error';

                // Get specific error message if available
                if ($this->emailProvider->shouldRetry($http_code)) {
                    if ($email['retries'] < $_ENV['QUEUE_MAX_RETRIES']) {
                        $this->emailRepository->setQueued($email['id']);
                    } else {
                        $this->emailRepository->setFailed($email['id'], $error_message, $result['provider'] ?? null, $result['message_id'] ?? null);
                    }
                } else {
                    $this->emailRepository->setFailed($email['id'], $http_code, $result['provider'] ?? null, $result['message_id'] ?? null);
                }
                break;
            default:
                throw new Exception('Unexpected status value: ' . self::prettyPrint($status));
        }
    }


    public function createProvider($provider): EmailProviderInterface
    {
        try {
            return EmailProviderFactory::create($provider);
        } catch (Exception $e) {
            self::prettyPrint($e->getMessage());
            exit(1);
        }
    }

    public function getEmails(): array
    {
        return $this->emailRepository->getTenEmails();
    }

    public function prettyPrint($data): void
    {

//        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        $output =  json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        file_put_contents($this->logFile, $output, FILE_APPEND);
    }

    public function cleanupProcessing(int $time): void
    {
        $this->emailRepository->cleanupProcessing($time);
    }

    public function cleanupSent(int $time): void
    {
        $this->emailRepository->cleanupSent($time);
    }

    public function setProcessing(mixed $id): void
    {
        $this->emailRepository->setProcessing($id);
    }

    public function checkDeliveryStatus(): void
    {
        $sentEmails = $this->emailRepository->getSentEmails();

        if (empty($sentEmails)) {
            self::prettyPrint("No SENT emails found to check delivery status.");
            return;
        }

        self::prettyPrint("Found " . count($sentEmails) . " SENT emails to check delivery status.");

        foreach ($sentEmails as $email) {
            self::prettyPrint("Checking email ID {$email['id']} with idempotency_key: {$email['idempotency_key']}");

            if (empty($email['idempotency_key'])){
                self::prettyPrint("Email ID {$email['id']}" .  " REASON: no idempotency_key" );
                $this->emailRepository->setFailed($email['id'], 'Automatic set failed! No idempotency_key');
                continue;
            }

            $result = $this->emailProvider->checkDeliveryStatus($email['idempotency_key']);

            if (!$result['success']) {
                self::prettyPrint("API Error for email ID {$email['id']}: " . ($result['error'] ?? 'Unknown error'));
                continue;
            }

            self::prettyPrint($result);

            switch ($result['event']) {
                case 'delivered':
                    self::prettyPrint("Email ID {$email['id']} was DELIVERED!");
                    $this->emailRepository->setDelivered($email['id']);
                    break;
                case 'hardBounces':
                case 'softBounces':
                case 'blocked':
                    self::prettyPrint("Email ID {$email['id']} EVENT: " . $result['event'] . " REASON " . $result['reason']);
                    $this->emailRepository->setFailed($email['id'], $result['reason'] ?? 'delivery failed', $result['provider'] ?? null, $result['message_id'] ?? null);
                    break;
                default:
                    self::prettyPrint("Email ID {$email['id']} EVENT: " . $result['event'] . " REASON " . $result['reason']);
                    break;
            }

            // Rate limiting protection
            sleep(1);
        }
    }

}