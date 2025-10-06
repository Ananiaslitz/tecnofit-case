<?php
declare(strict_types=1);

namespace Core\Domain\Entity;

use Core\Domain\ValueObject\Money;
use Core\Shared\Exception\BusinessException;

class Account
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $balance
    ) {}

    public function debit(Money $valueToDebit): void
    {
        if (! $this->balance->gte($valueToDebit)) {
            throw new BusinessException('INSUFFICIENT_FUNDS');
        }

        $this->balance = $this->balance->minus($valueToDebit);
    }
}