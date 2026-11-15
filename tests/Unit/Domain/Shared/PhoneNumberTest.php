<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;

final class PhoneNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNumbers(): iterable
    {
        yield 'official example' => ['0412345678'];
        yield 'zeros' => ['0000000000'];
        yield 'not starting with zero' => ['9876543210'];
    }

    #[DataProvider('provideValidNumbers')]
    public function testValidNumbers(string $value): void
    {
        static::assertSame($value, PhoneNumber::fromString($value)->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'nine digits' => ['041234567'];
        yield 'eleven digits' => ['04123456789'];
        yield 'letter' => ['041234567a'];
        yield 'international prefix' => ['+3341234567'];
        yield 'spaces' => ['04 12 34 56 78'];
        yield 'leading space' => [' 0412345678'];
        yield 'trailing space' => ['0412345678 '];
        yield 'trailing newline' => ["0412345678\n"];
        yield 'leading newline' => ["\n0412345678"];
    }

    #[DataProvider('provideInvalidNumbers')]
    public function testInvalidNumbersAreRejected(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('phone_number', '10 digits'));

        PhoneNumber::fromString($value);
    }

    public function testEquality(): void
    {
        $phone = PhoneNumber::fromString('0412345678');

        static::assertTrue($phone->equals(PhoneNumber::fromString('0412345678')));
        static::assertFalse($phone->equals(PhoneNumber::fromString('0412345679')));
    }

    public function testDebugOutputIsMasked(): void
    {
        $phone = PhoneNumber::fromString('0412345678');

        static::assertSame(['value' => '***'], $phone->__debugInfo());
        static::assertStringNotContainsString('0412345678', print_r($phone, true));
    }
}
