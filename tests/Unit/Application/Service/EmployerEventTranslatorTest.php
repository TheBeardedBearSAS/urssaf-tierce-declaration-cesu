<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;

/**
 * One method per scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class EmployerEventTranslatorTest extends TestCase
{
    public function testARegisteredEmployerIsTranslated(): void
    {
        $translated = EmployerEventTranslator::translate(IntegrationEventSamples::employerRegistered());

        static::assertCount(1, $translated);
        static::assertInstanceOf(EmployerRegistered::class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            'occurredAt' => '2026-11-15T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'account-1',
            'externalReference' => 'employer-1',
            'cesuNumber' => 'Z0003752390009',
            'origin' => 'registered',
        ], $translated[0]->toArray());
    }

    public function testTheOriginIsPartOfTheFact(): void
    {
        $registered = EmployerEventTranslator::translate(IntegrationEventSamples::employerRegistered());
        $imported = EmployerEventTranslator::translate(IntegrationEventSamples::employerRegistered(RegistrationOrigin::Imported));

        static::assertInstanceOf(EmployerRegistered::class, $imported[0]);
        static::assertSame('imported', $imported[0]->origin);
        static::assertNotSame($registered[0]->eventId, $imported[0]->eventId);
    }

    public function testAStatusRefreshCarriesTheRawAnswerOf020(): void
    {
        $status = new EmployerStatus(true, 'actif', 'inconnu-futur', true, 'actif', 'Motif libre', true);

        $translated = EmployerEventTranslator::translate(IntegrationEventSamples::employerStatusRefreshed(EmployerStage::MandateConfirmed, EmployerStage::AiciActive, $status));

        static::assertCount(1, $translated);
        static::assertInstanceOf(EmployerStatusChanged::class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            'occurredAt' => '2026-11-15T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'account-1',
            'externalReference' => 'employer-1',
            'cesuNumber' => 'Z0003752390009',
            'previousStage' => 'mandate_confirmed',
            'stage' => 'aici_active',
            'mandatePresent' => true,
            'aiciActive' => true,
            'onlineAccountState' => 'actif',
            'sepaMandateState' => 'inconnu-futur',
        ], $translated[0]->toArray());
    }

    public function testAStatusRefreshWithoutOptionalStatesKeepsThemNull(): void
    {
        $status = new EmployerStatus(false, null, null, false, null, null, false);

        $translated = EmployerEventTranslator::translate(IntegrationEventSamples::employerStatusRefreshed(EmployerStage::Registered, EmployerStage::Registered, $status));

        static::assertInstanceOf(EmployerStatusChanged::class, $translated[0]);
        static::assertFalse($translated[0]->mandatePresent);
        static::assertFalse($translated[0]->aiciActive);
        static::assertNull($translated[0]->onlineAccountState);
        static::assertNull($translated[0]->sepaMandateState);
    }

    public function testEveryPartOfTheStatusIsPartOfTheFact(): void
    {
        $ids = array_map(
            static fn(array $parts): string => EmployerEventTranslator::translate(IntegrationEventSamples::employerStatusRefreshed(
                $parts[0],
                $parts[1],
                new EmployerStatus(true, $parts[4], $parts[5], $parts[2], null, null, $parts[3]),
            ))[0]->eventId,
            [
                [EmployerStage::Registered, EmployerStage::MandateConfirmed, true, false, 'actif', 'actif'],
                [EmployerStage::Registered, EmployerStage::MandateConfirmed, true, false, 'actif', 'actif'],
                [EmployerStage::MandateNotified, EmployerStage::MandateConfirmed, true, false, 'actif', 'actif'],
                [EmployerStage::Registered, EmployerStage::AiciActive, true, false, 'actif', 'actif'],
                [EmployerStage::Registered, EmployerStage::MandateConfirmed, false, false, 'actif', 'actif'],
                [EmployerStage::Registered, EmployerStage::MandateConfirmed, true, true, 'actif', 'actif'],
                [EmployerStage::Registered, EmployerStage::MandateConfirmed, true, false, 'bloque', 'actif'],
                [EmployerStage::Registered, EmployerStage::MandateConfirmed, true, false, 'actif', 'bloque'],
            ],
        );

        static::assertSame($ids[0], $ids[1]);
        static::assertCount(7, array_unique($ids));
    }

    public function testALocalStageChangeHasNoStatusSnapshot(): void
    {
        $translated = EmployerEventTranslator::translate(IntegrationEventSamples::employerStageChanged(EmployerStage::MandateNotified, EmployerStage::MandateCancelled));

        static::assertCount(1, $translated);
        static::assertInstanceOf(EmployerStatusChanged::class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            'occurredAt' => '2026-11-15T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'account-1',
            'externalReference' => 'employer-1',
            'cesuNumber' => 'Z0003752390009',
            'previousStage' => 'mandate_notified',
            'stage' => 'mandate_cancelled',
            'mandatePresent' => null,
            'aiciActive' => null,
            'onlineAccountState' => null,
            'sepaMandateState' => null,
        ], $translated[0]->toArray());
    }

    public function testBothStagesOfALocalChangeArePartOfTheFact(): void
    {
        $ids = array_map(
            static fn(EmployerStage $to): string => EmployerEventTranslator::translate(IntegrationEventSamples::employerStageChanged(EmployerStage::Registered, $to))[0]->eventId,
            [EmployerStage::MandateNotified, EmployerStage::MandateCancelled],
        );
        $other = EmployerEventTranslator::translate(IntegrationEventSamples::employerStageChanged(EmployerStage::MandateNotified, EmployerStage::MandateNotified))[0]->eventId;

        static::assertCount(3, array_unique([...$ids, $other]));
    }

    public function testAForgottenEmployerCarriesTheCesuNumberAndTheErasedIds(): void
    {
        $translated = EmployerEventTranslator::translate(IntegrationEventSamples::employerForgotten());

        static::assertCount(1, $translated);
        static::assertInstanceOf(EmployerForgotten::class, $translated[0]);
        static::assertSame('urssaf.cesu.employer.forgotten', $translated[0]->type());
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            'occurredAt' => '2026-11-15T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'account-1',
            'externalReference' => 'employer-1',
            'cesuNumber' => 'Z0003752390009',
            'mandateIds' => ['mandate-1', 'mandate-2'],
            'declarationRecordIds' => ['record-1', 'record-2', 'record-3'],
        ], $translated[0]->toArray());
    }

    public function testAForgottenEmployerWithoutMandateNorRecordHasEmptyLists(): void
    {
        $translated = EmployerEventTranslator::translate(IntegrationEventSamples::employerForgotten(IntegrationEventSamples::CESU, [], []));

        static::assertInstanceOf(EmployerForgotten::class, $translated[0]);
        static::assertSame([], $translated[0]->mandateIds);
        static::assertSame([], $translated[0]->declarationRecordIds);
    }

    public function testTheForgottenEventIdIsStableForTheSameFactAndDependsOnTheCesuNumber(): void
    {
        $ids = array_map(
            static fn(string $cesu): string => EmployerEventTranslator::translate(IntegrationEventSamples::employerForgotten($cesu))[0]->eventId,
            ['Z0003752390009', 'Z0003752390009', 'Y0000000000002'],
        );

        static::assertSame($ids[0], $ids[1]);
        static::assertNotSame($ids[0], $ids[2]);
        static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $ids[0]);
    }

    public function testOtherEventsHaveNoEmployerIntegrationEvent(): void
    {
        static::assertSame([], EmployerEventTranslator::translate(IntegrationEventSamples::mandateNotified()));
        static::assertSame([], EmployerEventTranslator::translate(IntegrationEventSamples::declarationRecorded()));
    }
}
