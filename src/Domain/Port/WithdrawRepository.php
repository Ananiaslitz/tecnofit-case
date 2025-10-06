<?php

namespace Core\Domain\Port;

use Core\Domain\Entity\Withdraw;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;

interface WithdrawRepository {
    public function save(
        string $id,
        string $accountId,
        Money $amount,
        string $method,
        bool $scheduled,
        ?\DateTimeInterface $scheduledFor,
        ?PixKey $pix
    ): void;
    public function byId(string $id): ?Withdraw;
    public function markDone(string $id): void;
    public function markFailed(string $id, string $reason): void;
    public function findDueScheduled(\DateTimeInterface $now, int $limit = 100): array;
}