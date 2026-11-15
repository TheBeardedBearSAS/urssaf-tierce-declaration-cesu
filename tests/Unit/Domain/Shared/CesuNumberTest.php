<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

final class CesuNumberTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNumbers(): iterable
    {
        yield 'employer example' => ['Z0003752390009'];
        yield 'employee example' => ['00000123456789'];
        yield 'lower case kept' => ['abcdefghijklmn'];
        yield 'upper case' => ['ABCDEFGHIJKLMN'];
    }

    #[DataProvider('provideValidNumbers')]
    public function testValidNumbersAreKeptVerbatim(string $value): void
    {
        $number = CesuNumber::fromString($value);

        static::assertSame($value, $number->value());
        static::assertSame($value, (string) $number);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidNumbers(): iterable
    {
        yield 'empty' => [''];
        yield 'thirteen chars' => ['Z000375239000'];
        yield 'fifteen chars' => ['Z00037523900099'];
        yield 'punctuation' => ['Z000375239000-'];
        yield 'leading space (PDF typo)' => [' Z0003752390009'];
        yield 'trailing space' => ['Z0003752390009 '];
        yield 'trailing newline' => ["Z0003752390009\n"];
        yield 'leading newline' => ["\nZ0003752390009"];
        yield 'accent' => ['Z000375239000é'];
        yield 'multibyte chars' => [str_repeat('é', 14)];
    }

    #[DataProvider('provideInvalidNumbers')]
    public function testInvalidNumbersAreRejected(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('cesu_number', '14 alphanumeric characters'));

        CesuNumber::fromString($value);
    }

    public function testEquality(): void
    {
        $number = CesuNumber::fromString('Z0003752390009');

        static::assertTrue($number->equals(CesuNumber::fromString('Z0003752390009')));
        static::assertFalse($number->equals(CesuNumber::fromString('z0003752390009')));
        static::assertFalse($number->equals(CesuNumber::fromString('00000123456789')));
    }
}
