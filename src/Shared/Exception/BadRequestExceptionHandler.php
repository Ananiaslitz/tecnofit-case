<?php

namespace Core\Shared\Exception;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use InvalidArgumentException;
use Hyperf\HttpMessage\Stream\SwooleStream;

class BadRequestExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if (! $throwable instanceof InvalidArgumentException) {
            return $response;
        }

        $this->stopPropagation();

        $payload = json_encode([
            'ok' => false,
            'error' => $throwable->getMessage(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $response
            ->withStatus(400)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody(new SwooleStream($payload));
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof InvalidArgumentException;
    }
}
