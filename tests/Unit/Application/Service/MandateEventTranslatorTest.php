<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\MandateEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

final class MandateEventTranslatorTest extends TestCase
{
    /**
     * @param class-string<IntegrationEvent> $class
     */
    #[DataProvider('provideMandateEvents')]
    public function testAMandateEventIsTranslated(DomainEvent $event, string $class): void
    {
        $translated = MandateEventTranslator::translate($event);

        static::assertCount(1, $translated);
        static::assertInstanceOf($class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            'occurredAt' => '2026-11-15T10:00:00Z',
            'tenantId' => 'tenant-1',
            'aggregateId' => 'mandate-1',
            'externalReference' => 'employer-1',
            'cesuNumber' => 'Z0003752390009',
        ], $translated[0]->toArray());
    }

    /**
     * @return iterable<string, array{DomainEvent, class-string<IntegrationEvent>}>
     */
    public static function provideMandateEvents(): iterable
    {
        yield 'notified' => [IntegrationEventSamples::mandateNotified(), MandateNotified::class];
        yield 'confirmed' => [IntegrationEventSamples::mandateConfirmed(), MandateConfirmed::class];
        yield 'cancelled' => [IntegrationEventSamples::mandateCancelled(), MandateCancelled::class];
    }

    public function testEachKindOfFactHasItsOwnId(): void
    {
        $ids = array_map(
            static fn(DomainEvent $event): string => MandateEventTranslator::translate($event)[0]->eventId,
            [
                IntegrationEventSamples::mandateNotified(),
                IntegrationEventSamples::mandateNotified(),
                IntegrationEventSamples::mandateNotified('mandate-2'),
                IntegrationEventSamples::mandateConfirmed(),
                IntegrationEventSamples::mandateCancelled(),
            ],
        );

        static::assertSame($ids[0], $ids[1]);
        static::assertCount(4, array_unique($ids));
    }

    public function testOtherEventsHaveNoMandateIntegrationEvent(): void
    {
        static::assertSame([], MandateEventTranslator::translate(IntegrationEventSamples::employerRegistered()));
        static::assertSame([], MandateEventTranslator::translate(IntegrationEventSamples::declarationDeclared()));
    }
}
