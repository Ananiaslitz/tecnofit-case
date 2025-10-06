<?php
declare(strict_types=1);

namespace HyperfTest\Unit\Domain\Entity;

use Core\Domain\Entity\Account;
use Core\Domain\ValueObject\Money;
use Core\Shared\Exception\BusinessException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Core\Domain\Entity\Account
 */
final class AccountTest extends TestCase
{
    public function testDebitHappyPath(): void
    {
        $acc = new Account('A1', 'Diego', Money::fromDecimal(100.00));
        $acc->debit(Money::fromDecimal(25.40));

        $this->assertEquals(74.60, $acc->balance->toDecimal());
    }

    public function testDebitToZero(): void
    {
        $acc = new Account('A2', 'Zero', Money::fromDecimal(50.00));
        $acc->debit(Money::fromDecimal(50.00));

        $this->assertEquals(0, $acc->balance->amountInCents);
    }

    public function testDebitWithRounding(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot have more than 2 decimal places.');

        $acc = new Account('A3', 'Round', Money::fromDecimal(100.00));
        $acc->debit(Money::fromDecimal(33.335));
    }

    public function testMultipleDebitsRemainNumericallyStable(): void
    {
        $acc = new Account('A4', 'Multi', Money::fromDecimal(1.00));
        $acc->debit(Money::fromDecimal(0.33));
        $acc->debit(Money::fromDecimal(0.33));
        $acc->debit(Money::fromDecimal(0.33));

        $this->assertEquals(0.01, $acc->balance->toDecimal());
    }

    public function testDebitThrowsOnInsufficientFunds(): void
    {
        $acc = new Account('A5', 'Fail', Money::fromDecimal(10.00));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('INSUFFICIENT_FUNDS');

        $acc->debit(Money::fromDecimal(10.01));
    }
}