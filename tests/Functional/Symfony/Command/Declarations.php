<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationSnapshot;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;

/**
 * Declaration records put in a given state without going through the handlers.
 */
final class Declarations
{
    private function __construct() {}

    /**
     * A record in state "submitting", claimed at the current instant of the test clock (it is not stale).
     */
    public static function submittingNow(Harness $harness, string $tenant, string $reference): DeclarationRecordId
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

        return $id;
    }
}
