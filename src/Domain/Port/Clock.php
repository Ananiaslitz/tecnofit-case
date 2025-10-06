<?php

namespace Core\Domain\Port;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
