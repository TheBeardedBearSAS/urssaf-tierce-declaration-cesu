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

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\CesuPlusDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\BodyReader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;

final class DeclarationRequestMapperTest extends TestCase
{
    private const PERIOD = '"dateDebut":"2026-10-01","dateFin":"2026-10-31","dateVersement":"2026-10-31"';

    private static function encode(StandardDeclaration|CesuPlusDeclaration $declaration): string
    {
        return JsonEncoder::encode((new DeclarationRequestMapper())->toBody($declaration));
    }

    public function testAStandardDeclarationByCesuNumber(): void
    {
        static::assertSame(
            '{' . self::PERIOD . ',"typeDeclaration":0,"revenusEf":{"salaireHoraire":12.50,"heuresTravaillees":100,'
            . '"nbHeureSupplementaire1":4},"infosSalarie":{"numeroCesu":"00000123456789","typeEmploi":"PE",'
            . '"congesPayes":false}}',
            self::encode(DeclarationSamples::standard()),
        );
    }

    public function testACesuPlusDeclarationSendsTheTypeAndTheActivityNature(): void
    {
        static::assertSame(
            '{' . self::PERIOD . ',"typeDeclaration":1,"natureActivite":"08","revenusEf":{"salaireHoraire":12.50,'
            . '"heuresTravaillees":100,"nbHeureSupplementaire1":4},"infosSalarie":{"numeroCesu":"00000123456789",'
            . '"typeEmploi":"PE","congesPayes":false}}',
            self::encode(DeclarationSamples::cesuPlus()),
        );
    }

    public function testAnUnknownPaidLeaveFlagIsOmitted(): void
    {
        $content = DeclarationSamples::build(
            null,
            DeclarationSamples::employee(EmploymentKind::HomeEmployment, null),
            null,
            null,
            null,
        );

        static::assertSame(
            '{' . self::PERIOD . ',"typeDeclaration":0,"revenusEf":{"salaireHoraire":12.50,"heuresTravaillees":100,'
            . '"nbHeureSupplementaire1":4},"infosSalarie":{"numeroCesu":"00000123456789","typeEmploi":"PE"}}',
            self::encode(StandardDeclaration::of($content)),
        );
    }

    public function testEveryOptionalPartIsSentInSwaggerOrder(): void
    {
        $employee = RequestSamples::fullEmployee(
            EmployeeByIdentity::of(RequestSamples::fullIdentity()),
            EmploymentKind::FosterCarer,
        );
        $body = (new DeclarationRequestMapper())->toBody(StandardDeclaration::of(RequestSamples::maximalContent($employee)));

        static::assertSame(
            [
                'dateDebut',
                'dateFin',
                'dateVersement',
                'typeDeclaration',
                'montantAcompte',
                'revenusAf',
                'revenusEf',
                'infosSalarie',
            ],
            array_keys($body),
        );
        static::assertSame(
            '{"salaireHoraire":12.50,"heuresTravaillees":100,"nbHeureSupplementaire1":8,"nbHeureSupplementaire2":9,'
            . '"heuresCp":7,"complementsSalaire":[{"codeCategorie":"HEUR","codeSousCategorie":"NUIT","montant":20.09,'
            . '"salaireHoraire":12.50,"nombreHeures":3},{"codeCategorie":"PRIM","codeSousCategorie":"PRIM",'
            . '"montant":50.00}],"periodesCongesPayes":['
            . '{"dateDebut":"2026-10-05","dateFin":"2026-10-06","nbJoursCP":1.5},'
            . '{"dateDebut":"2026-10-20","dateFin":"2026-10-21","nbJoursCP":2}]}',
            JsonEncoder::encode(BodyReader::object($body, 'revenusEf')),
        );
    }

    public function testTheDepositFollowsTheActivityNature(): void
    {
        $content = new DeclarationContent(
            DeclarationSamples::employerCesuNumber(),
            DeclarationSamples::period(),
            DeclarationSamples::date('2026-10-31'),
            DeclarationSamples::employee(EmploymentKind::HomeEmployment, false),
            DeclarationSamples::hourlyIncome(),
            null,
            DeclarationSamples::deposit(),
            null,
            ActivityNature::Gardening,
        );

        static::assertSame(
            ['dateDebut', 'dateFin', 'dateVersement', 'typeDeclaration', 'natureActivite', 'montantAcompte', 'revenusEf', 'infosSalarie'],
            array_keys((new DeclarationRequestMapper())->toBody(CesuPlusDeclaration::of($content))),
        );
    }

    public function testNoInternalIdentifierIsSent(): void
    {
        $employee = RequestSamples::fullEmployee(
            EmployeeByIdentity::of(RequestSamples::fullIdentity()),
            EmploymentKind::FosterCarer,
        );
        $declaration = StandardDeclaration::of(RequestSamples::maximalContent($employee));
        $json = self::encode($declaration);

        static::assertStringNotContainsString($declaration->fingerprint(), $json);
        static::assertStringNotContainsString('tenant', $json);
        static::assertStringNotContainsString('host-declaration', $json);
    }
}
