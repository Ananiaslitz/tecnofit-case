<?php
declare(strict_types=1);

namespace Core\Domain\ValueObject;

use Core\Shared\Exception\BusinessException;
use InvalidArgumentException;

final class Money
{
    public function __construct(public readonly int $amountInCents)
    {
        if ($this->amountInCents < 0) {
            throw new BusinessException('Amount cannot be negative.');
        }
    }

    public static function fromDecimal(float $amount): self
    {
        if ($amount <= 0.0) {
            throw new BusinessException('Transaction amount must be positive.');
        }
        if (round($amount, 2) !== $amount) {
            throw new InvalidArgumentException('Amount cannot have more than 2 decimal places.');
        }
        return new self((int) round($amount * 100));
    }

    public static function fromCents(int $cents): self
    {
        if ($cents <= 0) {
            throw new BusinessException('Transaction amount must be positive.');
        }
        return new self($cents);
    }

    public static function fromCentsForBalance(int $cents): self
    {
        return new self($cents);
    }

    public function cents(): int
    {
        return $this->amountInCents;
    }

    public function amount(): float
    {
        return round($this->amountInCents / 100, 2);
    }

    public function toDecimal(): float
    {
        return $this->amount();
    }

    public function minus(self $other): self
    {
        return new self($this->amountInCents - $other->amountInCents);
    }

    public function gte(self $other): bool
    {
        return $this->amountInCents >= $other->amountInCents;
    }
}
