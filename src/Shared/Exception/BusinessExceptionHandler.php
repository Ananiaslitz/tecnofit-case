<?php
declare(strict_types=1);

namespace Core\Shared\Exception;

use Hyperf\ExceptionHandler\ExceptionHandler;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class BusinessExceptionHandler extends ExceptionHandler
{
    public function handle(Throwable $throwable, ResponseInterface $response)
    {
        if (! $throwable instanceof BusinessException) {
            return $response;
        }

        $this->stopPropagation();

        $payload = [
            'ok' => false,
            'error' => $throwable->getMessage(),
            'error_code' => 'INVALID_ARGUMENT',
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(400);
    }

    public function isValid(Throwable $throwable): bool
    {
        return $throwable instanceof BusinessException;
    }
}
