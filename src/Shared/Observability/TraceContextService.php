<?php

declare(strict_types=1);

namespace Core\Shared\Observability;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;

final class TraceContextService implements TraceContextServiceInterface
{
    public function getCurrentSpan(): SpanInterface
    {
        return Span::getCurrent();
    }
}