<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Application\Command;

use Core\Application\Command\RequestPixWithdrawCommand;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Application\Command\RequestPixWithdrawCommand
 */
final class RequestPixWithdrawCommandTest extends TestCase
{
    public function test_fromHttp_builds_command_with_full_data(): void
    {
        $accountId = 'acc-123';
        $body = [
            'method'   => 'pix',
            'pix'      => [
                'type' => 'EMAIL',
                'key'  => 'test@example.com',
            ],
            'amount'   => 123.45,
            'schedule' => '2025-10-10 10:00',
        ];

        $command = RequestPixWithdrawCommand::fromHttp($accountId, $body);

        $this->assertSame($accountId, $command->accountId);
        $this->assertSame('PIX', $command->method);
        $this->assertSame('email', $command->pixType);
        $this->assertSame('test@example.com', $command->pixKey);
        $this->assertSame(123.45, $command->amount);
        $this->assertSame('2025-10-10 10:00', $command->schedule);
    }

    public function test_fromHttp_builds_command_with_default_values(): void
    {
        $accountId = 'acc-456';
        $body = [
            'pix'    => ['key' => 'another@test.com'],
            'amount' => 50,
        ];

        $command = RequestPixWithdrawCommand::fromHttp($accountId, $body);

        $this->assertSame($accountId, $command->accountId);
        $this->assertSame('PIX', $command->method);
        $this->assertSame('email', $command->pixType);
        $this->assertSame('another@test.com', $command->pixKey);
        $this->assertSame(50.0, $command->amount);
        $this->assertNull($command->schedule);
    }
}