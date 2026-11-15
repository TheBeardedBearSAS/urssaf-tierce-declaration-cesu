<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ContributionsView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationResultView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSnapshotView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationStatusView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSubmission;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployeeVerificationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerStatusView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate\MandateSamples;

/**
 * One method per factory branch.
 *
 * @mago-expect lint:too-many-methods
 */
final class ViewsTest extends TestCase
{
    public function testStatusViewFromAStatusWithoutEmployee(): void
    {
        $status = new EmployerStatus(true, 'actif', 'inconnu-demain', true, 'bloque', 'Motif', true);

        $view = EmployerStatusView::fromStatus($status, null, null);

        static::assertTrue($view->registered);
        static::assertSame('actif', $view->onlineAccountState);
        static::assertSame('inconnu-demain', $view->sepaMandateState);
        static::assertTrue($view->mandatePresent);
        static::assertSame('bloque', $view->cesuPlusState);
        static::assertSame('Motif', $view->cesuPlusBlockingReason);
        static::assertTrue($view->aiciActive);
        static::assertNull($view->employeeReferenceKind);
        static::assertNull($view->employeeCesuNumber);
        static::assertNull($view->checkedAt);
    }

    public function testStatusViewFromAStatusWithAnEmployeeAndACheckInstant(): void
    {
        $status = new EmployerStatus(false, null, null, false, null, null, false);
        $employee = EmployeeByCesuNumber::of(EmployeeSamples::cesuNumber());

        $view = EmployerStatusView::fromStatus($status, $employee, Instant::fromIso8601('2026-11-15T10:00:00Z'));

        static::assertFalse($view->registered);
        static::assertNull($view->onlineAccountState);
        static::assertNull($view->sepaMandateState);
        static::assertFalse($view->mandatePresent);
        static::assertNull($view->cesuPlusState);
        static::assertNull($view->cesuPlusBlockingReason);
        static::assertFalse($view->aiciActive);
        static::assertSame('cesu_number', $view->employeeReferenceKind);
        static::assertSame('00000123456789', $view->employeeCesuNumber);
        static::assertSame('2026-11-15T10:00:00Z', $view->checkedAt);
    }

    public function testStatusViewFromAnAccountNeverSynchronisedIsNull(): void
    {
        static::assertNull(EmployerStatusView::fromAccount(EmployerSamples::account()));
    }

    public function testStatusViewFromASynchronisedAccount(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(
            new EmployerStatus(true, 'actif', 'actif', true, 'actif', 'Motif', false),
            EmployeeByCesuNumber::of(EmployeeSamples::cesuNumber()),
            Instant::fromIso8601('2026-11-16T08:00:00Z'),
        );

        $view = EmployerStatusView::fromAccount($account);

        static::assertInstanceOf(EmployerStatusView::class, $view);
        static::assertTrue($view->registered);
        static::assertSame('actif', $view->onlineAccountState);
        static::assertSame('actif', $view->sepaMandateState);
        static::assertTrue($view->mandatePresent);
        static::assertSame('actif', $view->cesuPlusState);
        static::assertSame('Motif', $view->cesuPlusBlockingReason);
        static::assertFalse($view->aiciActive);
        static::assertSame('cesu_number', $view->employeeReferenceKind);
        static::assertSame('00000123456789', $view->employeeCesuNumber);
        static::assertSame('2026-11-16T08:00:00Z', $view->checkedAt);
    }

    public function testStatusViewFromAnAccountSynchronisedWithoutEmployee(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(), null, Instant::fromIso8601('2026-11-16T08:00:00Z'));

        $view = EmployerStatusView::fromAccount($account);

        static::assertInstanceOf(EmployerStatusView::class, $view);
        static::assertNull($view->employeeReferenceKind);
        static::assertNull($view->employeeCesuNumber);
        static::assertFalse($view->mandatePresent);
    }

    public function testMandateViewOfANotifiedMandate(): void
    {
        $view = MandateView::fromMandate(MandateSamples::notified());

        static::assertSame('mandate-1', $view->mandateId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame(EmployerSamples::externalReference()->value(), $view->externalReference);
        static::assertSame(EmployerSamples::employer()->cesuNumber()->value(), $view->cesuNumber);
        static::assertSame('notified', $view->state);
        static::assertSame('2026-11-15T10:00:00Z', $view->notifiedAt);
        static::assertNull($view->confirmedAt);
        static::assertNull($view->cancelledAt);
        static::assertNull($view->lastMandatePresent);
        static::assertNull($view->lastCheckedAt);
        static::assertSame('2026-11-15T10:00:00Z', $view->updatedAt);
    }

    public function testMandateViewOfAConfirmedAndCheckedMandate(): void
    {
        $view = MandateView::fromMandate(MandateSamples::inState(MandateState::Confirmed));

        static::assertSame('confirmed', $view->state);
        static::assertSame('2026-11-15T12:00:00Z', $view->confirmedAt);
        static::assertNull($view->cancelledAt);
        static::assertTrue($view->lastMandatePresent);
        static::assertSame('2026-11-15T12:00:00Z', $view->lastCheckedAt);
        static::assertSame('2026-11-15T12:00:00Z', $view->updatedAt);
    }

    public function testMandateViewOfACancelledMandate(): void
    {
        $view = MandateView::fromMandate(MandateSamples::inState(MandateState::Cancelled));

        static::assertSame('cancelled', $view->state);
        static::assertNull($view->confirmedAt);
        static::assertSame('2026-11-15T12:00:00Z', $view->cancelledAt);
        static::assertNull($view->lastMandatePresent);
    }

    public function testAccountViewWithoutStatusNorMandate(): void
    {
        $view = EmployerAccountView::fromAccount(EmployerSamples::account(), null);

        static::assertSame('account-1', $view->accountId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame(EmployerSamples::externalReference()->value(), $view->externalReference);
        static::assertSame(EmployerSamples::employer()->cesuNumber()->value(), $view->cesuNumber);
        static::assertSame('registered', $view->origin);
        static::assertSame('registered', $view->stage);
        static::assertNull($view->lastStatus);
        static::assertNull($view->mandate);
        static::assertSame('2026-11-15T10:00:00Z', $view->createdAt);
        static::assertSame('2026-11-15T10:00:00Z', $view->updatedAt);
    }

    public function testAccountViewWithStatusAndMandate(): void
    {
        $account = EmployerSamples::account();
        $account->applyStatus(EmployerSamples::status(true), null, Instant::fromIso8601('2026-11-16T08:00:00Z'));

        $view = EmployerAccountView::fromAccount($account, MandateSamples::notified());

        static::assertSame('mandate_confirmed', $view->stage);
        static::assertInstanceOf(EmployerStatusView::class, $view->lastStatus);
        static::assertTrue($view->lastStatus->mandatePresent);
        static::assertInstanceOf(MandateView::class, $view->mandate);
        static::assertSame('mandate-1', $view->mandate->mandateId);
        static::assertSame('2026-11-15T10:00:00Z', $view->createdAt);
        static::assertSame('2026-11-16T08:00:00Z', $view->updatedAt);
    }

    public function testEmployeeVerificationView(): void
    {
        $known = EmployeeVerificationView::fromResult(EmployeeVerificationResult::of(true, EmployeeSamples::cesuNumber()));
        $unknown = EmployeeVerificationView::fromResult(EmployeeVerificationResult::of(false, null));

        static::assertTrue($known->registered);
        static::assertSame('00000123456789', $known->cesuNumber);
        static::assertFalse($unknown->registered);
        static::assertNull($unknown->cesuNumber);
    }

    public function testContributionsView(): void
    {
        $view = ContributionsView::fromEstimate(ContributionsEstimate::of(
            Contributions::of(DeclarationSamples::money('63.1'), DeclarationSamples::money('20.09')),
        ));

        static::assertSame(6310, $view->employerContributionsCents);
        static::assertSame(2009, $view->employeeContributionsCents);
    }

    public function testResultViewWithEveryAmount(): void
    {
        $view = DeclarationResultView::fromResult(DeclarationSamples::result());

        static::assertSame('209299542888', $view->declarationId);
        static::assertSame(6310, $view->employerContributionsCents);
        static::assertSame(2009, $view->employeeContributionsCents);
        static::assertSame(500, $view->withholdingTaxCents);
        static::assertSame(100, $view->employerContributionsDebitCents);
        static::assertSame(200, $view->employerWithholdingTaxDebitCents);
        static::assertSame(300, $view->employerSalaryDebitCents);
        static::assertSame(40_000, $view->grossSalaryCents);
        static::assertSame(30_000, $view->netTaxableSalaryCents);
        static::assertNull($view->salaryTransferCents);
        static::assertSame(700, $view->aiciAmountCents);
    }

    public function testResultViewWithoutAmount(): void
    {
        $view = DeclarationResultView::fromResult(new DeclarationResult(
            DeclarationId::fromString('209299542888'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
        ));

        static::assertSame('209299542888', $view->declarationId);
        static::assertSame(
            [null, null, null, null, null, null, null, null, null],
            [
                $view->employerContributionsCents,
                $view->employeeContributionsCents,
                $view->withholdingTaxCents,
                $view->employerContributionsDebitCents,
                $view->employerWithholdingTaxDebitCents,
                $view->employerSalaryDebitCents,
                $view->grossSalaryCents,
                $view->netTaxableSalaryCents,
                $view->salaryTransferCents,
            ],
        );
        static::assertNull($view->aiciAmountCents);
    }

    public function testResultViewWithTheOtherAmounts(): void
    {
        $view = DeclarationResultView::fromResult(new DeclarationResult(
            DeclarationId::fromString('1'),
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            DeclarationSamples::money('9'),
            null,
        ));

        static::assertSame(900, $view->salaryTransferCents);
        static::assertNull($view->aiciAmountCents);
        static::assertNull($view->grossSalaryCents);
        static::assertNull($view->netTaxableSalaryCents);
    }

    public function testSnapshotViewHoldsNoPersonalData(): void
    {
        $snapshot = DeclarationSamples::snapshot();

        $view = DeclarationSnapshotView::fromSnapshot($snapshot);

        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $view->employerCesuNumber);
        static::assertSame(0, $view->type);
        static::assertSame('2026-10-01', $view->employmentStart);
        static::assertSame('2026-10-31', $view->employmentEnd);
        static::assertSame($snapshot->paymentDate()->toString(), $view->paymentDate);
        static::assertSame($snapshot->employmentKind()->value, $view->employmentKind);
        static::assertSame($snapshot->employeeReferenceKind()->value, $view->employeeReferenceKind);
        static::assertSame($snapshot->employeeCesuNumber()?->value(), $view->employeeCesuNumber);
        static::assertSame($snapshot->fingerprint()->value(), $view->fingerprint);
    }

    public function testSnapshotViewOfAnEmployeeWithoutCesuNumber(): void
    {
        $declaration = StandardDeclaration::of(DeclarationSamples::build(null, DeclarationSamples::nirEmployee(), null, null, DeclarationSamples::approval()));

        $view = DeclarationSnapshotView::fromSnapshot(DeclarationSamples::snapshotOf($declaration));

        static::assertSame('nir', $view->employeeReferenceKind);
        static::assertNull($view->employeeCesuNumber);
    }

    public function testStatusViewFromAStatusWithAnEmployeeWithoutCesuNumber(): void
    {
        $status = new EmployerStatus(true, null, null, false, null, null, false);

        $view = EmployerStatusView::fromStatus($status, EmployeeSamples::byNir(), null);

        static::assertSame('nir', $view->employeeReferenceKind);
        static::assertNull($view->employeeCesuNumber);
    }

    public function testSnapshotViewOfACesuPlusDeclaration(): void
    {
        $view = DeclarationSnapshotView::fromSnapshot(DeclarationSamples::snapshotOf(DeclarationSamples::cesuPlus()));

        static::assertSame(1, $view->type);
    }

    public function testViewOfAPendingRecord(): void
    {
        $record = DeclarationSamples::record();

        $view = DeclarationView::fromRecord($record);

        static::assertSame('record-1', $view->recordId);
        static::assertSame('tenant-1', $view->tenantId);
        static::assertSame('host-declaration-1', $view->externalReference);
        static::assertSame($record->snapshot()->fingerprint()->value(), $view->snapshot->fingerprint);
        static::assertSame('pending', $view->status->state);
        static::assertSame(0, $view->status->attempts);
        static::assertNull($view->status->lastAttemptAt);
        static::assertSame([], $view->status->errors);
        static::assertNull($view->status->declarationId);
        static::assertNull($view->status->result);
        static::assertNull($view->status->outcomeUnknownReason);
        static::assertSame('2026-11-15T10:00:00Z', $view->status->updatedAt);
        static::assertSame('2026-11-15T10:00:00Z', $view->createdAt);
    }

    public function testViewOfADeclaredRecordWithItsFullResult(): void
    {
        $record = DeclarationSamples::record();
        $record->startSubmission(DeclarationSamples::later(60));
        $record->markDeclared(DeclarationSamples::result(), DeclarationSamples::later(120));

        $status = DeclarationView::fromRecord($record)->status;

        static::assertSame('declared', $status->state);
        static::assertSame(1, $status->attempts);
        static::assertSame('2026-11-15T10:01:00Z', $status->lastAttemptAt);
        static::assertSame('209299542888', $status->declarationId);
        static::assertInstanceOf(DeclarationResultView::class, $status->result);
        static::assertSame('209299542888', $status->result->declarationId);
        static::assertSame('2026-11-15T10:02:00Z', $status->updatedAt);
    }

    public function testViewOfARecordDeclaredManuallyHasNoResult(): void
    {
        $record = DeclarationSamples::record();
        $record->startSubmission(DeclarationSamples::later(60));
        $record->markOutcomeUnknown(OutcomeUnknownReason::StaleSubmission, [], DeclarationSamples::later(120));
        $unknown = DeclarationView::fromRecord($record)->status;
        $record->resolveAsDeclared(DeclarationId::fromString('known'), DeclarationSamples::later(180));

        $declared = DeclarationView::fromRecord($record)->status;

        static::assertSame('outcome_unknown', $unknown->state);
        static::assertSame('stale_submission', $unknown->outcomeUnknownReason);
        static::assertSame('declared', $declared->state);
        static::assertSame('known', $declared->declarationId);
        static::assertNull($declared->result);
        static::assertNull($declared->outcomeUnknownReason);
    }

    public function testViewOfARejectedRecordKeepsTheErrors(): void
    {
        $record = DeclarationSamples::record();
        $record->startSubmission(DeclarationSamples::later(60));
        $record->markRejected(
            [SubmissionError::of('ERR_X', 'Message', 'Description'), SubmissionError::of('ERR_Y', 'Other', null)],
            DeclarationSamples::later(120),
        );

        $status = DeclarationView::fromRecord($record)->status;

        static::assertSame('rejected', $status->state);
        static::assertSame([
            ['code' => 'ERR_X', 'message' => 'Message', 'description' => 'Description'],
            ['code' => 'ERR_Y', 'message' => 'Other', 'description' => null],
        ], $status->errors);
    }

    public function testViewOfARecordInOutcomeUnknownBecauseTheCallMayHaveReachedUrssaf(): void
    {
        $record = DeclarationSamples::record();
        $record->startSubmission(DeclarationSamples::later(60));
        $record->markOutcomeUnknown(OutcomeUnknownReason::MayHaveReachedUrssaf, [], DeclarationSamples::later(120));

        $status = DeclarationView::fromRecord($record)->status;

        static::assertSame('may_have_reached_urssaf', $status->outcomeUnknownReason);
    }

    public function testStatusViewFromAStatusBuiltByTheSamples(): void
    {
        $view = DeclarationStatusView::fromStatus(DeclarationSamples::statusIn(
            DeclarationState::Declared,
            [],
            DeclarationSamples::result(),
            null,
        ));

        static::assertSame(2, $view->attempts);
        static::assertSame('2026-11-15T09:59:00Z', $view->lastAttemptAt);
        static::assertSame('2026-11-15T09:59:30Z', $view->updatedAt);
    }

    public function testASubmissionPairsAViewWithAnOutcome(): void
    {
        $view = DeclarationView::fromRecord(DeclarationSamples::record());

        $submission = new DeclarationSubmission($view, ItemOutcome::Replayed);

        static::assertSame($view, $submission->declaration);
        static::assertSame(ItemOutcome::Replayed, $submission->outcome);
    }
}
