<?php

namespace Core\Domain\Entity;

use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;

class Withdraw {
    public function __construct(
        public string $id,
        public string $accountId,
        public Money $amount,
        public PixKey $pix,
        public bool $scheduled,
        public ?\DateTimeImmutable $scheduledFor,
        public string $method,
        public bool $done = false,
        public bool $error = false,
        public ?string $errorReason = null,
        public \DateTimeImmutable $createdAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $processedAt = new \DateTimeImmutable(),
        public \DateTimeImmutable $updatedAt = new \DateTimeImmutable()
    ) {}
}