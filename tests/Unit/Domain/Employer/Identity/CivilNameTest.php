<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Identity;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * One method per rule of the value object.
 *
 * @mago-expect lint:too-many-methods
 */
final class CivilNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFactories(): iterable
    {
        yield 'birth name' => ['birthName', 'birth_name'];
        yield 'usage name' => ['usageName', 'usage_name'];
        yield 'first name' => ['firstName', 'first_name'];
    }

    #[DataProvider('provideFactories')]
    public function testEveryFactoryReportsItsSubject(string $factory, string $subject): void
    {
        static::assertSame('Dupond', self::create($factory, 'Dupond')->value());
        static::assertSame($subject . '.empty', self::codeOf($factory, ''));
        static::assertSame($subject . '.empty', self::codeOf($factory, '   '));
        static::assertSame($subject . '.invalid_characters', self::codeOf($factory, "a\tb"));
        static::assertSame($subject . '.too_long', self::codeOf($factory, str_repeat('a', 31)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNames(): iterable
    {
        yield 'plain' => ['Dupond'];
        yield 'no pattern: digits and symbols' => ['Dup0nd_3.#'];
        yield 'accents and letters outside the 010 pattern' => ['Øystein Ǣ'];
        yield 'inner and edge spaces' => [' De la Paix '];
        yield 'thirty characters' => [str_repeat('a', 30)];
        yield 'thirty multibyte characters' => [str_repeat('é', 30)];
    }

    #[DataProvider('provideValidNames')]
    public function testValidNamesAreKeptVerbatim(string $value): void
    {
        static::assertSame($value, CivilName::birthName($value)->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideControlCharacters(): iterable
    {
        yield 'tab inside' => ["Du\tpond"];
        yield 'newline last' => ["Dupond\n"];
        yield 'newline first' => ["\nDupond"];
        yield 'nul' => ["Du\0pond"];
        yield 'invalid utf-8' => ["Dup\xFFond"];
        yield 'too long and invalid wins' => [str_repeat("\t", 5) . str_repeat('a', 31)];
    }

    #[DataProvider('provideControlCharacters')]
    public function testControlCharactersAreRefused(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidCharacters('birth_name'));

        CivilName::birthName($value);
    }

    public function testBlankNamesAreEmptyNotInvalidCharacters(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('usage_name'));

        CivilName::usageName("\t\n");
    }

    public function testTooLongCountsCharactersNotBytes(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('first_name', 30));

        CivilName::firstName(str_repeat('é', 31));
    }

    public function testEquality(): void
    {
        $name = CivilName::birthName('Dupond');

        static::assertTrue($name->equals(CivilName::firstName('Dupond')));
        static::assertFalse($name->equals(CivilName::birthName('Dupont')));
    }

    public function testDebugOutputIsMasked(): void
    {
        $name = CivilName::birthName('Dupond');

        static::assertSame(['value' => '***'], $name->__debugInfo());
        static::assertStringNotContainsString('Dupond', print_r($name, true));
    }

    private static function codeOf(string $factory, string $value): string
    {
        try {
            self::create($factory, $value);
        } catch (InvalidValue $invalidValue) {
            return $invalidValue->violationCode();
        }

        static::fail('Expected an InvalidValue.');
    }

    /**
     * @throws InvalidValue
     */
    private static function create(string $factory, string $value): CivilName
    {
        return match ($factory) {
            'birthName' => CivilName::birthName($value),
            'usageName' => CivilName::usageName($value),
            'firstName' => CivilName::firstName($value),
            default => throw new LogicException('Unknown factory.'),
        };
    }
}
