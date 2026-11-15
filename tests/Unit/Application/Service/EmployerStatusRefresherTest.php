<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerStatusRefresher;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

final class EmployerStatusRefresherTest extends TestCase
{
    public function testEmployerOnlyRefreshWithoutMandate(): void
    {
        $harness = new Harness();
        $account = $harness->account(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        $changed = self::refresher($harness)->refresh($account, null);

        static::assertTrue($changed);
        static::assertSame(['call:020', 'begin', 'save:account-1', 'commit', 'publish:EmployerStatusRefreshed'], $harness->journal->entries());
        static::assertNull($harness->gateway->verifications()[0]->employee());
        static::assertSame($account->employer(), $harness->gateway->verifications()[0]->employer());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
        $saved = $harness->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployerStage::MandateConfirmed, $saved->stage());
        static::assertSame(Harness::NOW, $saved->lastCheckedAt()?->toIso8601());
        static::assertSame([], $harness->mandates->inner()->all());
    }

    public function testPairRefreshKeepsTheEmployeeKind(): void
    {
        $harness = new Harness();
        $account = $harness->account(1);
        $employee = EmployeeSamples::byCesuNumber();
        $harness->gateway->willVerifyEmployer(EmployerSamples::statusWithCesuPlus(false, 'actif'));

        $changed = self::refresher($harness)->refresh($account, $employee);

        static::assertTrue($changed);
        static::assertSame($employee, $harness->gateway->verifications()[0]->employee());
        $saved = $harness->accounts->get(EmployerAccountId::fromString('account-1'));
        static::assertSame(EmployeeReferenceKind::CesuNumber, $saved->lastStatusEmployeeKind());
        static::assertSame(EmployeeSamples::cesuNumber()->value(), $saved->lastStatusEmployeeCesuNumber()?->value());
    }

    public function testActiveMandateIsConfirmedAfterTheAccountEvent(): void
    {
        $harness = new Harness();
        $account = $harness->account(1, EmployerStage::MandateNotified);
        $harness->mandate(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        $changed = self::refresher($harness)->refresh($account, null);

        static::assertTrue($changed);
        static::assertSame(
            ['call:020', 'begin', 'save:mandate-1', 'save:account-1', 'commit', 'publish:EmployerStatusRefreshed', 'publish:MandateConfirmed'],
            $harness->journal->entries(),
        );
        $mandate = $harness->mandates->get(MandateId::fromString('mandate-1'));
        static::assertSame(MandateState::Confirmed, $mandate->state());
        static::assertTrue($mandate->lastCheck()?->mandatePresent());
        static::assertSame(EmployerStage::MandateConfirmed, $harness->accounts->get(EmployerAccountId::fromString('account-1'))->stage());
    }

    public function testMandateEventAloneCountsAsChanged(): void
    {
        $harness = new Harness();
        $account = $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        $changed = self::refresher($harness)->refresh($account, null);

        static::assertTrue($changed);
        static::assertSame(['MandateConfirmed'], $harness->events->names());
    }

    public function testRepeatedAnswerChangesNothing(): void
    {
        $harness = new Harness();
        $account = $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1, MandateState::Confirmed);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));
        self::refresher($harness)->refresh($account, null);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));
        $harness->journal->clear();

        $changed = self::refresher($harness)->refresh($harness->accounts->get(EmployerAccountId::fromString('account-1')), null);

        static::assertFalse($changed);
        static::assertSame(['call:020', 'begin', 'save:mandate-1', 'save:account-1', 'commit'], $harness->journal->entries());
        static::assertSame(MandateState::Confirmed, $harness->mandates->get(MandateId::fromString('mandate-1'))->state());
    }

    public function testAbsentMandateIsRememberedWithoutTransition(): void
    {
        $harness = new Harness();
        $account = $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1, MandateState::Confirmed);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(false, false));

        self::refresher($harness)->refresh($account, null);

        $mandate = $harness->mandates->get(MandateId::fromString('mandate-1'));
        static::assertSame(MandateState::Confirmed, $mandate->state());
        $check = $mandate->lastCheck();
        static::assertNotNull($check);
        static::assertFalse($check->mandatePresent());
        static::assertSame(Harness::NOW, $check->checkedAt()->toIso8601());
    }

    public function testCancelledMandateIsLeftAlone(): void
    {
        $harness = new Harness();
        $account = $harness->account(1, EmployerStage::MandateCancelled);
        $harness->mandate(1, MandateState::Cancelled);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        self::refresher($harness)->refresh($account, null);

        static::assertSame(MandateState::Cancelled, $harness->mandates->get(MandateId::fromString('mandate-1'))->state());
        static::assertNotContains('save:mandate-1', $harness->journal->entries());
    }

    public function testMandateOfAnotherEmployerOrTenantIsLeftAlone(): void
    {
        $harness = new Harness();
        $account = $harness->account(1);
        $harness->mandate(2);
        $harness->mandate(1, MandateState::Notified, Harness::OTHER_TENANT);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, false));

        self::refresher($harness)->refresh($account, null);

        static::assertNotContains('save:mandate-1', $harness->journal->entries());
        static::assertNotContains('save:mandate-2', $harness->journal->entries());
        static::assertSame(['EmployerStatusRefreshed'], $harness->events->names());
    }

    public function testFailureOfTheCallStoresNothing(): void
    {
        $harness = new Harness();
        $account = $harness->account(1);
        $rejection = UrssafRequestRejected::withErrors(Operation::VerifyEmployer, 400, ApiErrors::none(), ErrorKind::InvalidParameter, null);
        $harness->gateway->willVerifyEmployer($rejection);

        try {
            self::refresher($harness)->refresh($account, null);
            static::fail('Expected a rejection.');
        } catch (UrssafRequestRejected $caught) {
            static::assertSame($rejection, $caught);
        }

        static::assertSame(['call:020'], $harness->journal->entries());
        static::assertNull($harness->accounts->get(EmployerAccountId::fromString('account-1'))->lastCheckedAt());
    }

    private static function refresher(Harness $harness): EmployerStatusRefresher
    {
        return new EmployerStatusRefresher($harness->gateway, $harness->mandates, $harness->accounts, $harness->clock, $harness->unitOfWork);
    }
}
