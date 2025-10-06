<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\Command;

use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Application\Command\ProcessScheduledWithdrawalsHandler;
use Core\Domain\Entity\Withdraw;
use Core\Domain\Port\AccountRepository;
use Core\Domain\Port\Clock;
use Core\Domain\Port\TxManager;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\Service\WithdrawalDomainService;
use Core\Domain\ValueObject\Money;
use DateTimeImmutable;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Application\Command\ProcessScheduledWithdrawalsHandler
 */
final class ProcessScheduledWithdrawalsHandlerTest extends TestCase
{
    private MockInterface|WithdrawalDomainService $domainMock;
    private MockInterface|AccountRepository $accountsMock;
    private MockInterface|WithdrawRepository $withdrawsMock;
    private MockInterface|TxManager $txMock;
    private MockInterface|Clock $clockMock;
    private MockInterface|RichEventEmitter $eventsMock;
    private ProcessScheduledWithdrawalsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domainMock = Mockery::mock(WithdrawalDomainService::class);
        $this->accountsMock = Mockery::mock(AccountRepository::class);
        $this->withdrawsMock = Mockery::mock(WithdrawRepository::class);
        $this->txMock = Mockery::mock(TxManager::class);
        $this->clockMock = Mockery::mock(Clock::class);
        $this->eventsMock = Mockery::mock(RichEventEmitter::class);

        $this->handler = new ProcessScheduledWithdrawalsHandler(
            $this->domainMock, $this->accountsMock, $this->withdrawsMock,
            $this->txMock, $this->clockMock, $this->eventsMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_invoke_processes_multiple_pending_withdrawals(): void
    {
        $now = new DateTimeImmutable();
        $this->clockMock->shouldReceive('now')->once()->andReturn($now);

        $pending1 = $this->createMockWithdraw('wid-1');
        $pending2 = $this->createMockWithdraw('wid-2');
        $this->withdrawsMock->shouldReceive('findDueScheduled')->once()->andReturn([$pending1, $pending2]);

        $result1 = $this->createMockWithdraw('wid-1', true, false);
        $result2 = $this->createMockWithdraw('wid-2', true, true, 'SOME_ERROR');
        $this->domainMock->shouldReceive('processScheduled')->with(Mockery::any(), Mockery::any(), 'wid-1')->andReturn($result1);
        $this->domainMock->shouldReceive('processScheduled')->with(Mockery::any(), Mockery::any(), 'wid-2')->andReturn($result2);

        $this->txMock->shouldReceive('transactional')->twice()->andReturnUsing(fn(\Closure $c) => $c());

        $this->eventsMock->shouldReceive('emit')->twice();

        $count = $this->handler->process();

        $this->assertSame(2, $count);
    }

    public function test_invoke_does_nothing_when_no_withdrawals_are_due(): void
    {
        $now = new DateTimeImmutable();
        $this->clockMock->shouldReceive('now')->once()->andReturn($now);

        $this->withdrawsMock->shouldReceive('findDueScheduled')
            ->once()
            ->andReturn([]);

        $this->txMock->shouldNotReceive('transactional');
        $this->domainMock->shouldNotReceive('processScheduled');
        $this->eventsMock->shouldNotReceive('emit');

        $count =  $this->handler->process();

        $this->assertSame(0, $count);
    }

    private function createMockWithdraw(string $id, bool $done = false, bool $error = false, ?string $reason = null): Withdraw
    {
        $mock = Mockery::mock(Withdraw::class);
        $mock->id = $id;
        $mock->done = $done;
        $mock->error = $error;
        $mock->errorReason = $reason;
        $mock->accountId = 'acc-test';
        $mock->amount = Money::fromCents(1000);
        return $mock;
    }
}