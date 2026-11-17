<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;

final class ApiErrorCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rawCodes(): iterable
    {
        yield 'clean' => ['ERR_METIER', 'ERR_METIER'];
        yield 'surrounding spaces' => [' ERR_METIER ', 'ERR_METIER'];
        yield 'inner space' => ['ERR_ METIER', 'ERR_METIER'];
        yield 'tabs and newlines' => ["\tERR_MET\nIER\r", 'ERR_METIER'];
        yield 'empty' => ['', ''];
    }

    #[DataProvider('rawCodes')]
    public function testItNormalisesWhitespace(string $raw, string $expected): void
    {
        static::assertSame($expected, ApiErrorCode::normalise($raw));
    }

    public function testFromCodeResolvesDirtyCodes(): void
    {
        static::assertSame(ApiErrorCode::Business, ApiErrorCode::fromCode(' ERR_METIER '));
        static::assertSame(ApiErrorCode::Business, ApiErrorCode::fromCode('ERR_ METIER'));
        static::assertSame(ApiErrorCode::InvalidParameter, ApiErrorCode::fromCode('PARAM_INVALIDE'));
    }

    public function testFromCodeReturnsNullForUnknownCodes(): void
    {
        static::assertNull(ApiErrorCode::fromCode('ERR_SOMETHING_NEW'));
        static::assertNull(ApiErrorCode::fromCode(''));
    }

    public function testCodesAreCaseSensitive(): void
    {
        static::assertNull(ApiErrorCode::fromCode('err_metier'));
    }

    public function testOnlyTheTwoTechnicalSpellingsAreTechnical(): void
    {
        $technical = array_filter(ApiErrorCode::cases(), static fn(ApiErrorCode $code): bool => $code->isTechnical());

        static::assertSame([ApiErrorCode::TechnicalError, ApiErrorCode::MandateTechnicalError], array_values($technical));
    }
}
