<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Address;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\AddressText;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class AddressTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, non-negative-int}>
     */
    public static function provideFactories(): iterable
    {
        yield 'street name' => ['streetName', 'street_name', 21];
        yield 'complement' => ['complement', 'address_complement', 32];
        yield 'locality' => ['locality', 'locality', 32];
    }

    /**
     * @param non-negative-int $max
     */
    #[DataProvider('provideFactories')]
    public function testLimitsAndSubjects(string $factory, string $subject, int $max): void
    {
        static::assertSame(str_repeat('é', $max), self::create($factory, str_repeat('é', $max))->value());
        self::assertFailure(InvalidValue::tooLong($subject, $max), $factory, str_repeat('é', $max + 1));
        self::assertFailure(InvalidValue::empty($subject), $factory, '');
        self::assertFailure(InvalidValue::empty($subject), $factory, "  \t ");
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, "a\nb");
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, "a\x00");
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, "\xFFa");
        self::assertFailure(InvalidValue::invalidCharacters($subject), $factory, str_repeat("a\n", $max));
    }

    public function testTextIsKeptVerbatim(): void
    {
        static::assertSame(' De la Paix ', AddressText::streetName(' De la Paix ')->value());
        static::assertSame("Bât. C / n°2", AddressText::complement("Bât. C / n°2")->value());
    }

    public function testEqualityAndMasking(): void
    {
        $text = AddressText::locality('Les Granges');

        static::assertTrue($text->equals(AddressText::complement('Les Granges')));
        static::assertFalse($text->equals(AddressText::locality('Les Granges ')));
        static::assertSame(['value' => '***'], $text->__debugInfo());
    }

    private static function assertFailure(InvalidValue $expected, string $factory, string $value): void
    {
        try {
            self::create($factory, $value);
        } catch (InvalidValue $actual) {
            static::assertSame($expected->violationCode(), $actual->violationCode());
            static::assertSame($expected->params(), $actual->params());
            static::assertSame($expected->getMessage(), $actual->getMessage());

            return;
        }

        static::fail('Expected an InvalidValue.');
    }

    /**
     * @throws InvalidValue
     */
    private static function create(string $factory, string $value): AddressText
    {
        return match ($factory) {
            'streetName' => AddressText::streetName($value),
            'complement' => AddressText::complement($value),
            'locality' => AddressText::locality($value),
            default => throw new LogicException('Unknown factory.'),
        };
    }
}
