<?php

namespace Core\Shared\Observability;

use Hyperf\Context\Context;

final class RequestIdService implements RequestIdServiceInterface
{
    public function get(): ?string
    {
        return Context::get('rid');
    }
}