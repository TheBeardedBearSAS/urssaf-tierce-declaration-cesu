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
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\EstimateResponseMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonDecoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;

final class EstimateResponseMapperTest extends TestCase
{
    public function testOfficialExample(): void
    {
        $estimate = (new EstimateResponseMapper())->map(Fixtures::payload('estimate_response'));

        static::assertSame(10_000, $estimate->employerContributions()->cents());
        static::assertSame(5000, $estimate->employeeContributions()->cents());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusals(): iterable
    {
        yield 'empty body' => ['', 'field "cotisations" is missing or not an object'];
        yield 'cotisations missing' => ['{}', 'field "cotisations" is missing or not an object'];
        yield 'cotisations null' => ['{"cotisations":null}', 'field "cotisations" is missing or not an object'];
        yield 'cotisations not an object' => ['{"cotisations":3}', 'field "cotisations" is missing or not an object'];
        yield 'an amount is missing' => ['{"cotisations":{"cotisationsPatronales":1}}', 'field "cotisationsSalariales" is missing or not a number'];
    }

    #[DataProvider('refusals')]
    public function testRefusals(string $body, string $reason): void
    {
        try {
            (new EstimateResponseMapper())->map(JsonDecoder::decode($body));
            static::fail('Expected an UnexpectedUrssafResponse.');
        } catch (UnexpectedUrssafResponse $exception) {
            static::assertSame('Unexpected URSSAF response: ' . $reason . '.', $exception->getMessage());
        }
    }
}
