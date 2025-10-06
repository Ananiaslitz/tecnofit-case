<?php

namespace HyperfTest\Unit;

use Core\Domain\ValueObject\PixKey;
use PHPUnit\Framework\TestCase;

final class PixMaskTest extends TestCase
{
    public function test_email_mask(): void
    {
        $pix = new PixKey('email', 'fulano@email.com');
        $masked = $pix->mask();

        $this->assertNotSame('fulano@email.com', $masked);
        $this->assertStringContainsString('@', $masked);
        $this->assertMatchesRegularExpression('/^\S+@\S+$/', $masked);
    }

    public function test_phone_mask(): void
    {
        $pix = new PixKey('phone', '+55 11 91234-5678');
        $masked = $pix->mask();

        $this->assertNotSame('+55 11 91234-5678', $masked);

        $this->assertTrue(strlen($masked) >= 6);
    }

    public function test_random_key_mask(): void
    {
        $key = 'e3b0c442-98fc-1c14-9afb-4c8996fb9242';
        $pix = new PixKey('random', $key);
        $masked = $pix->mask();

        $this->assertNotSame($key, $masked);

        $this->assertTrue(strlen($masked) === strlen($key));
    }
}
