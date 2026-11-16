<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Contract\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerStatusChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEventCodec;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\MandateNotified;

final class IntegrationEventCodecTest extends TestCase
{
    #[DataProvider('provideEvents')]
    public function testAnEventIsDecodedAsEncoded(IntegrationEvent $event): void
    {
        static::assertEquals($event, IntegrationEventCodec::decode(IntegrationEventCodec::encode($event)));
        static::assertContains($event::class, IntegrationEventCodec::CLASSES);
    }

    /**
     * @return iterable<string, array{IntegrationEvent}>
     */
    public static function provideEvents(): iterable
    {
        yield 'employer registered' => [new EmployerRegistered('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'Z0003752390009', 'registered')];
        yield 'employer status changed' => [new EmployerStatusChanged('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'Z0003752390009', 'registered', 'mandate_notified', null, null, null, null)];
        yield 'employer forgotten' => [new EmployerForgotten('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'Z0003752390009', ['m1', 'm2'], ['r1'])];
        yield 'mandate notified' => [new MandateNotified('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'Z0003752390009')];
        yield 'mandate confirmed' => [new MandateConfirmed('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'Z0003752390009')];
        yield 'mandate cancelled' => [new MandateCancelled('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'Z0003752390009')];
        yield 'declaration declared' => [new DeclarationDeclared('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'd')];
        yield 'declaration rejected' => [new DeclarationRejected('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', ['ERR_A'])];
        yield 'declaration outcome unknown' => [new DeclarationOutcomeUnknown('e', '2026-11-15T10:00:00Z', 't', 'a', 'r', 'stale_submission')];
        yield 'declaration already declared' => [new DeclarationAlreadyDeclared('e', '2026-11-15T10:00:00Z', 't', 'a', 'r')];
    }

    #[DataProvider('provideForeignData')]
    public function testOnlyEncodedIntegrationEventsAreDecoded(string $data): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The data is not an encoded integration event.');

        IntegrationEventCodec::decode($data);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignData(): iterable
    {
        yield 'text' => [serialize('text')];
        yield 'foreign object' => [serialize(new stdClass())];
    }

    public function testNoOtherClassIsInstantiatedWhileDecoding(): void
    {
        try {
            IntegrationEventCodec::decode(serialize(new WakeUpRecorder()));
            static::fail('The data should be refused.');
        } catch (InvalidArgumentException $failure) {
            static::assertSame('The data is not an encoded integration event.', $failure->getMessage());
        }

        static::assertFalse(WakeUpRecorder::wokenUp());
    }
}
