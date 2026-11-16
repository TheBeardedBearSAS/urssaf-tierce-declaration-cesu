<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Contract\Event;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateNotified;

final class IntegrationEventsTest extends TestCase
{
    private const COMMON = [
        'eventId' => 'event-1',
        'occurredAt' => '2026-11-15T10:00:00Z',
        'tenantId' => 'tenant-1',
        'aggregateId' => 'aggregate-1',
        'externalReference' => 'reference-1',
    ];

    /**
     * @param array<string, mixed> $specific
     */
    #[DataProvider('provideEvents')]
    public function testAnEventExposesItsTypeVersionAndProperties(IntegrationEvent $event, string $type, array $specific): void
    {
        static::assertSame($type, $event->type());
        static::assertSame($event::TYPE, $type);
        static::assertSame(1, $event->version());
        static::assertSame([...self::COMMON, ...$specific], $event->toArray());
    }

    /**
     * @return iterable<string, array{IntegrationEvent, string, array<string, mixed>}>
     */
    public static function provideEvents(): iterable
    {
        yield 'employer registered' => [
            new EmployerRegistered('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'Z0003752390009', 'imported'),
            'urssaf.cesu.employer.registered',
            ['cesuNumber' => 'Z0003752390009', 'origin' => 'imported'],
        ];
        yield 'employer status changed' => [
            new EmployerStatusChanged('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'Z0003752390009', 'mandate_notified', 'mandate_confirmed', true, false, 'actif', 'inactif'),
            'urssaf.cesu.employer.status_changed',
            [
                'cesuNumber' => 'Z0003752390009',
                'previousStage' => 'mandate_notified',
                'stage' => 'mandate_confirmed',
                'mandatePresent' => true,
                'aiciActive' => false,
                'onlineAccountState' => 'actif',
                'sepaMandateState' => 'inactif',
            ],
        ];
        yield 'employer forgotten' => [
            new EmployerForgotten('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'Z0003752390009', ['mandate-1', 'mandate-2'], ['record-1']),
            'urssaf.cesu.employer.forgotten',
            ['cesuNumber' => 'Z0003752390009', 'mandateIds' => ['mandate-1', 'mandate-2'], 'declarationRecordIds' => ['record-1']],
        ];
        yield 'mandate notified' => [
            new MandateNotified('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'Z0003752390009'),
            'urssaf.cesu.mandate.notified',
            ['cesuNumber' => 'Z0003752390009'],
        ];
        yield 'mandate confirmed' => [
            new MandateConfirmed('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'Z0003752390009'),
            'urssaf.cesu.mandate.confirmed',
            ['cesuNumber' => 'Z0003752390009'],
        ];
        yield 'mandate cancelled' => [
            new MandateCancelled('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'Z0003752390009'),
            'urssaf.cesu.mandate.cancelled',
            ['cesuNumber' => 'Z0003752390009'],
        ];
        yield 'declaration declared' => [
            new DeclarationDeclared('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'declaration-1'),
            'urssaf.cesu.declaration.declared',
            ['declarationId' => 'declaration-1'],
        ];
        yield 'declaration rejected' => [
            new DeclarationRejected('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', ['ERR_A', 'ERR_B']),
            'urssaf.cesu.declaration.rejected',
            ['errorCodes' => ['ERR_A', 'ERR_B']],
        ];
        yield 'declaration outcome unknown' => [
            new DeclarationOutcomeUnknown('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1', 'stale_submission'),
            'urssaf.cesu.declaration.outcome_unknown',
            ['reason' => 'stale_submission'],
        ];
        yield 'declaration already declared' => [
            new DeclarationAlreadyDeclared('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'aggregate-1', 'reference-1'),
            'urssaf.cesu.declaration.already_declared',
            [],
        ];
    }

    public function testEveryTypeIsUnique(): void
    {
        $types = [];
        foreach (self::provideEvents() as [$event]) {
            $types[] = $event->type();
        }

        static::assertCount(10, array_unique($types));
    }
}
