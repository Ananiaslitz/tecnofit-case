<?php

namespace Core\Domain\Port;

interface WithdrawReadRepository
{
    public function listByAccount(string $accountId, int $page, int $perPage): array;
}
