<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;

/**
 * Each scenario reaches its state through the real handlers and the scripted platform.
 */
final class ScenarioTest extends TestCase
{
    public function testAnImportedEmployerIsTrackedWithoutAnyCall(): void
    {
        $harness = Harness::boot();

        $account = Scenario::importedEmployer($harness);

        static::assertSame('registered', $account->stage);
        static::assertSame(Scenario::EMPLOYER, $account->externalReference);
        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $account->cesuNumber);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAMandatedEmployerHasAnActiveMandate(): void
    {
        $harness = Harness::boot();

        Scenario::mandatedEmployer($harness);

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
        $mandate = $harness->privateService(MandateRepository::class, MandateRepository::class)
            ->findActive(TenantId::fromString(Scenario::TENANT), CesuNumber::fromString(DeclarationSamples::EMPLOYER_CESU_NUMBER));
        static::assertNotNull($mandate);
    }

    public function testADeclarationIsDeclared(): void
    {
        $harness = Harness::boot();

        $submission = Scenario::declaration($harness);

        static::assertSame(ItemOutcome::Declared, $submission->outcome);
        static::assertSame(DeclarationState::Declared->value, $submission->declaration->status->state);
        static::assertSame(Scenario::DECLARATION, $submission->declaration->externalReference);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }

    public function testAnOutcomeUnknownDeclarationFollowsAServerError(): void
    {
        $harness = Harness::boot();

        $submission = Scenario::outcomeUnknownDeclaration($harness);

        static::assertSame(DeclarationState::OutcomeUnknown->value, $submission->declaration->status->state);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_050));
        // The route answers a success again.
        static::assertSame(ItemOutcome::Declared, Scenario::declaration($harness, 'declaration-2')->outcome);
    }

    public function testAnInvalidCredentialsDeclarationFollowsARefusedToken(): void
    {
        $harness = Harness::boot();

        $submission = Scenario::invalidCredentialsDeclaration($harness);

        static::assertSame(DeclarationState::InvalidCredentials->value, $submission->declaration->status->state);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
        static::assertSame(2, $harness->urssaf->calls(HttpScript::ROUTE_AUTHENTICATION));
    }

    public function testAStaleDeclarationIsSubmittingAndOlderThanThePolicy(): void
    {
        $harness = Harness::boot();

        $id = Scenario::staleDeclaration($harness);

        $record = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)->get($id);
        static::assertSame(DeclarationState::Submitting, $record->state());
        $policy = $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class);
        $stale = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)
            ->findStaleSubmissions(Instant::fromDateTime($harness->clock->now()->sub($policy->staleSubmissionAfter())), 10);
        static::assertCount(1, $stale);
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_050));
    }
}
