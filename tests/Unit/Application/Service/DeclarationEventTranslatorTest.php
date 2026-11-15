<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationEventTranslator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\DomainEvent;

final class DeclarationEventTranslatorTest extends TestCase
{
    private const COMMON = [
        'occurredAt' => '2026-11-15T10:00:00Z',
        'tenantId' => 'tenant-1',
        'aggregateId' => 'record-1',
        'externalReference' => 'declaration-1',
    ];

    public function testADeclaredDeclarationIsTranslated(): void
    {
        $translated = DeclarationEventTranslator::translate(IntegrationEventSamples::declarationDeclared());

        static::assertCount(1, $translated);
        static::assertInstanceOf(DeclarationDeclared::class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            ...self::COMMON,
            'declarationId' => 'declaration-1',
        ], $translated[0]->toArray());
    }

    public function testARejectedDeclarationCarriesTheCodesOnly(): void
    {
        $translated = DeclarationEventTranslator::translate(IntegrationEventSamples::declarationRejected('ERR_A', 'ERR_B'));

        static::assertCount(1, $translated);
        static::assertInstanceOf(DeclarationRejected::class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            ...self::COMMON,
            'errorCodes' => ['ERR_A', 'ERR_B'],
        ], $translated[0]->toArray());
        static::assertStringNotContainsString('Free text', serialize($translated[0]));
    }

    #[DataProvider('provideReasons')]
    public function testAnUnknownOutcomeCarriesItsReason(OutcomeUnknownReason $reason, string $expected): void
    {
        $translated = DeclarationEventTranslator::translate(IntegrationEventSamples::declarationOutcomeUnknown($reason));

        static::assertCount(1, $translated);
        static::assertInstanceOf(DeclarationOutcomeUnknown::class, $translated[0]);
        static::assertSame([
            'eventId' => $translated[0]->eventId,
            ...self::COMMON,
            'reason' => $expected,
        ], $translated[0]->toArray());
    }

    /**
     * @return iterable<string, array{OutcomeUnknownReason, string}>
     */
    public static function provideReasons(): iterable
    {
        yield 'may have reached urssaf' => [OutcomeUnknownReason::MayHaveReachedUrssaf, 'may_have_reached_urssaf'];
        yield 'stale submission' => [OutcomeUnknownReason::StaleSubmission, 'stale_submission'];
    }

    public function testAnAlreadyDeclaredDeclarationIsTranslated(): void
    {
        $translated = DeclarationEventTranslator::translate(IntegrationEventSamples::declarationAlreadyDeclared());

        static::assertCount(1, $translated);
        static::assertInstanceOf(DeclarationAlreadyDeclared::class, $translated[0]);
        static::assertSame(['eventId' => $translated[0]->eventId, ...self::COMMON], $translated[0]->toArray());
    }

    public function testEachKindOfFactHasItsOwnId(): void
    {
        $ids = array_map(
            static fn(DomainEvent $event): string => DeclarationEventTranslator::translate($event)[0]->eventId,
            [
                IntegrationEventSamples::declarationDeclared(),
                IntegrationEventSamples::declarationDeclared(),
                IntegrationEventSamples::declarationDeclared('declaration-2'),
                IntegrationEventSamples::declarationRejected('ERR_A'),
                IntegrationEventSamples::declarationRejected('ERR_A', 'ERR_B'),
                IntegrationEventSamples::declarationOutcomeUnknown(),
                IntegrationEventSamples::declarationOutcomeUnknown(OutcomeUnknownReason::StaleSubmission),
                IntegrationEventSamples::declarationAlreadyDeclared(),
            ],
        );

        static::assertSame($ids[0], $ids[1]);
        static::assertCount(7, array_unique($ids));
    }

    public function testEventsWithoutIntegrationEventAreIgnored(): void
    {
        static::assertSame([], DeclarationEventTranslator::translate(IntegrationEventSamples::declarationRecorded()));
        static::assertSame([], DeclarationEventTranslator::translate(IntegrationEventSamples::declarationSubmissionFailed()));
        static::assertSame([], DeclarationEventTranslator::translate(IntegrationEventSamples::declarationCredentialsRejected()));
        static::assertSame([], DeclarationEventTranslator::translate(IntegrationEventSamples::declarationRequeued()));
        static::assertSame([], DeclarationEventTranslator::translate(IntegrationEventSamples::mandateNotified()));
    }
}
