<?php
declare(strict_types=1);

namespace Core\Domain\ValueObject;

use Core\Domain\Port\Clock;
use Core\Shared\Exception\BusinessException;
use DateTimeImmutable;
use DateTimeInterface;

class Schedule
{
    private ?DateTimeImmutable $scheduledAt;

    public function __construct(?DateTimeInterface $scheduledAt, Clock $clock)
    {
        $now = $clock->now();

        if ($scheduledAt === null) {
            $this->scheduledAt = null;
            return;
        }

        $dt = DateTimeImmutable::createFromInterface($scheduledAt);

        if ($dt <= $now) {
            throw new BusinessException('Schedule cannot be in the past.');
        }

        $max = $now->modify('+7 days');
        if ($dt > $max) {
            throw new BusinessException('Schedule cannot be more than 7 days in the future.');
        }

        $this->scheduledAt = $dt;
    }

    public static function immediate(Clock $clock): self
    {
        return new self(null, $clock);
    }

    public function isScheduled(): bool
    {
        return $this->scheduledAt !== null;
    }

    public function scheduledAt(): ?DateTimeImmutable
    {
        return $this->scheduledAt;
    }
}
