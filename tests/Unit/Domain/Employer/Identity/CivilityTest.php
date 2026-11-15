<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Identity;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class CivilityTest extends TestCase
{
    public function testKnownCodes(): void
    {
        static::assertSame(Civility::Male, Civility::fromCode('1'));
        static::assertSame(Civility::Female, Civility::fromCode('2'));
        static::assertSame('1', Civility::Male->value);
        static::assertSame('2', Civility::Female->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownCodes(): iterable
    {
        yield 'empty' => [''];
        yield 'zero' => ['0'];
        yield 'three' => ['3'];
        yield 'padded' => [' 1'];
        yield 'text' => ['M'];
    }

    #[DataProvider('provideUnknownCodes')]
    public function testUnknownCodes(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('civility'));

        Civility::fromCode($code);
    }
}
