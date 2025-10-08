<?php

namespace Trusti\EmailProvider;

class MailtrapProvider extends AbstractEmailProvider
{
    private string $inbox_id;

    public function __construct($api_key, $inbox_id)
    {
        parent::__construct($api_key);
        $this->inbox_id = $inbox_id;
    }

    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $idempotency_key = null): array
    {
        $url = "https://sandbox.api.mailtrap.io/api/send/{$this->inbox_id}";

        $data = [
            'from' => [
                'email' => 'ts.krastev@trusti.bg',
                'name' => 'Test Sender'
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'text' => $body
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseArray = json_decode($response, true);

        return [
            'success' => $responseArray['success'] ?? false,
            'message_id' => $responseArray['message_ids'][0] ?? 'unknown',
            'provider' => 'mailtrap',
            'errors' => $responseArray['errors'][0] ?? null,
            'http_code' => $httpCode,
        ];
    }

    public function processDeliveryStatusResponse(array $data, string $messageId): array
    {
        // TODO: Implement processDeliveryStatusResponse() method.
        return [];
    }
}