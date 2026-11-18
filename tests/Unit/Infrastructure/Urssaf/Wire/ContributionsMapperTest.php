<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\ContributionsMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;

final class ContributionsMapperTest extends TestCase
{
    public function testItMapsBothAmountsWithoutFloatDrift(): void
    {
        $contributions = (new ContributionsMapper())->map(JsonDecoder::decode('{"cotisationsPatronales":63.1,"cotisationsSalariales":20.09}'));

        static::assertSame(6310, $contributions->employerContributions()->cents());
        static::assertSame(2009, $contributions->employeeContributions()->cents());
    }

    public function testTheAmountsAreNotSwapped(): void
    {
        $contributions = (new ContributionsMapper())->map(JsonDecoder::decode('{"cotisationsPatronales":100,"cotisationsSalariales":50}'));

        static::assertSame(10_000, $contributions->employerContributions()->cents());
        static::assertSame(5000, $contributions->employeeContributions()->cents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'employer amount missing' => ['{"cotisationsSalariales":1}', 'field "cotisationsPatronales" is missing or not a number'];
        yield 'employee amount missing' => ['{"cotisationsPatronales":1}', 'field "cotisationsSalariales" is missing or not a number'];
        yield 'employer amount not a number' => ['{"cotisationsPatronales":"1","cotisationsSalariales":1}', 'field "cotisationsPatronales" is missing or not a number'];
        yield 'employee amount null' => ['{"cotisationsPatronales":1,"cotisationsSalariales":null}', 'field "cotisationsSalariales" is missing or not a number'];
        yield 'negative amount' => ['{"cotisationsPatronales":-1,"cotisationsSalariales":1}', 'amount is not a valid non-negative amount in euros'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body, string $reason): void
    {
        try {
            (new ContributionsMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: ' . $reason . '.', $exception->getMessage());
        }
    }
}
