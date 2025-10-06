<?php
declare(strict_types=1);

namespace HyperfTest\Unit;

use Core\Domain\Entity\Account;
use Core\Domain\Entity\Withdraw;
use Core\Domain\Port\AccountRepository;
use Core\Domain\Port\Clock;
use Core\Domain\Port\IdGenerator;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\Service\WithdrawalDomainService;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;
use Core\Domain\ValueObject\Schedule;
use Core\Shared\Exception\BusinessException;
use DateTimeImmutable;
use Mockery;
use PHPUnit\Framework\TestCase;

final class WithdrawalDomainServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function makeService(): array
    {
        $accounts   = Mockery::mock(AccountRepository::class);
        $withdraws  = Mockery::mock(WithdrawRepository::class);
        $ids        = Mockery::mock(IdGenerator::class);
        $clock      = Mockery::mock(Clock::class);
        $service    = new WithdrawalDomainService();

        return [$service, $accounts, $withdraws, $ids, $clock];
    }

    private function cents(int $cents): Money
    {
        return Money::fromCents($cents);
    }

    private function immediateSchedule(DateTimeImmutable $now): Schedule
    {
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->andReturn($now);

        return new Schedule(null, $clock);
    }

    private function scheduledIn(DateTimeImmutable $now, string $modifier): Schedule
    {
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->andReturn($now);

        return new Schedule($now->modify($modifier), $clock);
    }

    private function makeWithdrawEntity(
        string $id,
        string $accountId,
        Money $amount,
        bool $done,
        bool $error,
        ?string $errorReason = null,
        ?\DateTimeInterface $scheduledFor = null
    ): Withdraw {
        $ref = new \ReflectionClass(Withdraw::class);
        /** @var Withdraw $w */
        $w = $ref->newInstanceWithoutConstructor();

        $set = function (string $prop, $val) use ($w) {
            $rp = new \ReflectionProperty($w, $prop);
            $rp->setAccessible(true);
            $rp->setValue($w, $val);
        };

        $set('id', $id);
        $set('accountId', $accountId);
        $set('amount', $amount);
        $set('done', $done);
        $set('error', $error);
        $set('errorReason', $errorReason);
        $set('scheduledFor', $scheduledFor);

        return $w;
    }

    public function test_request_immediate_success(): void
    {
        [$service, $accounts, $withdraws, $ids] = $this->makeService();
        $accountId = 'acc-1';
        $withdrawId = 'wid-1';
        $amount = $this->cents(1000);

        $withdraws->shouldReceive('save')->once();

        $acc = Mockery::mock(Account::class);
        $acc->shouldReceive('debit')->once()->with($amount);

        $accounts->shouldReceive('byId')->once()->with($accountId, true)->andReturn($acc);
        $accounts->shouldReceive('save')->once()->with($acc);
        $withdraws->shouldReceive('markDone')->once()->with($withdrawId);
        $ids->shouldReceive('uuid')->once()->andReturn($withdrawId);

        $result = $service->request($accounts, $withdraws, $ids, Mockery::mock(Clock::class), $accountId, $amount, new PixKey('email', 'x@x.com'), $this->immediateSchedule(new DateTimeImmutable()));

        $this->assertSame($withdrawId, $result);
    }

    public function test_request_immediate_insufficient_funds(): void
    {
        [$service, $accounts, $withdraws, $ids] = $this->makeService();
        $accountId = 'acc-1';
        $withdrawId = 'wid-2';
        $amount = $this->cents(5000);

        $withdraws->shouldReceive('save')->once();
        $ids->shouldReceive('uuid')->once()->andReturn($withdrawId);

        $acc = Mockery::mock(Account::class);
        $acc->shouldReceive('debit')->once()->with($amount)->andThrow(new BusinessException('INSUFFICIENT_FUNDS'));

        $accounts->shouldReceive('byId')->once()->with($accountId, true)->andReturn($acc);
        $accounts->shouldNotReceive('save');
        $withdraws->shouldReceive('markFailed')->once()->with($withdrawId, 'INSUFFICIENT_FUNDS');

        $result = $service->request($accounts, $withdraws, $ids, Mockery::mock(Clock::class), $accountId, $amount, new PixKey('email', 'x@x.com'), $this->immediateSchedule(new DateTimeImmutable()));

        $this->assertSame($withdrawId, $result);
    }

    public function test_request_immediate_account_not_found(): void
    {
        [$service, $accounts, $withdraws, $ids, $clock] = $this->makeService();
        $now        = new DateTimeImmutable('2025-10-03 12:00:00');
        $schedule   = $this->immediateSchedule($now);
        $accountId  = 'acc-1';
        $withdrawId = 'wid-3';
        $amount     = $this->cents(1000);
        $pix        = new PixKey('email', 'x@x.com');

        $ids->shouldReceive('uuid')->once()->andReturn($withdrawId);

        $withdraws->shouldReceive('save')->once()->with(
            $withdrawId, $accountId, Mockery::type(Money::class), 'PIX', false, null, Mockery::type(PixKey::class)
        );

        $accounts->shouldReceive('byId')->once()->with($accountId, true)->andReturn(null);
        $withdraws->shouldReceive('markFailed')->once()->with($withdrawId, 'ACCOUNT_NOT_FOUND');

        $result = $service->request($accounts, $withdraws, $ids, $clock, $accountId, $amount, $pix, $schedule);

        $this->assertSame($withdrawId, $result);
    }

    public function test_request_scheduled_persists_without_debit(): void
    {
        [$service, $accounts, $withdraws, $ids, $clock] = $this->makeService();
        $now         = new DateTimeImmutable('2025-10-03 12:00:00');
        $schedule    = $this->scheduledIn($now, '+1 minute');
        $accountId   = 'acc-1';
        $withdrawId  = 'wid-4';
        $amount      = $this->cents(3333);
        $pix         = new PixKey('email', 'x@x.com');

        $ids->shouldReceive('uuid')->once()->andReturn($withdrawId);

        $withdraws->shouldReceive('save')->once()->with(
            $withdrawId, $accountId, Mockery::type(Money::class), 'PIX', true, Mockery::type(\DateTimeInterface::class), Mockery::type(PixKey::class)
        );

        $accounts->shouldNotReceive('byId');
        $accounts->shouldNotReceive('save');
        $withdraws->shouldNotReceive('markDone');
        $withdraws->shouldNotReceive('markFailed');

        $result = $service->request($accounts, $withdraws, $ids, $clock, $accountId, $amount, $pix, $schedule);

        $this->assertSame($withdrawId, $result);
    }

    public function test_processScheduled_success(): void
    {
        [$service, $accounts, $withdraws] = $this->makeService();
        $withdrawId = 'wid-5';
        $accountId  = 'acc-1';
        $amount     = $this->cents(1000);
        $w = $this->makeWithdrawEntity($withdrawId, $accountId, $amount, false, false);
        $w->amount = $amount;

        $updatedW = $this->makeWithdrawEntity($withdrawId, $accountId, $amount, true, false);

        $withdraws->shouldReceive('byId')->with($withdrawId)->andReturn($w, $updatedW);

        $acc = Mockery::mock(Account::class);

        $acc->balance = Money::fromCentsForBalance(100000);

        $acc->shouldReceive('debit')->once()->with($w->amount);

        $accounts->shouldReceive('byId')->once()->with($accountId, true)->andReturn($acc);
        $accounts->shouldReceive('save')->once()->with($acc);
        $withdraws->shouldReceive('markDone')->once()->with($withdrawId);

        $updated = $service->processScheduled($accounts, $withdraws, $withdrawId);
        $this->assertNotNull($updated);
        $this->assertTrue($updated->done);
    }

    public function test_processScheduled_account_not_found(): void
    {
        [$service, $accounts, $withdraws] = $this->makeService();
        $withdrawId = 'wid-6';
        $accountId  = 'acc-x';
        $amount     = $this->cents(700);
        $w = $this->makeWithdrawEntity($withdrawId, $accountId, $amount, false, false);
        $failedW = $this->makeWithdrawEntity($withdrawId, $accountId, $amount, true, true, 'ACCOUNT_NOT_FOUND');

        $withdraws->shouldReceive('byId')->with($withdrawId)->andReturn($w, $failedW);
        $accounts->shouldReceive('byId')->once()->with($accountId, true)->andReturn(null);
        $withdraws->shouldReceive('markFailed')->once()->with($withdrawId, 'ACCOUNT_NOT_FOUND');
        $accounts->shouldNotReceive('save');
        $withdraws->shouldNotReceive('markDone');

        $updated = $service->processScheduled($accounts, $withdraws, $withdrawId);
        $this->assertTrue($updated->error);
        $this->assertSame('ACCOUNT_NOT_FOUND', $updated->errorReason);
    }

    public function test_processScheduled_insufficient_funds(): void
    {
        [$service, $accounts, $withdraws] = $this->makeService();
        $withdrawId = 'wid-7';
        $accountId  = 'acc-1';
        $amount     = $this->cents(50000);
        $w = $this->makeWithdrawEntity($withdrawId, $accountId, $amount, false, false);
        $failedW = $this->makeWithdrawEntity($withdrawId, $accountId, $amount, true, true, 'INSUFFICIENT_FUNDS');

        $withdraws->shouldReceive('byId')->with($withdrawId)->andReturn($w, $failedW);

        $acc = Mockery::mock(Account::class);
        $acc->id = $accountId;
        $acc->balance = Money::fromCents(10000);
        $acc->name = 'Test Account';

        $accounts->shouldReceive('byId')->once()->with($accountId, true)->andReturn($acc);
        $withdraws->shouldReceive('markFailed')->once()->with($withdrawId, 'INSUFFICIENT_FUNDS');
        $accounts->shouldNotReceive('save');
        $withdraws->shouldNotReceive('markDone');

        $updated = $service->processScheduled($accounts, $withdraws, $withdrawId);
        $this->assertTrue($updated->error);
        $this->assertSame('INSUFFICIENT_FUNDS', $updated->errorReason);
    }

    public function test_processScheduled_already_done_returns_as_is(): void
    {
        [$service, $accounts, $withdraws] = $this->makeService();
        $withdrawId = 'wid-8';
        $already = $this->makeWithdrawEntity($withdrawId, 'acc-1', $this->cents(100), true, false);

        $withdraws->shouldReceive('byId')->once()->with($withdrawId)->andReturn($already);
        $accounts->shouldNotReceive('byId');
        $accounts->shouldNotReceive('save');
        $withdraws->shouldNotReceive('markDone');
        $withdraws->shouldNotReceive('markFailed');

        $ret = $service->processScheduled($accounts, $withdraws, $withdrawId);
        $this->assertSame($already, $ret);
    }

    public function test_processScheduled_not_found_returns_null(): void
    {
        [$service, $_, $withdraws] = $this->makeService();
        $withdrawId = 'wid-404';
        $withdraws->shouldReceive('byId')->once()->with($withdrawId)->andReturn(null);

        $ret = $service->processScheduled(Mockery::mock(AccountRepository::class), $withdraws, $withdrawId);
        $this->assertNull($ret);
    }
}