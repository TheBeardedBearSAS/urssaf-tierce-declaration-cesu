<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployeeVerificationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandViews;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate\MandateSamples;

/**
 * The rows of the views: identifiers, states, codes and instants only (ADR 0007, lot 6 design open point 7).
 */
final class CommandViewsTest extends TestCase
{
    public function testTheErrorsOfARejectedDeclarationAreListedByCodeNeverByDescription(): void
    {
        $harness = Harness::boot();
        $id = Scenario::staleDeclaration($harness);
        $records = $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class);
        $record = $records->get($id);
        $record->markRejected(
            [
                SubmissionError::of('ERR_METIER', 'A business error.', 'Veuillez indiquer un IBAN valide.'),
                SubmissionError::of('PARAM_INVALIDE', 'A parameter.', null),
            ],
            Instant::fromIso8601('2026-11-15T10:30:00Z'),
        );
        $records->save($record);

        $rows = CommandViews::declaration(DeclarationView::fromRecord($records->get($id)));

        static::assertSame([
            ['Record id', $id->value()],
            ['External reference', Scenario::DECLARATION],
            ['State', 'rejected'],
            ['Declaration id', '-'],
            ['Attempts', '1'],
            ['Error codes', 'ERR_METIER, PARAM_INVALIDE'],
            ['Updated at', '2026-11-15T10:30:00Z'],
        ], $rows);
        static::assertStringNotContainsString('IBAN', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function testAnEmployeeNotRegisteredHasNoCesuNumber(): void
    {
        $view = EmployeeVerificationView::fromResult(EmployeeVerificationResult::of(false, null));

        static::assertSame([['Registered', 'no'], ['Cesu number', '-']], CommandViews::employeeVerification($view));
    }

    public function testTheAccountOfAnEmployerNeverCheckedHasNoStatusAndNoMandate(): void
    {
        $account = EmployerAccountView::fromAccount(EmployerSamples::account(), null);

        static::assertSame([
            ['Account id', 'account-1'],
            ['External reference', 'employer-ref-1'],
            ['Cesu number', 'Z0003752390009'],
            ['Stage', 'registered'],
            ['Mandate present', '-'],
            ['Mandate state', '-'],
            ['Last checked at', '-'],
        ], CommandViews::refreshedAccount($account));
    }

    public function testTheAccountOfAnEmployerWithAStatusButNoMandate(): void
    {
        $employer = EmployerSamples::account();
        $employer->applyStatus(EmployerSamples::status(true), null, EmployerSamples::instant('2026-11-15T11:00:00Z'));

        $rows = CommandViews::refreshedAccount(EmployerAccountView::fromAccount($employer, null));

        static::assertSame([['Mandate present', 'yes'], ['Mandate state', '-'], ['Last checked at', '2026-11-15T11:00:00Z']], \array_slice($rows, 4));
    }

    public function testTheAccountOfAnEmployerWithAMandateButNoStatus(): void
    {
        $rows = CommandViews::refreshedAccount(EmployerAccountView::fromAccount(EmployerSamples::account(), MandateSamples::notified()));

        static::assertSame([['Mandate present', '-'], ['Mandate state', 'notified'], ['Last checked at', '-']], \array_slice($rows, 4));
    }

    public function testTheTrackedAccountShowsItsOriginAndCreationInstant(): void
    {
        $rows = CommandViews::trackedAccount(EmployerAccountView::fromAccount(EmployerSamples::account(), null));

        static::assertSame([
            ['Account id', 'account-1'],
            ['External reference', 'employer-ref-1'],
            ['Cesu number', 'Z0003752390009'],
            ['Origin', 'registered'],
            ['Stage', 'registered'],
            ['Created at', '2026-11-15T10:00:00Z'],
        ], $rows);
    }
}
