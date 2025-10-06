<?php

declare(strict_types=1);

namespace HyperfTest\Unit\Adapters\Out\Observability;

use Core\Adapter\Out\Observability\RichEvent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @covers \Core\Adapter\Out\Observability\RichEvent
 */
final class RichEventTest extends TestCase
{
    public function test_constructor_sets_timestamp_if_not_provided(): void
    {
        $event = new RichEvent(
            name: 'user.created',
            version: '1.0',
            level: 'info',
            message: 'User was created successfully.'
        );

        $this->assertInstanceOf(\DateTimeImmutable::class, $event->ts);
    }

    public function test_constructor_uses_provided_timestamp(): void
    {
        $specificDate = new \DateTimeImmutable('2025-10-05 21:30:00');

        $event = new RichEvent(
            name: 'user.login',
            version: '1.0',
            level: 'info',
            message: 'User logged in.',
            ts: $specificDate
        );

        $this->assertSame($specificDate, $event->ts);
    }

    public function test_to_array_formats_data_correctly(): void
    {
        $timestamp = new \DateTimeImmutable('2025-01-01T12:00:00+00:00');
        $event = new RichEvent(
            name: 'payment.processed',
            version: '1.1',
            level: 'warn',
            message: 'Payment processed with a delay.',
            attrs: ['payment_id' => 'pid-123', 'delay_ms' => 500],
            meta: ['service' => 'billing-api', 'host' => 'prod-server-01'],
            ts: $timestamp
        );

        $result = $event->toArray();

        $expected = [
            'ts'      => '2025-01-01T12:00:00+00:00',
            'event'   => [
                'name' => 'payment.processed',
                'version' => '1.1',
                'level' => 'warn',
                'message' => 'Payment processed with a delay.',
            ],
            'attrs'   => ['payment_id' => 'pid-123', 'delay_ms' => 500],
            'meta'    => ['service' => 'billing-api', 'host' => 'prod-server-01'],
        ];

        $this->assertEquals($expected, $result);
    }

    public function test_to_array_handles_empty_arrays_and_auto_timestamp(): void
    {
        $event = new RichEvent(
            name: 'system.boot',
            version: '2.0',
            level: 'info',
            message: 'System is booting up.'
        );

        $result = $event->toArray();

        $this->assertArrayHasKey('ts', $result);
        $this->assertIsString($result['ts']);
        $this->assertArrayHasKey('event', $result);
        $this->assertEquals([], $result['attrs']);
        $this->assertEquals([], $result['meta']);
    }
}