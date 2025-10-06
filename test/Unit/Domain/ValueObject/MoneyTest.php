<?php
declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use Core\Domain\ValueObject\Money;
use Core\Shared\Exception\BusinessException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Core\Domain\ValueObject\Money
 */
final class MoneyTest extends TestCase
{
    public function test_fromDecimal_creates_correct_cents_value(): void
    {
        $money = Money::fromDecimal(123.45);
        $this->assertSame(12345, $money->amountInCents);
    }

    public function test_fromCents_creates_correct_cents_value(): void
    {
        $money = Money::fromCents(54321);
        $this->assertSame(54321, $money->amountInCents);
    }

    public function test_fromDecimal_throws_exception_for_more_than_two_decimals(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot have more than 2 decimal places.');
        Money::fromDecimal(10.999);
    }

    /** @dataProvider nonPositiveTransactionProvider */
    public function test_factories_throw_exception_for_non_positive_transactions(callable $factory): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Transaction amount must be positive.');
        $factory();
    }

    public static function nonPositiveTransactionProvider(): array
    {
        return [
            'fromDecimal with zero'     => [fn() => Money::fromDecimal(0.0)],
            'fromDecimal with negative' => [fn() => Money::fromDecimal(-10.50)],
            'fromCents with zero'       => [fn() => Money::fromCents(0)],
            'fromCents with negative'   => [fn() => Money::fromCents(-50)],
        ];
    }

    public function test_balance_factory_allows_zero(): void
    {
        $money = Money::fromCentsForBalance(0);
        $this->assertSame(0, $money->amountInCents);
    }

    public function test_balance_factory_throws_for_negative(): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Amount cannot be negative.');
        Money::fromCentsForBalance(-1);
    }

    public function test_minus_subtracts_correctly(): void
    {
        $moneyA = Money::fromCents(1000);
        $moneyB = Money::fromCents(350);
        $result = $moneyA->minus($moneyB);
        $this->assertSame(650, $result->amountInCents);
    }

    public function test_gte_compares_correctly(): void
    {
        $money10 = Money::fromCents(1000);
        $money5 = Money::fromCents(500);
        $this->assertTrue($money10->gte($money5));
        $this->assertTrue($money10->gte($money10));
        $this->assertFalse($money5->gte($money10));
    }

    public function test_toDecimal_converts_correctly(): void
    {
        $money = Money::fromCents(12345);
        $this->assertSame(123.45, $money->toDecimal());
    }
}