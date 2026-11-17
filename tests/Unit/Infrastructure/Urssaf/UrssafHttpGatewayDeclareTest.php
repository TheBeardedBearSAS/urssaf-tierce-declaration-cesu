<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Gateways;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Stacks;

final class UrssafHttpGatewayDeclareTest extends TestCase
{
    /**
     * The official request fixture is a contract-violating placeholder, so the expected bodies are written out here.
     *
     * @return iterable<string, array{Declaration, array<string, mixed>}>
     */
    public static function declarations(): iterable
    {
        $common = [
            'dateDebut' => '2026-10-01',
            'dateFin' => '2026-10-31',
            'dateVersement' => '2026-10-31',
        ];
        $rest = [
            'revenusEf' => ['salaireHoraire' => 12.5, 'heuresTravaillees' => 100, 'nbHeureSupplementaire1' => 4],
            'infosSalarie' => ['numeroCesu' => '00000123456789', 'typeEmploi' => 'PE', 'congesPayes' => false],
        ];

        yield 'standard' => [DeclarationSamples::standard(), [...$common, 'typeDeclaration' => 0, ...$rest]];
        yield 'cesu plus' => [
            DeclarationSamples::cesuPlus(),
            [...$common, 'typeDeclaration' => 1, 'natureActivite' => '08', ...$rest],
        ];
    }

    /**
     * @param array<string, mixed> $expectedBody
     */
    #[DataProvider('declarations')]
    public function testDeclare(Declaration $declaration, array $expectedBody): void
    {
        $stack = Gateways::answering(200, Fixtures::load('declare_response_assembled'));

        $result = Gateways::of($stack)->declare(Stacks::tenant(), $declaration);

        static::assertSame('209299542888', $result->declarationId()->value());
        $request = Gateways::apiRequest($stack);
        static::assertSame('POST', $request->getMethod());
        static::assertSame(
            'https://api-edi.urssaf.fr/tiersdecl/v1/cesu/employeurs/Z0003752390009/declarer',
            (string) $request->getUri(),
        );
        static::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        static::assertEquals($expectedBody, Gateways::jsonBody($request));
        static::assertSame(['tenant-1:declare_salary'], $stack->rateLimiter->acquired());
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function unreadableAnswers(): iterable
    {
        yield '200 empty object' => [200, '{}'];
        yield '200 invalid JSON' => [200, 'not json'];
        yield '201 empty body' => [201, ''];
    }

    #[DataProvider('unreadableAnswers')]
    public function testAnUnreadableSuccessIsAnUnknownOutcome(int $status, string $body): void
    {
        $stack = Gateways::answering($status, $body);

        try {
            Gateways::of($stack)->declare(Stacks::tenant(), DeclarationSamples::standard());
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(Operation::DeclareSalary, $unknown->operation());
            static::assertSame($status, $unknown->httpStatus());
            static::assertInstanceOf(UnexpectedUrssafResponse::class, $unknown->getPrevious());
        }
    }

    public function testATechnicalErrorIsAnUnknownOutcome(): void
    {
        $stack = Gateways::answering(500, Fixtures::load('error_technical'));

        try {
            Gateways::of($stack)->declare(Stacks::tenant(), DeclarationSamples::standard());
            static::fail('An unknown outcome was expected.');
        } catch (OutcomeUnknown $unknown) {
            static::assertSame(500, $unknown->httpStatus());
            static::assertTrue($unknown->errors()->hasCode('ERR_TECHNIQUE'));
        }
    }

    public function testADuplicateDeclarationIsClassified(): void
    {
        $stack = Gateways::answering(
            400,
            '{"code":"ERR_METIER","message":"Une erreur métier est survenue","description":'
            . '"Une déclaration est déjà enregistrée pour cette période d’emploi."}',
        );

        try {
            Gateways::of($stack)->declare(Stacks::tenant(), DeclarationSamples::standard());
            static::fail('A rejection was expected.');
        } catch (UrssafRequestRejected $rejected) {
            static::assertTrue($rejected->isDuplicateDeclaration());
        }
    }
}
