<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\In\Cli;

use Core\Adapter\In\Cli\ProcessScheduledWithdrawalsCommand;
use Core\Application\Command\ProcessScheduledWithdrawalsHandler;
use Mockery;
use Mockery\MockInterface;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\ScopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Adapter\In\Cli\ProcessScheduledWithdrawalsCommand
 */
final class ProcessScheduledWithdrawalsCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_handle_success_path(): void
    {
        $processedCount = 5;

        /** @var ProcessScheduledWithdrawalsHandler|MockInterface $handler */
        $handler = Mockery::mock(ProcessScheduledWithdrawalsHandler::class);
        $handler->shouldReceive('process')->once()->andReturn($processedCount);

        [$span, $scope] = $this->mockOpenTelemetry();
        $span->shouldReceive('setAttribute')->once()->with('withdraw.processed_count', $processedCount);
        $span->shouldNotReceive('recordException');
        $span->shouldNotReceive('setStatus');

        /** @var ProcessScheduledWithdrawalsCommand|MockInterface $command */
        $command = Mockery::mock(ProcessScheduledWithdrawalsCommand::class . '[line]', [$handler])->makePartial();
        $command->shouldReceive('line')->once()->with("processed={$processedCount}");

        $command->handle();
        $this->assertTrue(true);
    }

    public function test_handle_exception_path(): void
    {
        $exception = new \RuntimeException('Database connection failed');

        /** @var ProcessScheduledWithdrawalsHandler|MockInterface $handler */
        $handler = Mockery::mock(ProcessScheduledWithdrawalsHandler::class);
        $handler->shouldReceive('process')->once()->andThrow($exception);

        [$span, $scope] = $this->mockOpenTelemetry();
        $span->shouldReceive('recordException')->once()->with($exception);
        $span->shouldReceive('setStatus')->once()->with(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span->shouldNotReceive('setAttribute');

        /** @var ProcessScheduledWithdrawalsCommand|MockInterface $command */
        $command = Mockery::mock(ProcessScheduledWithdrawalsCommand::class . '[line]', [$handler])->makePartial();
        $command->shouldNotReceive('line');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection failed');

        $command->handle();
    }

    private function mockOpenTelemetry(): array
    {
        $tracerProvider = Mockery::mock(TracerProviderInterface::class);
        $tracer = Mockery::mock(TracerInterface::class);
        $spanBuilder = Mockery::mock(SpanBuilderInterface::class);
        $span = Mockery::mock(SpanInterface::class);
        $scope = Mockery::mock(ScopeInterface::class);

        $globalsMock = Mockery::mock('alias:' . Globals::class);
        $globalsMock->shouldReceive('tracerProvider')->andReturn($tracerProvider);

        $tracerProvider->shouldReceive('getTracer')->with('cli')->andReturn($tracer);
        $tracer->shouldReceive('spanBuilder')->with('withdrawals.process_batch')->andReturn($spanBuilder);
        $spanBuilder->shouldReceive('startSpan')->andReturn($span);
        $span->shouldReceive('activate')->andReturn($scope);

        $scope->shouldReceive('detach')->once();
        $span->shouldReceive('end')->once();

        return [$span, $scope];
    }
}