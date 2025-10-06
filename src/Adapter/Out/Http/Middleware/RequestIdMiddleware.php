<?php

namespace Core\Adapter\Out\Http\Middleware;

use Hyperf\Context\Context;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ramsey\Uuid\Uuid;

final class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $rid = $request->getHeaderLine('X-Request-Id') ?: Uuid::uuid4()->toString();
        Context::set('rid', $rid);
        $resp = $handler->handle($request);
        return $resp->withHeader('X-Request-Id', $rid);
    }
}