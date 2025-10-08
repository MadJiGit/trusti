<?php

namespace Trusti\EmailProvider;

abstract class AbstractEmailProvider implements EmailProviderInterface
{
    protected string $api_key;

    public function __construct(string $api_key, ?string $unused_param = null)
    {
        $this->api_key = $api_key;
    }

    abstract public function send(string $to, string $subject, string $body, ?string $from = null, ?string $idempotency_key = null): array;
    abstract public function processDeliveryStatusResponse(array $data, string $messageId): array;

    public function shouldRetry(int $httpCode): bool
    {
        // 429 RATE LIMIT ERRORS can retry (after delay)
        if ($httpCode === 429) {
            return true;
        }

        // CLIENT SIDE ERRORS can't retry
        if ($httpCode >= 400 && $httpCode < 500) {
            return false;
        }

        // 5xx SERVER ERRORS can retry
        // 3xx REDIRECTS can retry
        // 0 NETWORK ERRORS can retry
        if ($httpCode >= 500 || $httpCode === 0 || ($httpCode >= 300 && $httpCode < 400)) {
            return true;
        }

        // Default - retry
        return true;
    }

    // Default implementation - providers can override if supported
    public function checkDeliveryStatus(string $messageId): array
    {
        return [
            'success' => false,
            'error' => 'Delivery status checking not supported by this provider',
            'delivered' => false,
            'invalid' => false
        ];
    }
}