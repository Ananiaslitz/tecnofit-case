<?php
declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Time;

use Core\Adapter\Out\Time\SystemClock;
use PHPUnit\Framework\TestCase;

final class SystemClockTest extends TestCase
{
    private ?string $prevTz = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prevTz = getenv('APP_TIMEZONE') === false ? null : (string) getenv('APP_TIMEZONE');
    }

    protected function tearDown(): void
    {
        if ($this->prevTz === null) {
            putenv('APP_TIMEZONE'); // remove
        } else {
            putenv('APP_TIMEZONE=' . $this->prevTz);
        }
        parent::tearDown();
    }

    public function testFallbackTimezoneWhenEnvNotSet(): void
    {
        putenv('APP_TIMEZONE');
        $clock = new SystemClock();

        $tz = $clock->timezone();
        $this->assertInstanceOf(\DateTimeZone::class, $tz);
        $this->assertSame('America/Sao_Paulo', $tz->getName());

        $now = $clock->now();
        $this->assertInstanceOf(\DateTimeImmutable::class, $now);
        $this->assertSame('America/Sao_Paulo', $now->getTimezone()->getName());
    }

    public function testCustomTimezoneFromEnv(): void
    {
        putenv('APP_TIMEZONE=UTC');
        $clock = new SystemClock();

        $this->assertSame('UTC', $clock->timezone()->getName());
        $this->assertSame('UTC', $clock->now()->getTimezone()->getName());
    }

    public function testNowIsRecentAndMonotonicNonDecreasing(): void
    {
        putenv('APP_TIMEZONE=UTC');
        $clock = new SystemClock();

        $t1 = $clock->now();
        $t2 = $clock->now();

        $this->assertLessThanOrEqual(5, abs(time() - $t1->getTimestamp()));
        $this->assertGreaterThanOrEqual($t1->getTimestamp(), $t2->getTimestamp());
    }

    public function testTimezoneMatchesNowTimezone(): void
    {
        putenv('APP_TIMEZONE=America/New_York');
        $clock = new SystemClock();

        $this->assertSame(
            $clock->timezone()->getName(),
            $clock->now()->getTimezone()->getName()
        );
    }
}
