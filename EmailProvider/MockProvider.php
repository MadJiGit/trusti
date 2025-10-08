<?php

namespace Trusti\EmailProvider;

class MockProvider extends AbstractEmailProvider
{

    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $idempotency_key = null): array
    {
        $url = "http://localhost:8001";

        $data = [
            'sender' => [
                'email' => 'reg9643@gmail.com',
                'name' => 'Test Sender'
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'textContent' => $body,
            'idempotency_key' => $idempotency_key
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $this->api_key,
            'Content-Type: application/json',
            'Idempotency-Key: ' . ($idempotency_key ?? '')
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseArray = json_decode($response, true);

        echo json_encode($httpCode , JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo json_encode($response , JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo json_encode($idempotency_key , JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;

        return [
            'success' => $httpCode === 201,
            'message_id' => $responseArray['messageId'] ?? 'unknown',
            'provider' => 'mock',
            'errors' => $responseArray['message'] ?? $responseArray['error'] ?? null,
            'http_code' => $httpCode,
        ];
    }

    public function checkDeliveryStatus(string $idempotencyKey): array
    {
        $url = "http://localhost:8001?idempotency_key=" . urlencode($idempotencyKey);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'api-key: ' . $this->api_key
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'error' => 'API request failed'
            ];
        }

        $data = json_decode($response, true);

        return $this->processDeliveryStatusResponse($data, $idempotencyKey);
    }

    public function processDeliveryStatusResponse(array $data, string $messageId): array
    {
        foreach (($data['events'] ?? []) as $event) {
            if($event['event'] === 'delivered' || $event['event'] === 'blocked' || $event['event'] === 'hardBounces' || $event['event'] === 'softBounces') {
                return [
                    'success' => true,
                    'event' => $event['event'],
                    'reason' => $event['reason'] ?? 'no reason provided'
                ];
            }
        }

        return [
            'success' => true,
            'event' => null,
            'reason' => 'Mock does not support delivery status checks via API',
        ];
    }
}