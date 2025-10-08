#!/usr/bin/env php
<?php
ini_set('max_execution_time', 0);

$dirname = dirname(__DIR__, 1);
require_once $dirname . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Ramsey\Uuid\Uuid;
use Trusti\db\DBConnect;

$dotenv = Dotenv::createImmutable($dirname);
$dotenv->load();

header('Content-Type: application/json');

// Database connection
$pdo = DBConnect::conn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Send email endpoint
    $input = json_decode(file_get_contents('php://input'), true);
    $idempotencyKey = $input['idempotency_key'] ?? null;

    if (!$idempotencyKey) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing idempotency_key']);
        exit;
    }

    // Check if already exists (idempotency)
    $stmt = $pdo->prepare("SELECT message_id FROM trusti.provider_emails WHERE idempotency_key = ?");
    $stmt->execute([$idempotencyKey]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Already processed - return same message_id
        http_response_code(201);
        echo json_encode(['messageId' => $existing['message_id']]);
        exit;
    }

    // Create new entry
    $messageId = '<provider-' . time() . '-' . Uuid::uuid4()->toString() . '@trusti.local>';
    $statusId = 4; // 'delivered' status

    $stmt = $pdo->prepare("INSERT INTO provider_emails (idempotency_key, message_id, status_id) VALUES (?, ?, ?)");
    $stmt->execute([$idempotencyKey, $messageId, $statusId]);

    http_response_code(201);
    echo json_encode(['messageId' => $messageId]);

} elseif (isset($_GET['idempotency_key'])) {
    // Delivery status endpoint
    $idempotencyKey = $_GET['idempotency_key'];

    $stmt = $pdo->prepare("
        SELECT pe.*, es.code as status_code
        FROM provider_emails pe
        JOIN email_statuses es ON pe.status_id = es.id
        WHERE pe.idempotency_key = ?
    ");
    $stmt->execute([$idempotencyKey]);
    $email = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$email) {
        http_response_code(404);
        echo json_encode(['error' => 'Email not found']);
        exit;
    }

    http_response_code(200);
    echo json_encode([
        'events' => [
            [
                'event' => $email['status_code'],
                'reason' => $email['error_message'] ?? 'Provider delivery successful'
            ]
        ]
    ]);

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}
