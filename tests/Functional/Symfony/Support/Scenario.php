<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\RegisterMandateHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * Drives an application through the lot 4 handlers to a given state, with the fictitious employer and the valid
 * declaration draft of the unit fixtures (reference Scenario; lot 6 design §10.2).
 *
 * The employer of {@see DraftFixtures::valid()} is the only one a declaration can be sent for: use one tenant per
 * employer. The scripted routes touched by a scenario are put back to their default answer afterwards.
 */
final class Scenario
{
    public const TENANT = 'tenant-1';
    public const EMPLOYER = 'employer-1';
    public const DECLARATION = 'declaration-1';

    private function __construct() {}

    /**
     * Employer tracked in stage "registered" (no URSSAF call).
     */
    public static function importedEmployer(Harness $harness, string $tenant = self::TENANT, string $reference = self::EMPLOYER): EmployerAccountView
    {
        return $harness->get(ImportEmployerHandler::class)(
            new ImportEmployer(TenantId::fromString($tenant), ExternalReference::fromString($reference), EmployerSamples::employer()),
        );
    }

    /**
     * Imported employer whose mandate was notified (method 030, route 030). Returns the account as imported.
     */
    public static function mandatedEmployer(Harness $harness, string $tenant = self::TENANT, string $reference = self::EMPLOYER): EmployerAccountView
    {
        $account = self::importedEmployer($harness, $tenant, $reference);
        $harness->get(RegisterMandateHandler::class)(new RegisterMandate(
            TenantId::fromString($tenant),
            EmployerSelector::byExternalReference(ExternalReference::fromString($reference)),
        ));

        return $account;
    }

    /**
     * Declaration in state "declared" (method 050 answered the assembled response fixture).
     */
    public static function declaration(Harness $harness, string $reference = self::DECLARATION): DeclarationSubmission
    {
        self::mandatedEmployer($harness);

        return self::declare($harness, $reference);
    }

    /**
     * Declaration in state "outcome_unknown": the platform answered a 500 to method 050 (never resent, ADR 0003).
     */
    public static function outcomeUnknownDeclaration(Harness $harness, string $reference = self::DECLARATION): DeclarationSubmission
    {
        self::mandatedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_050, 500, '{"message":"Internal Server Error"}');
        $submission = self::declare($harness, $reference);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_050, 200, 'declare_response_assembled.json');

        return $submission;
    }

    /**
     * Declaration in state "invalid_credentials": the cached access token expired and the token route answers a 401.
     * The clock is two hours later afterwards.
     */
    public static function invalidCredentialsDeclaration(Harness $harness, string $reference = self::DECLARATION): DeclarationSubmission
    {
        self::mandatedEmployer($harness);
        $harness->clock->modify('+2 hours');
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $submission = self::declare($harness, $reference);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_AUTHENTICATION, 200, 'token_response.json');

        return $submission;
    }

    /**
     * Record in state "submitting" claimed twenty minutes ago (the stale threshold is fifteen): nothing was sent.
     * The clock is twenty minutes later afterwards.
     */
    public static function staleDeclaration(Harness $harness, string $tenant = self::TENANT, string $reference = self::DECLARATION): DeclarationRecordId
    {
        $records = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class);
        $now = Instant::fromDateTime($harness->clock->now());
        $declaration = (new DeclarationFactory())->fromDraft(DraftFixtures::valid(), DraftFixtures::context());
        $id = DeclarationRecordId::fromString($harness->privateService(IdGenerator::class, IdGenerator::class)->generate());
        $records->save(DeclarationRecord::record(
            $id,
            TenantId::fromString($tenant),
            ExternalReference::fromString($reference),
            DeclarationSnapshot::fromDeclaration($declaration),
            $now,
        ));
        $records->claimForSubmission($id, $now);
        $harness->clock->modify('+20 minutes');

        return $id;
    }

    private static function declare(Harness $harness, string $reference): DeclarationSubmission
    {
        return $harness->get(DeclareSalaryHandler::class)(
            new DeclareSalary(TenantId::fromString(self::TENANT), ExternalReference::fromString($reference), DraftFixtures::valid()),
        );
    }
}
