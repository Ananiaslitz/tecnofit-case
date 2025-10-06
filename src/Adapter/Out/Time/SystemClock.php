<?php
namespace Core\Adapter\Out\Time;

use Core\Domain\Port\Clock;

final class SystemClock implements Clock
{
    private \DateTimeZone $tz;

    public function __construct()
    {
        $this->tz = new \DateTimeZone(getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo');
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->tz);
    }

    public function timezone(): \DateTimeZone
    {
        return $this->tz;
    }
}
