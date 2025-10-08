<?php

namespace Trusti\Service;

use Trusti\Enum\StatusCode;

class StatusCache
{
    private array  $statuses = [];
    private array $statusByCode = [];
    private array $statusById = [];

    public function __construct(array $statuses = [])
    {
        $this->loadStatuses($statuses);
    }

    public function getIdByCode(string $code): ?int
    {
        return $this->statusByCode[$code]['id'] ?? null;
    }

    public function getCodeById(int $id): ?string
    {
        return $this->statusById[$id]['code'] ?? null;
    }

    public function getAllStatuses(): array
    {
        return $this->statuses;
    }

    public function getIdByEnum(StatusCode $status): ?int {
        return $this->getIdByCode($status->value);
    }

    private function loadStatuses(array $statuses): void
    {
        foreach ($statuses as $status) {
            $this->statuses[] = $status;
            $this->statusByCode[$status['code']] = $status;
            $this->statusById[$status['id']] = $status;
        }
    }
}