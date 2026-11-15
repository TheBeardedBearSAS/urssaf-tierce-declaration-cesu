<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecimal;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class JsonDecimalTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function validLiterals(): iterable
    {
        yield 'zero' => ['0'];
        yield 'integer' => ['2000'];
        yield 'decimal' => ['11.02'];
        yield 'trailing zero' => ['0.30'];
        yield 'negative' => ['-1.5'];
    }

    #[DataProvider('validLiterals')]
    public function testItKeepsValidLiteralsVerbatim(string $literal): void
    {
        static::assertSame($literal, JsonDecimal::fromString($literal)->literal());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLiterals(): iterable
    {
        yield 'empty' => [''];
        yield 'leading zero' => ['01'];
        yield 'exponent' => ['1e3'];
        yield 'comma' => ['1,5'];
        yield 'trailing dot' => ['1.'];
        yield 'text' => ['abc'];
        yield 'trailing newline' => ["1\n"];
        yield 'injection' => ['1,"x":2'];
    }

    #[DataProvider('invalidLiterals')]
    public function testItRejectsAnythingThatIsNotAJsonNumber(string $literal): void
    {
        $exception = DomainAssertions::assertViolationCode('json_decimal.invalid', static fn(): JsonDecimal => JsonDecimal::fromString($literal));

        static::assertSame('The value is not a valid JSON number literal.', $exception->getMessage());
    }
}
