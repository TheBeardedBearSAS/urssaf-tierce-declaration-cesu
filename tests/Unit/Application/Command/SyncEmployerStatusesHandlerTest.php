<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use DateInterval;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatuses;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class SyncEmployerStatusesHandlerTest extends TestCase
{
    private const ZONE = 'Europe/Paris';

    public function testReportsChangedUnchangedAndFailedAccountsAndGoesOn(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->account(2);
        $harness->account(3);
        $harness->account(4, EmployerStage::Registered, Harness::OTHER_TENANT);
        $harness->gateway->willVerifyEmployer(UrssafRequestRejected::withErrors(
            Operation::VerifyEmployer,
            400,
            ApiErrors::of(ApiError::of('PARAM_INVALIDE', 'Invalid parameter.', null)),
            ErrorKind::InvalidParameter,
            null,
        ));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        $report = self::handler($harness)(new SyncEmployerStatuses(Harness::tenant(), null));

        static::assertEquals([
            new BatchItem('account-2', ItemOutcome::Failed, ['PARAM_INVALIDE']),
            new BatchItem('account-3', ItemOutcome::Changed),
            new BatchItem('account-1', ItemOutcome::Unchanged),
        ], $report->items());
        static::assertSame([Harness::TENANT, Harness::TENANT, Harness::TENANT], $harness->gateway->tenants());
        static::assertNull($harness->accounts->get(EmployerAccountId::fromString('account-2'))->lastCheckedAt());
        static::assertSame(Harness::NOW, $harness->accounts->get(EmployerAccountId::fromString('account-3'))->lastCheckedAt()?->toIso8601());
        static::assertNull($harness->gateway->verifications()[0]->employee());
    }

    public function testEachAccountIsRefreshedInItsOwnTransaction(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));

        self::handler($harness)(new SyncEmployerStatuses(Harness::tenant(), null));

        static::assertSame(
            [
                'call:020', 'begin', 'save:account-1', 'commit', 'publish:EmployerStatusRefreshed',
                'call:020', 'begin', 'save:account-2', 'commit', 'publish:EmployerStatusRefreshed',
            ],
            $harness->journal->entries(),
        );
    }

    public function testTheLimitIsCappedByThePolicy(): void
    {
        $harness = new Harness(new WorkflowPolicy(new DateTimeZone(self::ZONE), new DateInterval(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER), WorkflowPolicy::DEFAULT_MAX_SUBMISSION_ATTEMPTS, true, false, 1, 2));
        $harness->account(1);
        $harness->account(2);
        $harness->account(3);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $handler = self::handler($harness);

        static::assertSame(1, $handler(new SyncEmployerStatuses(Harness::tenant(), null))->total());
        static::assertSame(1, $handler(new SyncEmployerStatuses(Harness::tenant(), 1))->total());
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        static::assertSame(2, $handler(new SyncEmployerStatuses(Harness::tenant(), 50))->total());
    }

    public function testInvalidCredentialsStopTheRunButKeepWhatWasCommitted(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));
        $harness->gateway->willVerifyEmployer(UrssafAuthenticationFailed::withStatus(401));

        try {
            self::handler($harness)(new SyncEmployerStatuses(Harness::tenant(), null));
            static::fail('Expected invalid credentials.');
        } catch (UrssafAuthenticationFailed $exception) {
            static::assertSame(401, $exception->httpStatus());
        }

        static::assertNotNull($harness->accounts->get(EmployerAccountId::fromString('account-1'))->lastCheckedAt());
        static::assertNull($harness->accounts->get(EmployerAccountId::fromString('account-2'))->lastCheckedAt());
    }

    public function testTemporaryFailureStopsTheRun(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->account(2);
        $harness->gateway->willVerifyEmployer(UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, null, ApiErrors::none()));

        try {
            self::handler($harness)(new SyncEmployerStatuses(Harness::tenant(), null));
            static::fail('Expected a temporary failure.');
        } catch (UrssafUnavailable) {
            static::assertSame([Operation::VerifyEmployer], $harness->gateway->calls());
        }
    }

    public function testNoAccountNoCall(): void
    {
        $harness = new Harness();

        static::assertSame([], self::handler($harness)(new SyncEmployerStatuses(Harness::tenant(), null))->items());
        static::assertSame([], $harness->gateway->calls());
    }

    private static function handler(Harness $harness): SyncEmployerStatusesHandler
    {
        return new SyncEmployerStatusesHandler(
            $harness->accounts,
            new EmployerStatusRefresher($harness->gateway, $harness->mandates, $harness->accounts, $harness->clock, $harness->unitOfWork),
            $harness->policy,
        );
    }
}
