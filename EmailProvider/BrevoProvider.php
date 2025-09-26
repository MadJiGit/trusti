<?php

class BrevoProvider extends AbstractEmailProvider
{
    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $idempotency_key = null): array
    {
        $url = "https://api.brevo.com/v3/smtp/email";

        $data = [
            'sender' => [
                'email' => 'reg9643@gmail.com',
                'name' => 'Test Sender'
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'textContent' => $body
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
            'provider' => 'brevo',
            'errors' => $responseArray['message'] ?? $responseArray['error'] ?? null,
            'http_code' => $httpCode,
        ];
    }

    public function checkDeliveryStatus(string $messageId): array
    {
        $url = "https://api.brevo.com/v3/smtp/statistics/events/?messageId=" . urlencode($messageId);

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

        return $this->processDeliveryStatusResponse($data, $messageId);
    }

    function processDeliveryStatusResponse(array $data, string $messageId): array
    {
        foreach (($data['events'] ?? []) as $event) {
            return [
                'success' => true,
                'event' => $event['event'],
                'reason' => $event['reason'] ?? 'no reason provided'
            ];
        }

        return [
            'success' => true,
            'event' => null,
            'reason' => 'Brevo does not support delivery status checks via API',
        ];
    }
}