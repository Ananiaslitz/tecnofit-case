<?php

namespace Core\Shared\Observability;

interface RequestIdServiceInterface
{
    public function get(): ?string;
}