<?php

namespace Trusti\EmailProvider;

interface EmailProviderInterface
{
    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $idempotency_key = null): array;
    public function shouldRetry(int $httpCode): bool;
    public function checkDeliveryStatus(string $messageId): array;
    public function processDeliveryStatusResponse(array $data, string $messageId): array;
}