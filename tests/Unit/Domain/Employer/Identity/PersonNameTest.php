<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Identity;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * One method per rule of the value object.
 *
 * @mago-expect lint:too-many-methods
 */
final class PersonNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideFactories(): iterable
    {
        yield 'last name' => ['lastName', 'last_name'];
        yield 'first name' => ['firstName', 'first_name'];
        yield 'birth name' => ['birthName', 'birth_name'];
        yield 'usage name' => ['usageName', 'usage_name'];
        yield 'birth city' => ['birthCity', 'birth_city'];
    }

    #[DataProvider('provideFactories')]
    public function testEveryFactoryReportsItsSubject(string $factory, string $subject): void
    {
        static::assertSame('Dupond', self::create($factory, 'Dupond')->value());
        static::assertSame($subject . '.empty', self::codeOf($factory, ''));
        static::assertSame($subject . '.invalid_characters', self::codeOf($factory, 'Dup0nd'));
        static::assertSame($subject . '.too_long', self::codeOf($factory, str_repeat('a', 31)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideValidNames(): iterable
    {
        yield 'plain' => ['Dupond'];
        yield 'accents' => ['Éléonore'];
        yield 'accents of the pattern, part 1' => ['áÁàÀâÂäÄéÉèÈëËêÊìÌíÍïÏîÎ'];
        yield 'accents of the pattern, part 2' => ['ñÑòÒóÓôÔöÖùÙúÚüÜûÛçÇýÝÿŸ'];
        yield 'ligatures' => ['ŒœÆæ'];
        yield 'hyphen' => ['Jean-Pierre'];
        yield 'apostrophe' => ["D'Artagnan"];
        yield 'inner space' => ['De la Paix'];
        yield 'trailing newline (the class contains \\s)' => ["Dupond\n"];
        yield 'thirty characters' => [str_repeat('a', 30)];
        yield 'thirty multibyte characters' => [str_repeat('é', 30)];
    }

    #[DataProvider('provideValidNames')]
    public function testValidNamesAreKeptVerbatim(string $value): void
    {
        static::assertSame($value, PersonName::lastName($value)->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidCharacters(): iterable
    {
        yield 'digit' => ['Dup0nd'];
        yield 'dot' => ['Dupond.'];
        yield 'letter outside the pattern first' => ['øDupond'];
        yield 'letter outside the pattern last' => ['Dupondø'];
        yield 'invalid utf-8' => ["Dup\xFFond"];
        yield 'underscore' => ['Du_pond'];
        yield 'too long and invalid wins' => [str_repeat('1', 31)];
    }

    #[DataProvider('provideInvalidCharacters')]
    public function testInvalidCharacters(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidCharacters('last_name'));

        PersonName::lastName($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideBlankNames(): iterable
    {
        yield 'empty' => [''];
        yield 'spaces' => ['   '];
        yield 'tab and newline' => ["\t\n"];
    }

    #[DataProvider('provideBlankNames')]
    public function testBlankNamesAreEmptyNotInvalidCharacters(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::empty('last_name'));

        PersonName::lastName($value);
    }

    public function testTooLongCountsCharactersNotBytes(): void
    {
        $this->expectExceptionObject(InvalidValue::tooLong('last_name', 30));

        PersonName::lastName(str_repeat('é', 31));
    }

    public function testEquality(): void
    {
        $name = PersonName::lastName('Dupond');

        static::assertTrue($name->equals(PersonName::firstName('Dupond')));
        static::assertFalse($name->equals(PersonName::lastName('Dupont')));
    }

    public function testDebugOutputIsMasked(): void
    {
        $name = PersonName::lastName('Dupond');

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
    private static function create(string $factory, string $value): PersonName
    {
        return match ($factory) {
            'lastName' => PersonName::lastName($value),
            'firstName' => PersonName::firstName($value),
            'birthName' => PersonName::birthName($value),
            'usageName' => PersonName::usageName($value),
            'birthCity' => PersonName::birthCity($value),
            default => throw new LogicException('Unknown factory.'),
        };
    }
}
