<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Shared;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class CountryCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidCodes(): iterable
    {
        yield 'france' => ['100'];
        yield 'one char' => ['1'];
        yield 'two chars' => ['99'];
        yield 'letters accepted (no pattern published)' => ['FRA'];
        yield 'three multibyte chars' => ['ééé'];
    }

    #[DataProvider('provideValidCodes')]
    public function testValidCodesAreKeptVerbatim(string $value): void
    {
        $code = CountryCode::fromString($value);

        static::assertSame($value, $code->value());
        static::assertSame($value, (string) $code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBlankValues(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => [' '];
        yield 'tab and newline' => ["\t\n"];
    }

    #[DataProvider('provideBlankValues')]
    public function testBlankValuesAreRejected(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::empty('country_code'));

        CountryCode::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCharacters(): iterable
    {
        yield 'inner space' => ['1 0'];
        yield 'leading space' => [' 100'];
        yield 'trailing space' => ['100 '];
        yield 'trailing newline' => ["100\n"];
        yield 'invalid utf-8' => ["\xFF"];
        yield 'inner space wins over length' => ['1 000'];
    }

    #[DataProvider('provideInvalidCharacters')]
    public function testInvalidCharactersAreRejected(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidCharacters('country_code'));

        CountryCode::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTooLongValues(): iterable
    {
        yield 'four digits' => ['1000'];
        yield 'four multibyte chars' => ['éééé'];
    }

    #[DataProvider('provideTooLongValues')]
    public function testTooLongValuesAreRejected(string $value): void
    {
        $exception = DomainAssertions::assertViolationCode('country_code.too_long', static fn(): CountryCode => CountryCode::fromString($value));

        static::assertSame(['max_length' => 3], $exception->params());
    }

    public function testFrance(): void
    {
        static::assertSame('100', CountryCode::france()->value());
        static::assertTrue(CountryCode::france()->isFrance());
        static::assertTrue(CountryCode::fromString('100')->isFrance());
        static::assertFalse(CountryCode::fromString('101')->isFrance());
    }

    public function testEquality(): void
    {
        $code = CountryCode::fromString('100');

        static::assertTrue($code->equals(CountryCode::france()));
        static::assertFalse($code->equals(CountryCode::fromString('101')));
    }
}
