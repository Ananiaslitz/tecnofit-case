<?php

namespace Core\Adapter\Out\Observability;

use Core\Shared\Observability\RequestIdServiceInterface;
use Core\Shared\Observability\TraceContextServiceInterface;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class RichEventEmitter
{
    public function __construct(
        private LoggerInterface $logger,
        private TraceContextServiceInterface $traceContextService,
        private RequestIdServiceInterface $requestIdService
    ) {}

    public function emit(RichEvent $e): void
    {
        $span = $this->traceContextService->getCurrentSpan();
        $ctx = $span->getContext();
        $traceId = $ctx->isValid() ? $ctx->getTraceId() : '00000000000000000000000000000000';
        $spanId  = $ctx->isValid() ? $ctx->getSpanId()  : '0000000000000000';

        $requestId = $this->requestIdService->get();

        $enriched = $e->toArray();
        $enriched['meta'] = array_filter($enriched['meta'] + [
                'trace_id'     => $traceId,
                'span_id'      => $spanId,
                'request_id'   => $requestId,
                'service.name' => getenv('OTEL_SERVICE_NAME') ?: 'tecnofit-saque',
                'env'          => getenv('APP_ENV') ?: 'dev',
                'host'         => gethostname(),
            ]);

        if (isset($enriched['attrs']['pix.key'])) {
            $enriched['attrs']['pix.key_hash'] = substr(hash('sha256', $enriched['attrs']['pix.key']), 0, 16);
            unset($enriched['attrs']['pix.key']);
        }

        $map = [
            'debug' => Logger::DEBUG, 'info' => Logger::INFO, 'notice' => Logger::NOTICE,
            'warn' => Logger::WARNING, 'warning' => Logger::WARNING, 'error' => Logger::ERROR,
            'critical' => Logger::CRITICAL, 'alert' => Logger::ALERT, 'emergency' => Logger::EMERGENCY,
        ];
        $level = strtolower($e->level ?? 'info');
        $monologLevel = $map[$level] ?? Logger::INFO;

        $this->logger->log($monologLevel, $e->message, $enriched);
    }
}
