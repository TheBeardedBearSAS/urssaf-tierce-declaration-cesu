<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalary;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\DeclareSalaryHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command\Fields;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;

/**
 * The life of an employer through the console commands and the handlers, over the scripted URSSAF platform:
 * import, mandate, declaration, refresh of the status, then the overview (lot 6 design §13.2).
 */
final class EndToEndTest extends TestCase
{
    public function testAnEmployerGoesFromImportToAConfirmedMandateAndADeclaration(): void
    {
        $harness = Harness::boot();

        $import = $harness->command('urssaf:cesu:employer:import');
        $import->setInputs(['1975-01-30']);
        static::assertSame(0, $import->execute(['cesu-number' => 'Z0003752390009', '--reference' => 'employer-1']));

        $mandate = $harness->command('urssaf:cesu:mandate:register');
        static::assertSame(0, $mandate->execute(['--employer' => 'employer-1']));
        static::assertSame('notified', Fields::of($mandate->getDisplay())['State']);

        $submission = $harness->get(DeclareSalaryHandler::class)(
            new DeclareSalary(TenantId::fromString('tenant-1'), ExternalReference::fromString('declaration-1'), DraftFixtures::valid()),
        );
        static::assertSame('declared', $submission->declaration->status->state);

        $harness->urssaf->respond(HttpScript::ROUTE_020, 200, '{"inscrit":true,"etatCompteEnLigne":"actif","mandatPresent":true,"etatMandatSepa":"actif","etatCesuPlus":"activé","etatAICI":false}');
        $sync = $harness->command('urssaf:cesu:employers:sync-status');
        static::assertSame(0, $sync->execute(['--tenant' => 'tenant-1', '--employer' => 'employer-1']));
        static::assertSame('yes', Fields::of($sync->getDisplay())['Mandate present']);
        static::assertSame('mandate_confirmed', Fields::of($sync->getDisplay())['Stage']);

        $status = $harness->command('urssaf:cesu:status');
        static::assertSame(0, $status->execute(['--format' => 'json']));
        static::assertSame([[
            'tenant_id' => 'tenant-1',
            'employers' => ['registered' => 0, 'mandate_notified' => 0, 'mandate_confirmed' => 1, 'aici_active' => 0, 'mandate_cancelled' => 0],
            'mandates' => ['notified' => 0, 'confirmed' => 1, 'cancelled' => 0],
            'declarations' => ['pending' => 0, 'submitting' => 0, 'declared' => 1, 'rejected' => 0, 'already_declared' => 0, 'outcome_unknown' => 0, 'error' => 0, 'invalid_credentials' => 0],
            'needing_attention' => 0,
        ]], json_decode($status->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }
}
