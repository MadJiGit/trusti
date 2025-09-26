<?php

use Mailgun\Mailgun;

class MailgunProvider implements EmailProviderInterface
{
    private Mailgun $client;
    private $sandbox;
    private string $from_email;

    public function __construct($api_key, $sandbox, $from_email)
    {
        $this->client = Mailgun::create($api_key);
        $this->sandbox = $sandbox;
        $this->from_email = $from_email;
    }

    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $idempotency_key = null): array
    {
        $result = $this->client->messages()->send($this->sandbox, [
            'from' => $from ?? $this->from_email,
            'to' => $to,
            'subject' => $subject,
            'text' => $body
        ]);

        return [
            'success' => true,
            'message_id' => $result->getId(),
            'response' => $result->getMessage()
        ];
    }

    public function checkDeliveryStatus(string $messageId): array
    {
        // TODO: Implement checkDeliveryStatus() method.
        return [];
    }

    public function processDeliveryStatusResponse(array $data, string $messageId): array
    {
        // TODO: Implement processDeliveryStatusResponse() method.
        return [];
    }

    public function shouldRetry(int $httpCode): bool
    {
        // TODO: Implement shouldRetry() method.
        return true;
    }
}