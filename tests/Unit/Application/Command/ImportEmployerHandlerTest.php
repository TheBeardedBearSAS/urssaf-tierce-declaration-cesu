<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ImportEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\EmployerAlreadyTracked;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

final class ImportEmployerHandlerTest extends TestCase
{
    public function testImportsTheEmployerWithoutAnyCall(): void
    {
        $harness = new Harness();

        $view = self::handler($harness)(self::command());

        static::assertSame(['begin', 'save:id-1', 'commit', 'publish:EmployerRegistered'], $harness->journal->entries());
        static::assertSame([], $harness->gateway->calls());
        static::assertSame('id-1', $view->accountId);
        static::assertSame(Harness::TENANT, $view->tenantId);
        static::assertSame('employer-1', $view->externalReference);
        static::assertSame(Harness::cesuNumber(1)->value(), $view->cesuNumber);
        static::assertSame('imported', $view->origin);
        static::assertSame('registered', $view->stage);
        static::assertNull($view->mandate);
        static::assertSame(Harness::NOW, $view->createdAt);
        $stored = $harness->accounts->get(EmployerAccountId::fromString('id-1'));
        static::assertTrue($stored->employer()->equals(Harness::employer(1)));
    }

    public function testReplayReturnsTheTrackedAccountWithItsActiveMandate(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateNotified);
        $harness->mandate(1);

        $view = self::handler($harness)(self::command());

        static::assertSame([], $harness->journal->entries());
        static::assertSame('account-1', $view->accountId);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertSame(MandateState::Notified->value, $view->mandate->state);
    }

    public function testReplayWithoutMandate(): void
    {
        $harness = new Harness();
        $harness->account(1);

        $view = self::handler($harness)(self::command());

        static::assertSame('account-1', $view->accountId);
        static::assertNull($view->mandate);
    }

    public function testReplayIgnoresACancelledMandate(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateCancelled);
        $harness->mandate(1, MandateState::Cancelled);

        static::assertNull(self::handler($harness)(self::command())->mandate);
    }

    public function testSameReferenceWithAnotherEmployerIsRefused(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $command = new ImportEmployer(Harness::tenant(), ExternalReference::fromString('employer-1'), Employer::of(Harness::cesuNumber(1), CalendarDate::fromString('1980-02-02')));

        try {
            self::handler($harness)($command);
            static::fail('Expected a refusal.');
        } catch (EmployerAlreadyTracked $exception) {
            static::assertSame(['external_reference' => 'employer-1', 'employer_account_id' => 'account-1'], $exception->params());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testCesuNumberTrackedUnderAnotherReferenceIsRefused(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $command = new ImportEmployer(Harness::tenant(), ExternalReference::fromString('other'), Harness::employer(1));

        try {
            self::handler($harness)($command);
            static::fail('Expected a refusal.');
        } catch (EmployerAlreadyTracked $exception) {
            static::assertSame(['cesu_number' => Harness::cesuNumber(1)->value(), 'employer_account_id' => 'account-1'], $exception->params());
        }

        static::assertSame([], $harness->journal->entries());
    }

    public function testAnotherTenantDoesNotConflict(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::Registered, Harness::OTHER_TENANT);

        static::assertSame('id-1', self::handler($harness)(self::command())->accountId);
    }

    private static function handler(Harness $harness): ImportEmployerHandler
    {
        return new ImportEmployerHandler($harness->accounts, $harness->mandates, $harness->ids, $harness->clock, $harness->unitOfWork);
    }

    private static function command(): ImportEmployer
    {
        return new ImportEmployer(Harness::tenant(), ExternalReference::fromString('employer-1'), Harness::employer(1));
    }
}
