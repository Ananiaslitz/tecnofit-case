<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\Command;

use Core\Adapter\Out\Observability\RichEvent;
use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Application\Command\RequestPixWithdrawCommand;
use Core\Application\Command\RequestPixWithdrawHandler;
use Core\Domain\Entity\Withdraw;
use Core\Domain\Port\AccountRepository;
use Core\Domain\Port\Clock;
use Core\Domain\Port\IdGenerator;
use Core\Domain\Port\MailerPort;
use Core\Domain\Port\TxManager;
use Core\Domain\Port\WithdrawRepository;
use Core\Domain\Service\WithdrawalDomainService;
use Core\Domain\ValueObject\Money;
use Core\Domain\ValueObject\PixKey;
use DateTimeImmutable;
use InvalidArgumentException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Application\Command\RequestPixWithdrawHandler
 */
final class RequestPixWithdrawHandlerTest extends TestCase
{
    private MockInterface|WithdrawalDomainService $domainMock;
    private MockInterface|AccountRepository $accountsMock;
    private MockInterface|WithdrawRepository $withdrawsMock;
    private MockInterface|MailerPort $mailerMock;
    private MockInterface|TxManager $txMock;
    private MockInterface|IdGenerator $idsMock;
    private MockInterface|Clock $clockMock;
    private MockInterface|RichEventEmitter $eventsMock;
    private RequestPixWithdrawHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->domainMock = Mockery::mock(WithdrawalDomainService::class);
        $this->accountsMock = Mockery::mock(AccountRepository::class);
        $this->withdrawsMock = Mockery::mock(WithdrawRepository::class);
        $this->mailerMock = Mockery::mock(MailerPort::class);
        $this->txMock = Mockery::mock(TxManager::class);
        $this->idsMock = Mockery::mock(IdGenerator::class);
        $this->clockMock = Mockery::mock(Clock::class);
        $this->eventsMock = Mockery::mock(RichEventEmitter::class);

        $this->handler = new RequestPixWithdrawHandler(
            $this->domainMock, $this->accountsMock, $this->withdrawsMock,
            $this->mailerMock, $this->txMock, $this->idsMock,
            $this->clockMock, $this->eventsMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_invoke_handles_immediate_successful_withdrawal(): void
    {
        $cmd = new RequestPixWithdrawCommand('acc-1', 'PIX', 'email', 'test@test.com', 100.50, null);
        $now = new DateTimeImmutable('2025-10-05 20:00:00');
        $this->clockMock->shouldReceive('now')->andReturn($now);
        $this->txMock->shouldReceive('transactional')->once()->andReturnUsing(fn(\Closure $c) => $c());
        $this->domainMock->shouldReceive('request')->once()->andReturn('wid-123');
        $mockWithdraw = $this->createMockWithdraw('wid-123', true, false);
        $this->withdrawsMock->shouldReceive('byId')->once()->with('wid-123')->andReturn($mockWithdraw);
        $this->eventsMock->shouldReceive('emit')->twice();
        $this->mailerMock->shouldReceive('sendWithdrawEmail')->once();

        $result = ($this->handler)($cmd);

        $this->assertSame(['withdraw_id' => 'wid-123'], $result);
    }

    public function test_invoke_handles_immediate_failed_withdrawal(): void
    {
        $cmd = new RequestPixWithdrawCommand('acc-1', 'PIX', 'email', 'test@test.com', 100.50, null);
        $this->clockMock->shouldReceive('now')->andReturn(new DateTimeImmutable());
        $this->txMock->shouldReceive('transactional')->andReturnUsing(fn(\Closure $c) => $c());
        $this->domainMock->shouldReceive('request')->andReturn('wid-456');
        $mockWithdraw = $this->createMockWithdraw('wid-456', true, true, 'INSUFFICIENT_FUNDS');
        $this->withdrawsMock->shouldReceive('byId')->once()->with('wid-456')->andReturn($mockWithdraw);
        $this->eventsMock->shouldReceive('emit')->twice();
        $this->mailerMock->shouldNotReceive('sendWithdrawEmail');

        $result = ($this->handler)($cmd);

        $this->assertSame(['withdraw_id' => 'wid-456'], $result);
    }

    public function test_invoke_handles_scheduled_withdrawal(): void
    {
        $now = new DateTimeImmutable('2025-10-05 20:00:00');
        $scheduleTime = '2025-10-06 10:00';
        $cmd = new RequestPixWithdrawCommand('acc-1', 'PIX', 'email', 'test@test.com', 100.50, $scheduleTime);
        $this->clockMock->shouldReceive('now')->andReturn($now);
        $this->txMock->shouldReceive('transactional')->andReturnUsing(fn(\Closure $c) => $c());
        $this->domainMock->shouldReceive('request')->andReturn('wid-789');
        $mockWithdraw = $this->createMockWithdraw('wid-789', false, false);
        $this->withdrawsMock->shouldReceive('byId')->once()->with('wid-789')->andReturn($mockWithdraw);
        $this->eventsMock->shouldReceive('emit')->once()->with(Mockery::on(fn(RichEvent $e) => $e->name === 'withdraw.requested'));
        $this->mailerMock->shouldNotReceive('sendWithdrawEmail');

        $result = ($this->handler)($cmd);

        $this->assertSame(['withdraw_id' => 'wid-789'], $result);
    }

    /**
     * @dataProvider invalidArgumentProvider
     */
    public function test_invoke_throws_invalid_argument_exception(RequestPixWithdrawCommand $cmd, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);
        $tz = new \DateTimeZone(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');
        $this->clockMock->shouldReceive('now')->andReturn(new DateTimeImmutable('2025-10-05 20:00:00', $tz));
        ($this->handler)($cmd);
    }

    public static function invalidArgumentProvider(): array
    {
        $validPixKey = 'test@test.com';

        return [
            'invalid method' => [new RequestPixWithdrawCommand('a', 'TED', 'e', 'k', 1, null), 'Only PIX withdrawals are supported.'],
            'invalid pix type' => [new RequestPixWithdrawCommand('a', 'PIX', 'CPF', 'k', 1, null), 'Only PIX type "email" is supported for this case.'],
            'zero amount' => [new RequestPixWithdrawCommand('a', 'PIX', 'email', 'k', 0, null), 'Amount must be greater than zero.'],
            'negative amount' => [new RequestPixWithdrawCommand('a', 'PIX', 'email', 'k', -10, null), 'Amount must be greater than zero.'],
            'invalid schedule format' => [new RequestPixWithdrawCommand('a', 'PIX', 'email', $validPixKey, 1, '2025/10/10'), 'Invalid schedule format, expected Y-m-d H:i'],
            'schedule in the past' => [new RequestPixWithdrawCommand('a', 'PIX', 'email', $validPixKey, 1, '2025-10-05 19:00'), 'Schedule cannot be in the past.'],
            'schedule too far' => [new RequestPixWithdrawCommand('a', 'PIX', 'email', $validPixKey, 1, '2025-10-20 10:00'), 'Schedule cannot be more than 7 days in the future.'],
        ];
    }

    private function createMockWithdraw(string $id, bool $done, bool $error, ?string $reason = null): Withdraw
    {
        $pix = new PixKey('email', 'mock@key.com');
        $amount = Money::fromCents(10050);

        $mock = Mockery::mock(Withdraw::class);
        $mock->id = $id;
        $mock->done = $done;
        $mock->error = $error;
        $mock->errorReason = $reason;
        $mock->accountId = 'acc-1';
        $mock->amount = $amount;
        $mock->pix = $pix;

        return $mock;
    }
}