<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetEmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\GetEmployerAccountHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

/**
 * One method per mandate shown (none, active, cancelled) and per selector.
 *
 * @mago-expect lint:too-many-methods
 */
final class GetEmployerAccountHandlerTest extends TestCase
{
    public function testAccountWithoutMandate(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->mandate(1, MandateState::Notified, Harness::OTHER_TENANT);

        $view = self::handler($harness)(self::query());

        static::assertSame('account-1', $view->accountId);
        static::assertSame('employer-1', $view->externalReference);
        static::assertNull($view->mandate);
        static::assertNull($view->lastStatus);
        static::assertSame([], $harness->journal->entries());
    }

    public function testActiveMandateIsShown(): void
    {
        $harness = new Harness();
        $harness->account(1, EmployerStage::MandateConfirmed);
        $harness->mandate(1, MandateState::Confirmed);

        $view = self::handler($harness)(self::query());

        static::assertSame(EmployerStage::MandateConfirmed->value, $view->stage);
        static::assertSame('mandate-1', $view->mandate?->mandateId);
        static::assertTrue($view->lastStatus?->mandatePresent);
    }

    public function testActiveMandateWinsOverAMoreRecentCancelledOne(): void
    {
        $harness = new Harness();
        $harness->account(1);
        self::addMandate($harness, self::notified('mandate-active', '2026-11-01T10:00:00Z'));
        self::addMandate($harness, self::cancelled('mandate-cancelled', '2026-11-10T10:00:00Z'));

        static::assertSame('mandate-active', self::handler($harness)(self::query())->mandate?->mandateId);
    }

    public function testMostRecentCancelledMandateIsShownWhenNoneIsActive(): void
    {
        $harness = new Harness();
        $harness->account(1);
        self::addMandate($harness, self::cancelled('mandate-old', '2026-11-01T10:00:00Z'));
        self::addMandate($harness, self::cancelled('mandate-new', '2026-11-10T10:00:00Z'));

        $view = self::handler($harness)(self::query());

        static::assertSame('mandate-new', $view->mandate?->mandateId);
        static::assertSame('cancelled', $view->mandate->state);
    }

    public function testSelectionByAccountId(): void
    {
        $harness = new Harness();
        $harness->account(2);

        $view = self::handler($harness)(new GetEmployerAccount(Harness::tenant(), EmployerSelector::byAccountId(EmployerAccountId::fromString('account-2'))));

        static::assertSame('account-2', $view->accountId);
    }

    public function testUnknownAccount(): void
    {
        $harness = new Harness();

        try {
            self::handler($harness)(self::query());
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $exception) {
            static::assertSame('employer_account.not_found', $exception->violationCode());
        }
    }

    private static function addMandate(Harness $harness, Mandate $mandate): void
    {
        $mandate->releaseEvents();
        $harness->mandates->add($mandate);
    }

    private static function notified(string $id, string $notifiedAt): Mandate
    {
        return Mandate::notify(
            MandateId::fromString($id),
            Harness::tenant(),
            ExternalReference::fromString('employer-1'),
            Harness::employer(1),
            Harness::at($notifiedAt),
        );
    }

    private static function cancelled(string $id, string $notifiedAt): Mandate
    {
        $mandate = self::notified($id, $notifiedAt);
        $mandate->cancel(Harness::at(Harness::NOW));

        return $mandate;
    }

    private static function handler(Harness $harness): GetEmployerAccountHandler
    {
        return new GetEmployerAccountHandler(new EmployerLocator($harness->accounts), $harness->mandates);
    }

    private static function query(): GetEmployerAccount
    {
        return new GetEmployerAccount(Harness::tenant(), EmployerSelector::byExternalReference(ExternalReference::fromString('employer-1')));
    }
}
