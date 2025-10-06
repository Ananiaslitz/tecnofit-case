<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Http;

use Core\Adapter\Out\Http\WithdrawController;
use Core\Application\Command\RequestPixWithdrawHandler;
use Core\Application\Query\ListWithdrawsHandler;
use Core\Application\Query\ListWithdrawsQuery;
use Core\Shared\Http\IdempotencyService;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\StreamInterface;

/**
 * @internal
 * @covers \Core\Adapter\Out\Http\WithdrawController
 */
final class WithdrawControllerTest extends TestCase
{
    private MockInterface|RequestPixWithdrawHandler $handler;
    private MockInterface|ListWithdrawsHandler $listHandler;
    private MockInterface|HttpResponse $response;
    private MockInterface|IdempotencyService $idempotency;
    private WithdrawController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = Mockery::mock(RequestPixWithdrawHandler::class);
        $this->listHandler = Mockery::mock(ListWithdrawsHandler::class);
        $this->response = Mockery::mock(HttpResponse::class);
        $this->idempotency = Mockery::mock(IdempotencyService::class);

        $this->controller = new WithdrawController(
            $this->handler,
            $this->listHandler,
            $this->response,
            $this->idempotency
        );
    }

    public function test_withdraw_success_path(): void
    {
        $accountId = 'acc-123';
        $idempotencyKey = 'key-abc';
        $body = ['amount' => 1000, 'pix_key' => 'test@test.com', 'pix_type' => 'email'];
        $request = $this->mockRequest($body, $idempotencyKey);
        $handlerResult = ['withdraw_id' => 'wid-xyz'];
        $this->idempotency->shouldReceive('get')->once()->with($idempotencyKey)->andReturn(null);
        $this->handler->shouldReceive('__invoke')->once()->andReturn($handlerResult);
        $this->idempotency->shouldReceive('fingerprint')->once()->andReturn('fingerprint-hash');
        $this->idempotency->shouldReceive('store')->once();
        $this->mockResponse(200, ['ok' => true] + $handlerResult, $idempotencyKey, 'false');
        $result = $this->controller->withdraw($request, $accountId);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_withdraw_returns_cached_idempotent_response(): void
    {
        $idempotencyKey = 'key-replayed';
        $request = $this->mockRequest([], $idempotencyKey);
        $cachedResponse = ['status' => 201, 'body' => ['ok' => true, 'withdraw_id' => 'wid-cached']];
        $this->idempotency->shouldReceive('get')->once()->with($idempotencyKey)->andReturn($cachedResponse);
        $this->mockResponse(201, $cachedResponse['body'], $idempotencyKey, 'true');
        $result = $this->controller->withdraw($request, 'acc-any');
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    /**
     * @dataProvider invalidArgumentProvider
     */
    public function test_withdraw_handles_invalid_argument_exception(string $errorMessage, int $expectedStatus, string $expectedCode): void
    {
        $accountId = 'acc-123';
        $idempotencyKey = 'key-invalid';
        $body = ['amount' => -100];
        $request = $this->mockRequest($body, $idempotencyKey);
        $exception = new \InvalidArgumentException($errorMessage);
        $expectedPayload = ['ok' => false, 'error' => $errorMessage, 'error_code' => $expectedCode];
        $this->idempotency->shouldReceive('get')->once()->with($idempotencyKey)->andReturn(null);
        $this->handler->shouldReceive('__invoke')->once()->andThrow($exception);
        $this->idempotency->shouldReceive('fingerprint')->once()->andReturn('fingerprint-hash');
        $this->idempotency->shouldReceive('store')->once();
        $this->mockResponse($expectedStatus, $expectedPayload, $idempotencyKey, 'false');
        $result = $this->controller->withdraw($request, $accountId);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_withdraw_handles_generic_throwable(): void
    {
        $accountId = 'acc-123';
        $idempotencyKey = 'key-server-error';
        $request = $this->mockRequest([], $idempotencyKey);
        $exception = new \Exception('Internal Server Error');
        $expectedPayload = ['ok' => false, 'error' => 'Internal Server Error'];
        $this->idempotency->shouldReceive('get')->once()->with($idempotencyKey)->andReturn(null);
        $this->handler->shouldReceive('__invoke')->once()->andThrow($exception);
        $this->idempotency->shouldNotReceive('store');
        $this->mockResponse(500, $expectedPayload, $idempotencyKey, 'false');
        $result = $this->controller->withdraw($request, $accountId);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_list_with_default_pagination(): void
    {
        $accountId = 'acc-456';
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getQueryParams')->andReturn([]);
        $listResult = ['total' => 2, 'items' => [['id' => 'w1'], ['id' => 'w2']]];

        $this->listHandler->shouldReceive('__invoke')
            ->once()
            ->with(Mockery::on(fn(ListWithdrawsQuery $q) => $q->accountId === $accountId && $q->page === 1 && $q->perPage === 20))
            ->andReturn($listResult);

        $finalResponse = Mockery::mock(ResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->andReturn($finalResponse);

        $result = $this->controller->list($request, $accountId);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public function test_list_with_custom_pagination(): void
    {
        $accountId = 'acc-789';
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('getQueryParams')->andReturn(['page' => 3, 'per_page' => 15]);
        $listResult = ['total' => 1, 'items' => [['id' => 'w3']]];

        $this->listHandler->shouldReceive('__invoke')
            ->once()
            ->with(Mockery::on(fn(ListWithdrawsQuery $q) => $q->accountId === $accountId && $q->page === 3 && $q->perPage === 15))
            ->andReturn($listResult);

        $finalResponse = Mockery::mock(ResponseInterface::class);

        $this->response->shouldReceive('json')
            ->once()
            ->andReturn($finalResponse);

        $result = $this->controller->list($request, $accountId);
        $this->assertInstanceOf(ResponseInterface::class, $result);
    }

    public static function invalidArgumentProvider(): array
    {
        return [
            'Schedule in the past' => ['schedule date cannot be in the past', 400, 'SCHEDULE_PAST'],
            'Schedule too far' => ['schedule cannot be more than 7 days in the future', 400, 'SCHEDULE_TOO_FAR'],
            'Generic invalid argument' => ['Invalid amount provided', 400, 'INVALID_ARGUMENT'],
        ];
    }

    private function mockRequest(array $body, string $idempotencyKey): Request
    {
        $request = Mockery::mock(Request::class);
        $stream = Mockery::mock(StreamInterface::class);
        $request->shouldReceive('getBody->getContents')->andReturn(json_encode($body));
        $request->shouldReceive('getHeaderLine')->with('Idempotency-Key')->andReturn($idempotencyKey);
        return $request;
    }

    private function mockResponse(int $status, array $payload, string $key, string $replayed): void
    {
        $psrResponse = Mockery::mock(ResponseInterface::class);

        $this->response->shouldReceive('json')->with($payload)->andReturn($psrResponse);

        $psrResponse->shouldReceive('withStatus')->with($status)->andReturnSelf();
        $psrResponse->shouldReceive('withHeader')->with('Idempotency-Key', $key)->andReturnSelf();
        $psrResponse->shouldReceive('withHeader')->with('Idempotency-Replayed', $replayed)->andReturnSelf();
    }
}