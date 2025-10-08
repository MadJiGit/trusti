<?php

namespace Trusti\Repository;

use PDO;
use Trusti\Enum\StatusCode;
use Trusti\db\DBConnect;
use Trusti\Service\StatusCache;

class EmailRepository
{
    private ?PDO $pdo;
    private StatusCache $statusCache;

    public function __construct(StatusCache $statusCache)
    {
        $this->pdo = DBConnect::conn();
        $this->statusCache = $statusCache;
    }

    public function getTenEmails(): array
    {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare("SELECT * 
                                            FROM trusti.emails 
                                            WHERE status_id IN(?) 
                                            LIMIT 3
                                            FOR UPDATE SKIP LOCKED;");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::QUEUED), PDO::PARAM_STR);
        $stmt->execute();
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($emails as $email) {
            $this->setProcessing((int)$email['id']);
        }

        $this->pdo->commit();
        return $emails;
    }

    public function setSent(int $emailId, ?string $provider, ?string $message_id): bool
    {
        echo "Setting email with id $emailId to SENT" . PHP_EOL;
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status_id = ?, 
                                                updated_at = NOW(), 
                                                retries = retries + 1,
                                                provider = ?,
                                                message_id = ?
                                            WHERE id = ?;");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::SENT), PDO::PARAM_STR);
        $stmt->bindParam(2, $provider, PDO::PARAM_STR);
        $stmt->bindParam(3, $message_id, PDO::PARAM_STR);
        $stmt->bindParam(4, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setFailed(int $emailId, string $error, ?string $provider = null, ?string $message_id = null): bool
    {
        echo "Setting email with id $emailId to FAILED. Error: $error" . PHP_EOL;

        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status_id = ?, 
                                                updated_at = NOW(), 
                                                retries = retries + 1,
                                                error_message = ?,
                                                provider = ?,
                                                message_id = ?
                                            WHERE id = ?;");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::FAILED), PDO::PARAM_STR);
        $stmt->bindParam(2, $error, PDO::PARAM_STR);
        $stmt->bindParam(3, $provider, PDO::PARAM_STR);
        $stmt->bindParam(4, $message_id, PDO::PARAM_STR);
        $stmt->bindParam(5, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setQueued(int $emailId): bool
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status_id = ?, 
                                                updated_at = NOW(), 
                                                retries = retries + 1
                                            WHERE id = ?;");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::QUEUED), PDO::PARAM_STR);
        $stmt->bindParam(2, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setProcessing(int $emailId): bool
    {
        echo "Setting email with id $emailId to PROCESSING" . PHP_EOL;
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status_id = ?, 
                                                updated_at = NOW() 
                                            WHERE id = ?;");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::PROCESSING), PDO::PARAM_STR);
        $stmt->bindParam(2, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function setDelivered(int $emailId): bool
    {
        echo "Setting email with id $emailId to DELIVERED" . PHP_EOL;
        $stmt = $this->pdo->prepare("UPDATE trusti.emails 
                                            SET status_id = ?, 
                                                updated_at = NOW()
                                            WHERE id = ?;");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::DELIVERED), PDO::PARAM_STR);
        $stmt->bindParam(2, $emailId, PDO::PARAM_STR);
        return $stmt->execute();
    }

    public function addEmail(string $email, string $subject, string $body, string $idempotency_key): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO trusti.emails 
                                            (idempotency_key, email, subject, body, status_id, updated_at)
                                            VALUES (?, ?, ?, ?, ?, NOW());");

        $stmt->bindValue(1, $idempotency_key, PDO::PARAM_STR);
        $stmt->bindValue(2, $email, PDO::PARAM_STR);
        $stmt->bindValue(3, $subject, PDO::PARAM_STR);
        $stmt->bindValue(4, $body, PDO::PARAM_STR);
        $stmt->bindValue(5, $this->statusCache->getIdByEnum(StatusCode::QUEUED), PDO::PARAM_STR);
        $stmt->execute();
        return (int)$this->pdo->lastInsertId();
    }

    public function getSentEmails(): array
    {
        $stmt = $this->pdo->prepare("SELECT id, idempotency_key, email, subject, provider
                                            FROM trusti.emails
                                            WHERE status_id = ?
                                            ORDER BY updated_at ASC");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::SENT), PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cleanupProcessing(int $timeoutSeconds = 60): int
    {
        $stmt = $this->pdo->prepare("UPDATE trusti.emails
                                            SET status_id = ?,
                                                updated_at = NOW()
                                            WHERE status_id = ?
                                              AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::QUEUED), PDO::PARAM_STR);
        $stmt->bindValue(2, $this->statusCache->getIdByEnum(StatusCode::PROCESSING), PDO::PARAM_STR);
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
                                            SET status_id = ?,
                                                error_message = ?,
                                                updated_at = NOW()
                                            WHERE status_id = ?
                                              AND updated_at < DATE_SUB(NOW(), INTERVAL ? SECOND)");

        $stmt->bindValue(1, $this->statusCache->getIdByEnum(StatusCode::FAILED), PDO::PARAM_STR);
        $stmt->bindValue(2, 'Automatic set failed! Timed out waiting for delivery confirmation!', PDO::PARAM_STR);
        $stmt->bindValue(3, $this->statusCache->getIdByEnum(StatusCode::SENT), PDO::PARAM_STR);
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