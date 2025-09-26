<?php

$dirname = dirname(__DIR__, 1);

require_once $dirname . '/vendor/autoload.php';
require_once $dirname . '/db/DBConnect.php';
require_once $dirname . '/constants/status_code.php';
require_once $dirname . '/EmailProvider/EmailProviderInterface.php';
require_once $dirname . '/EmailProvider/AbstractEmailProvider.php';
require_once $dirname . '/EmailProvider/EmailProviderFactory.php';
require_once $dirname . '/EmailProvider/MailgunProvider.php';
require_once $dirname . '/EmailProvider/MailtrapProvider.php';
require_once $dirname . '/EmailProvider/BrevoProvider.php';
require_once $dirname . '/Repository/EmailRepository.php';

class EmailWorker
{
    private EmailRepository $emailRepository;
    private EmailProviderInterface $emailProvider;

    public function __construct($provider)
    {
        $this->emailRepository = new EmailRepository();
        $this->emailProvider = $this->createProvider($provider);
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
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
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
            self::prettyPrint("Checking email ID {$email['id']} with message_id: {$email['message_id']}");

            if ($email['message_id'] === null or $email['message_id'] === ''){
                self::prettyPrint("Email ID {$email['id']}" .  " REASON: no message_id" );
                $this->emailRepository->setFailed($email['id'], 'Automatic set failed! No message_id', $result['provider'] ?? null, $result['message_id'] ?? null);
                break;
            }

            $result = $this->emailProvider->checkDeliveryStatus($email['message_id']);

            if (!$result['success']) {
                self::prettyPrint("API Error for email ID {$email['id']}: " . ($result['error'] ?? 'Unknown error'));
                continue;
            }

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