<?php

namespace Core\Shared\Http\Middleware;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class HttpServerTracingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $propagator = Globals::propagator();
        $ctx = $propagator->extract($request->getHeaders());

        $tracer = Globals::tracerProvider()->getTracer('http-server');
        $route = $request->getUri()->getPath();

        $span = $tracer->spanBuilder($request->getMethod() . ' ' . $route)
            ->setParent($ctx)
            ->setAttribute('http.request.method', $request->getMethod())
            ->setAttribute('url.path', $route)
            ->setAttribute('url.scheme', $request->getUri()->getScheme())
            ->setAttribute('server.address', $request->getUri()->getHost())
            ->setAttribute('user_agent.original', $request->getHeaderLine('User-Agent'))
            ->startSpan();

        $scope = $span->activate();

        try {
            $response = $handler->handle($request);
            $span->setAttribute('http.response.status_code', $response->getStatusCode());
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            throw $e;
        } finally {
            $traceId = $span->getContext()->getTraceId();
            $scope->detach();
            $span->end();
        }

        return $response->withHeader('x-trace-id', $traceId);
    }
}
