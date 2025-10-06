<?php
declare(strict_types=1);

namespace HyperfTest\Unit;

use Core\Domain\Port\Clock;
use Core\Domain\ValueObject\Schedule;
use Core\Shared\Exception\BusinessException;
use DateTimeImmutable;
use HyperfTest\TestCase;
use Mockery;

final class ScheduleTest extends TestCase
{
    public function test_cannot_schedule_in_the_past(): void
    {
        $this->expectException(BusinessException::class);

        $now = new DateTimeImmutable('2025-10-03 12:00:00');
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->andReturn($now);

        new Schedule($now->modify('-1 minute'), $clock);
    }

    public function test_cannot_schedule_more_than_7_days(): void
    {
        $this->expectException(BusinessException::class);

        $now = new DateTimeImmutable('2025-10-03 12:00:00');
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->andReturn($now);

        new Schedule($now->modify('+8 days'), $clock);
    }

    public function test_valid_schedule_plus_one_minute(): void
    {
        $now = new DateTimeImmutable('2025-10-03 12:00:00');
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->andReturn($now);

        $s = new Schedule($now->modify('+1 minute'), $clock);

        $this->assertTrue($s->isScheduled());
        $this->assertSame('2025-10-03 12:01:00', $s->scheduledAt()->format('Y-m-d H:i:s'));
    }

    public function test_immediate_factory(): void
    {
        $now = new DateTimeImmutable('2025-10-03 12:00:00');
        $clock = Mockery::mock(Clock::class);
        $clock->shouldReceive('now')->andReturn($now);

        $s = Schedule::immediate($clock);

        $this->assertFalse($s->isScheduled());
        $this->assertNull($s->scheduledAt());
    }
}
