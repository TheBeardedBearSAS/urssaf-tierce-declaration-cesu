<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class ApprovalNumberTest extends TestCase
{
    public function testTwentyCharactersAreAccepted(): void
    {
        $number = ApprovalNumber::fromString('12345678910111213145');

        static::assertSame('12345678910111213145', $number->value());
    }

    public function testLengthIsCountedInCharacters(): void
    {
        static::assertSame(str_repeat('é', 20), ApprovalNumber::fromString(str_repeat('é', 20))->value());
    }

    public function testTooLongIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('approval_number', 20));

        ApprovalNumber::fromString(str_repeat('é', 21));
    }

    public function testBlankIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('approval_number'));

        ApprovalNumber::fromString("  \t");
    }

    public function testValueIsKeptVerbatim(): void
    {
        static::assertSame(' ab ', ApprovalNumber::fromString(' ab ')->value());
    }

    public function testEquality(): void
    {
        static::assertTrue(ApprovalNumber::fromString('A1')->equals(ApprovalNumber::fromString('A1')));
        static::assertFalse(ApprovalNumber::fromString('A1')->equals(ApprovalNumber::fromString('A2')));
    }

    public function testDebugOutputIsMasked(): void
    {
        $number = ApprovalNumber::fromString('SECRET-123');

        static::assertSame(['value' => '***'], $number->__debugInfo());
        static::assertStringNotContainsString('SECRET', print_r($number, true));
        static::assertFalse((new ReflectionClass($number))->hasMethod('__toString'));
    }
}
