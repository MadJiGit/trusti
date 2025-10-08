<?php

namespace Trusti\Repository;

use Trusti\db\DBConnect;
use PDO;

class StatusRepository
{
    private ?PDO $pdo;

    public function __construct()
    {
        $this->pdo = DBConnect::conn();
    }

    public function getAllStatuses(): array
    {
        $stmt = $this->pdo->query("SELECT id, code
                                            FROM trusti.email_statuses;");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatusById(int $statusId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, code 
                                            FROM trusti.email_statuses 
                                            WHERE id = ?;");
        $stmt->bindParam(1, $statusId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    public function getStatusByCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare("SELECT id, code 
                                            FROM trusti.email_statuses 
                                            WHERE code = ?;");
        $stmt->bindParam(1, $code, PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
}