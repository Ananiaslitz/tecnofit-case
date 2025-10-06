<?php
namespace Core\Shared\Observability;

use OpenTelemetry\API\Trace\Span;

final class TraceContextLogProcessor
{
    public function __invoke(array $record): array
    {
        $span = Span::getCurrent();
        $ctx  = $span?->getContext();

        if ($ctx && $ctx->isValid()) {
            $record['context']['trace_id'] = $ctx->getTraceId();
            $record['context']['span_id']  = $ctx->getSpanId();
        } else {
            $record['context']['trace_id'] = '00000000000000000000000000000000';
            $record['context']['span_id']  = '0000000000000000';
        }
        return $record;
    }
}
