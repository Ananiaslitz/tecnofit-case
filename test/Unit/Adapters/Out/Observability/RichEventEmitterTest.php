<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Observability;

use Core\Adapter\Out\Observability\RichEvent;
use Core\Adapter\Out\Observability\RichEventEmitter;
use Core\Shared\Observability\RequestIdServiceInterface;
use Core\Shared\Observability\TraceContextServiceInterface;
use Mockery;
use Mockery\MockInterface;
use Monolog\Logger;
use OpenTelemetry\API\Trace\SpanContext;
use OpenTelemetry\API\Trace\SpanInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @internal
 * @covers \Core\Adapter\Out\Observability\RichEventEmitter
 */
final class RichEventEmitterTest extends TestCase
{
    private MockInterface|LoggerInterface $logger;
    private MockInterface|TraceContextServiceInterface $traceContextService;
    private MockInterface|RequestIdServiceInterface $requestIdService;
    private RichEventEmitter $emitter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->traceContextService = Mockery::mock(TraceContextServiceInterface::class);
        $this->requestIdService = Mockery::mock(RequestIdServiceInterface::class);

        $this->emitter = new RichEventEmitter(
            $this->logger,
            $this->traceContextService,
            $this->requestIdService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        putenv('OTEL_SERVICE_NAME');
        putenv('APP_ENV');
    }

    public function test_emit_enriches_event_with_context_and_redacts_pii(): void
    {
        $traceId = 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4';
        $spanId = 'a1b2c3d4e5f6a1b2';
        $requestId = 'req-xyz-789';

        $spanMock = Mockery::mock(SpanInterface::class);
        $spanMock->shouldReceive('getContext')->andReturn(SpanContext::create($traceId, $spanId));
        $this->traceContextService->shouldReceive('getCurrentSpan')->andReturn($spanMock);
        $this->requestIdService->shouldReceive('get')->andReturn($requestId);

        putenv('OTEL_SERVICE_NAME=test-service');
        putenv('APP_ENV=testing');

        $originalEvent = new RichEvent(
            name: 'pix.requested',
            version: '1.0',
            level: 'info',
            message: 'PIX withdrawal requested.',
            attrs: ['pix.key' => 'test@example.com', 'amount' => 1000]
        );

        $this->logger->shouldReceive('log')
            ->once()
            ->with(Logger::INFO, 'PIX withdrawal requested.', Mockery::on(function (array $enriched) use ($traceId, $spanId, $requestId) {
                $this->assertSame($traceId, $enriched['meta']['trace_id']);
                $this->assertSame($spanId, $enriched['meta']['span_id']);
                $this->assertSame($requestId, $enriched['meta']['request_id']);
                $this->assertArrayNotHasKey('pix.key', $enriched['attrs']);
                $this->assertArrayHasKey('pix.key_hash', $enriched['attrs']);
                return true;
            }));

        $this->emitter->emit($originalEvent);
    }

    public function test_emit_handles_invalid_trace_context(): void
    {
        $spanMock = Mockery::mock(SpanInterface::class);
        $spanMock->shouldReceive('getContext')->andReturn(SpanContext::getInvalid());
        $this->traceContextService->shouldReceive('getCurrentSpan')->andReturn($spanMock);
        $this->requestIdService->shouldReceive('get')->andReturn(null);

        $originalEvent = new RichEvent('test', '1.0', 'info', 'test message');

        $this->logger->shouldReceive('log')
            ->once()
            ->with(Logger::INFO, 'test message', Mockery::on(function (array $enriched) {
                $this->assertSame('00000000000000000000000000000000', $enriched['meta']['trace_id']);
                $this->assertSame('0000000000000000', $enriched['meta']['span_id']);
                return true;
            }));

        $this->emitter->emit($originalEvent);
    }

    /**
     * @dataProvider logLevelProvider
     */
    public function test_emit_maps_log_levels_correctly(string $inputLevel, int $expectedMonologLevel): void
    {
        $spanMock = Mockery::mock(SpanInterface::class);
        $spanMock->shouldReceive('getContext')->andReturn(SpanContext::getInvalid());
        $this->traceContextService->shouldReceive('getCurrentSpan')->andReturn($spanMock);
        $this->requestIdService->shouldReceive('get')->andReturn(null);

        $originalEvent = new RichEvent('test', '1.0', $inputLevel, 'level test');

        $this->logger->shouldReceive('log')
            ->once()
            ->with($expectedMonologLevel, 'level test', Mockery::any());

        $this->emitter->emit($originalEvent);
        $this->assertTrue(true);
    }

    public static function logLevelProvider(): array
    {
        return [
            'standard info' => ['info', Logger::INFO],
            'standard warn' => ['warn', Logger::WARNING],
            'alias warning' => ['warning', Logger::WARNING],
            'uppercase error' => ['ERROR', Logger::ERROR],
            'critical level' => ['critical', Logger::CRITICAL],
            'unknown level falls back to info' => ['unknown_level', Logger::INFO],
        ];
    }
}