<?php
declare(strict_types=1);

namespace HyperfTest\Unit\Domain\ValueObject;

use Core\Domain\ValueObject\PixKey;
use Core\Shared\Exception\BusinessException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class PixKeyTest extends TestCase
{
    public function testEmailValidAndMask(): void
    {
        $k = new PixKey(' Email ', '  diego@example.com ');
        $this->assertSame('email', $k->type());
        $this->assertSame('diego@example.com', $k->key());
        $this->assertSame('di***@example.com', $k->mask());
    }

    public function testEmailMaskEdgeCaseEmptyUserViaReflection(): void
    {
        $ref = new ReflectionClass(PixKey::class);
        $k = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('maskEmail');
        $m->setAccessible(true);

        $this->assertSame('***@example.com', $m->invoke($k, '@example.com'));
    }

    public function testPhoneValidAndMask(): void
    {
        $k = new PixKey('phone', '+55 (11) 99888-7777');
        $this->assertSame('+55*********77', $k->mask());
    }

    public function testPhoneMaskShortDigitsViaReflection(): void
    {
        $ref = new ReflectionClass(PixKey::class);
        $k = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('maskPhone');
        $m->setAccessible(true);

        $this->assertSame('****', $m->invoke($k, '12-34'));
        $this->assertSame('***', $m->invoke($k, '1(2)3'));
    }

    public function testPhoneInvalidTooShort(): void
    {
        $this->expectException(BusinessException::class);
        new PixKey('phone', '123-45');
    }

    public function testRandomValidAndMask(): void
    {
        $raw = 'ABCDEF1234567890';
        $k = new PixKey('random', $raw);
        $this->assertSame('ABC**********890', $k->mask());
    }

    public function testRandomMaskShortLenViaReflection(): void
    {
        $ref = new ReflectionClass(PixKey::class);
        $k = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('maskRandom');
        $m->setAccessible(true);

        $this->assertSame('******', $m->invoke($k, '123456'));

        $this->assertSame('123*567', $m->invoke($k, '1234567'));
    }

    public function testRandomInvalidTooShort(): void
    {
        $this->expectException(BusinessException::class);
        new PixKey('random', 'short_key_len');
    }



    public function testUnsupportedType(): void
    {
        $this->expectException(BusinessException::class);
        new PixKey('cpf', '123.456.789-00');
    }

    public function testEmailMaskEmptyLocalPart(): void
    {
        $ref = new ReflectionClass(PixKey::class);
        $k = $ref->newInstanceWithoutConstructor();
        $m = $ref->getMethod('maskEmail');
        $m->setAccessible(true);

        $this->assertSame('***@example.com', $m->invoke($k, '@example.com'));
    }

    public function testEmailInvalidThrows(): void
    {
        $this->expectException(BusinessException::class);
        new PixKey('email', 'not-an-email');
    }

    public function testMaskCoversEmptyUserBranchThroughPublicApi(): void
    {
        $k = new PixKey('email', 'ok@example.com');

        $ref = new ReflectionClass(PixKey::class);
        $prop = $ref->getProperty('key');
        $prop->setAccessible(true);
        $prop->setValue($k, '@example.com');

        $this->assertSame('***@example.com', $k->mask());
    }
}