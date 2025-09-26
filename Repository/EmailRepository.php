<?php

class EmailRepository
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = DBConnect::conn();
    }

    public function getTenEmails(): array
    {
        $stmt = $this->pdo->prepare("SELECT * 
                                            FROM trusti.emails 
                                            WHERE status IN(?) 
                                            LIMIT 10
                                            FOR UPDATE SKIP LOCKED;");

        $stmt->bindValue(1, StatusCode::QUEUED->value, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setSent(int $emailId, ?string $provider, ?string $message_id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status = ?, 
                                                updated_at = NOW(), 
                                                retries = retries + 1,
                                                provider = ?,
                                                message_id = ?
                                            WHERE id = ?;");

        $stmt->bindValue(1, StatusCode::SENT->value, PDO::PARAM_STR);
        $stmt->bindParam(2, $provider, PDO::PARAM_STR);
        $stmt->bindParam(3, $message_id, PDO::PARAM_STR);
        $stmt->bindParam(4, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setFailed(int $emailId, string $error, ?string $provider = null, ?string $message_id = null): bool
    {
        echo "Setting email with id $emailId to FAILED. Error: $error" . PHP_EOL;

        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status = ?, 
                                                updated_at = NOW(), 
                                                retries = retries + 1,
                                                error_message = ?,
                                                provider = ?,
                                                message_id = ?
                                            WHERE id = ?;");

        $stmt->bindValue(1, StatusCode::FAILED->value, PDO::PARAM_STR);
        $stmt->bindParam(2, $error, PDO::PARAM_STR);
        $stmt->bindParam(3, $provider, PDO::PARAM_STR);
        $stmt->bindParam(4, $message_id, PDO::PARAM_STR);
        $stmt->bindParam(5, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setQueued(int $emailId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status = ?, 
                                                updated_at = NOW(), 
                                                retries = retries + 1
                                            WHERE id = ?;");

        $stmt->bindValue(1, StatusCode::QUEUED->value, PDO::PARAM_STR);
        $stmt->bindParam(2, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setProcessing(int $emailId): bool
    {
        echo "Setting email with id $emailId to PROCESSING" . PHP_EOL;
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status = ?, 
                                                updated_at = NOW() 
                                            WHERE id = ?;");

        $stmt->bindValue(1, StatusCode::PROCESSING->value, PDO::PARAM_STR);
        $stmt->bindParam(2, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setDelivered(int $emailId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status = ?, 
                                                updated_at = NOW()
                                            WHERE id = ?;");

        $stmt->bindValue(1, StatusCode::DELIVERED->value, PDO::PARAM_STR);
        $stmt->bindParam(2, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function addEmail(string $email, string $subject, string $body, string $idempotency_key): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO trusti.emails 
                                            (idempotency_key, email, subject, body, status, updated_at)
                                            VALUES (?, ?, ?, ?, ?, NOW());");

        $stmt->bindValue(1, $idempotency_key, PDO::PARAM_STR);
        $stmt->bindValue(2, $email, PDO::PARAM_STR);
        $stmt->bindValue(3, $subject, PDO::PARAM_STR);
        $stmt->bindValue(4, $body, PDO::PARAM_STR);
        $stmt->bindValue(5, StatusCode::QUEUED->value, PDO::PARAM_STR);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function getSentEmails(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, message_id, email, subject, provider
                                            FROM trusti.emails
                                            WHERE status = ?
                                            ORDER BY updated_at ASC");

        $stmt->bindValue(1, StatusCode::SENT->value, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanupProcessing(int $timeoutSeconds = 60): int
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails
                                            SET status = ?,
                                                updated_at = NOW()
                                            WHERE status = ?
                                              AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");

        $stmt->bindValue(1, StatusCode::QUEUED->value, PDO::PARAM_STR);
        $stmt->bindValue(2, StatusCode::PROCESSING->value, PDO::PARAM_STR);
        $stmt->bindValue(3, $timeoutSeconds, PDO::PARAM_INT);

        $stmt->execute();

        $count = $stmt->rowCount();

        if ($count > 0) {
//            echo "Reset $count stuck email(s) from processing to queued" . PHP_EOL;
        }

        return $count;
    }

    public function cleanupSent(int $timeoutSeconds = 9000): int
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails
                                            SET status = ?,
                                                error_message = ?,
                                                updated_at = NOW()
                                            WHERE status = ?
                                              AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");

        $stmt->bindValue(1, StatusCode::FAILED->value, PDO::PARAM_STR);
        $stmt->bindValue(2, 'Automatic set failed! Timed out waiting for delivery confirmation!', PDO::PARAM_STR);
        $stmt->bindValue(3, StatusCode::SENT->value, PDO::PARAM_STR);
        $stmt->bindValue(4, $timeoutSeconds, PDO::PARAM_INT);

        $stmt->execute();

        $count = $stmt->rowCount();

        if ($count > 0) {
//            echo "Reset $count stuck email(s) from SENT to FAILED" . PHP_EOL;
        }

        return $count;
    }

    public function __destruct()
    {
        $this->pdo = null;
    }
}