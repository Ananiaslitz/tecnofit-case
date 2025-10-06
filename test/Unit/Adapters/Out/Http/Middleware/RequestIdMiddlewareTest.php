<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Http\Middleware;

use Core\Adapter\Out\Http\Middleware\RequestIdMiddleware;
use Hyperf\Context\Context;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @internal
 * @covers \Core\Adapter\Out\Http\Middleware\RequestIdMiddleware
 */
final class RequestIdMiddlewareTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_process_adds_header_when_it_does_not_exist(): void
    {
        $generatedUuid = 'generated-uuid-1234';

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')->with('X-Request-Id')->andReturn('');

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')->once()->with('X-Request-Id', $generatedUuid)->andReturnSelf();

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($response);

        $uuidMock = Mockery::mock(UuidInterface::class);
        $uuidMock->shouldReceive('toString')->andReturn($generatedUuid);
        Mockery::mock('alias:' . Uuid::class)
            ->shouldReceive('uuid4')
            ->once()
            ->andReturn($uuidMock);

        Mockery::mock('alias:' . Context::class)
            ->shouldReceive('set')
            ->once()
            ->with('rid', $generatedUuid);

        $middleware = new RequestIdMiddleware();

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_process_uses_existing_header_when_it_exists(): void
    {
        $existingUuid = 'existing-uuid-5678';

        $request = Mockery::mock(ServerRequestInterface::class);
        $request->shouldReceive('getHeaderLine')->with('X-Request-Id')->andReturn($existingUuid);

        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('withHeader')->once()->with('X-Request-Id', $existingUuid)->andReturnSelf();

        $handler = Mockery::mock(RequestHandlerInterface::class);
        $handler->shouldReceive('handle')->once()->with($request)->andReturn($response);

        Mockery::mock('alias:' . Uuid::class)
            ->shouldNotReceive('uuid4');

        Mockery::mock('alias:' . Context::class)
            ->shouldReceive('set')
            ->once()
            ->with('rid', $existingUuid);

        $middleware = new RequestIdMiddleware();

        $result = $middleware->process($request, $handler);

        $this->assertInstanceOf(ResponseInterface::class, $result);
    }
}