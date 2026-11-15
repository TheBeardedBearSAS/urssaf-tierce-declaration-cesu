<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DepartmentalCouncil;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class DepartmentalCouncilTest extends TestCase
{
    /**
     * @return list<string>
     */
    private static function publishedCodes(): array
    {
        $codes = array_map(
            static fn(int $number): string => \sprintf('%03d', $number),
            array_values(array_diff(range(1, 95), [20])),
        );

        return [...$codes, '02A', '02B', '971', '972', '973', '974', '976'];
    }

    public function testThePublishedListHasOneHundredAndOneCodes(): void
    {
        static::assertCount(101, self::publishedCodes());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideAcceptedCodes(): iterable
    {
        foreach (self::publishedCodes() as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('provideAcceptedCodes')]
    public function testEveryPublishedCodeIsAccepted(string $code): void
    {
        $council = DepartmentalCouncil::fromString($code);

        static::assertSame($code, $council->value());
        static::assertSame($code, (string) $council);
    }

    public function testCodeIsUpperCased(): void
    {
        static::assertSame('02A', DepartmentalCouncil::fromString('02a')->value());
        static::assertSame('02B', DepartmentalCouncil::fromString('02b')->value());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideRefusedCodes(): iterable
    {
        yield '020 does not exist' => ['020'];
        yield '000' => ['000'];
        yield '096' => ['096'];
        yield '975 does not exist' => ['975'];
        yield '977' => ['977'];
        yield '02C' => ['02C'];
        yield 'two digits' => ['01'];
        yield 'empty' => [''];
        yield 'leading space' => [' 001'];
        yield 'trailing newline' => ["001\n"];
    }

    #[DataProvider('provideRefusedCodes')]
    public function testUnpublishedCodesAreRefused(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('departmental_council'));

        DepartmentalCouncil::fromString($code);
    }

    public function testEquality(): void
    {
        static::assertTrue(DepartmentalCouncil::fromString('971')->equals(DepartmentalCouncil::fromString('971')));
        static::assertFalse(DepartmentalCouncil::fromString('971')->equals(DepartmentalCouncil::fromString('972')));
    }
}
