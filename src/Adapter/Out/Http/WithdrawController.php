<?php
declare(strict_types=1);

namespace Core\Adapter\Out\Http;

use Core\Application\Command\RequestPixWithdrawCommand;
use Core\Application\Command\RequestPixWithdrawHandler;
use Core\Application\Query\ListWithdrawsHandler;
use Core\Application\Query\ListWithdrawsQuery;
use Core\Shared\Exception\BusinessException;
use Core\Shared\Http\IdempotencyService;
use Hyperf\HttpServer\Contract\ResponseInterface as HttpResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;

final class WithdrawController
{
    public function __construct(
        private RequestPixWithdrawHandler $handler,
        private ListWithdrawsHandler $listHandler,
        private HttpResponse $response,
        private IdempotencyService $idempotency
    ) {
    }

    public function withdraw(Request $request, string $accountId): ResponseInterface
    {
        $body = json_decode((string) $request->getBody()->getContents(), true) ?? [];

        $key = $request->getHeaderLine('Idempotency-Key') ?: bin2hex(random_bytes(16));

        if ($cached = $this->idempotency->get($key)) {
            return $this->response
                ->json($cached['body'] ?? $cached)
                ->withStatus((int) ($cached['status'] ?? 200))
                ->withHeader('Idempotency-Key', $key)
                ->withHeader('Idempotency-Replayed', 'true');
        }

        try {
            $cmd    = RequestPixWithdrawCommand::fromHttp($accountId, $body);
            $result = ($this->handler)($cmd);

            $status  = 200;
            $headers = ['Idempotency-Key' => $key, 'Idempotency-Replayed' => 'false'];
            $payload = ['ok' => true] + $result;

            $this->idempotency->store($key, $this->idempotency->fingerprint([
                'route' => 'withdraw',
                'accountId' => $accountId,
                'body' => $body,
            ]), $status, $headers, $payload);

            return $this->response
                ->json($payload)
                ->withStatus($status)
                ->withHeader('Idempotency-Key', $key)
                ->withHeader('Idempotency-Replayed', 'false');

        } catch (\InvalidArgumentException|BusinessException $e) {
            [$code, $http] = $this->mapError($e->getMessage());
            $payload = ['ok' => false, 'error' => $e->getMessage(), 'error_code' => $code];

            $this->idempotency->store($key, $this->idempotency->fingerprint([
                'route' => 'withdraw',
                'accountId' => $accountId,
                'body' => $body,
            ]), $http, ['Idempotency-Key' => $key, 'Idempotency-Replayed' => 'false'], $payload);

            return $this->response
                ->json($payload)
                ->withStatus($http)
                ->withHeader('Idempotency-Key', $key)
                ->withHeader('Idempotency-Replayed', 'false');

        } catch (\Throwable $e) {
            return $this->response
                ->json(['ok' => false, 'error' => $e->getMessage()])
                ->withStatus(500)
                ->withHeader('Idempotency-Key', $key)
                ->withHeader('Idempotency-Replayed', 'false');
        }
    }

    private function mapError(string $message): array
    {
        $m = trim($message);

        if (stripos($m, 'cannot be in the past') !== false) {
            return ['SCHEDULE_PAST', 400];
        }

        if (stripos($m, 'more than 7 days') !== false) {
            return ['SCHEDULE_TOO_FAR', 400];
        }

        return ['INVALID_ARGUMENT', 400];
    }

    public function list(Request $request, string $accountId): ResponseInterface
    {
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $per  = (int) ($request->getQueryParams()['per_page'] ?? 20);

        $res = ($this->listHandler)(new ListWithdrawsQuery(
            accountId: $accountId,
            page: $page,
            perPage: $per
        ));

        return $this->response->json($res);
    }
}
