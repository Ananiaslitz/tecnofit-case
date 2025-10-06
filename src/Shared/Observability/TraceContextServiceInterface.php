<?php

namespace Core\Shared\Observability;

use OpenTelemetry\API\Trace\SpanInterface;

interface TraceContextServiceInterface
{
    public function getCurrentSpan(): SpanInterface;
}