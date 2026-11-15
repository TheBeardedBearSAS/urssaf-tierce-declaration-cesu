<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Outbox;

use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Outbox\MessengerIntegrationEventSink;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\RecordingMessageBus;

final class MessengerIntegrationEventSinkTest extends TestCase
{
    public function testTheEventIsDispatchedOnTheBusAsIs(): void
    {
        $bus = new RecordingMessageBus();
        $event = self::event();

        (new MessengerIntegrationEventSink($bus))->dispatch($event);

        static::assertSame([$event], $bus->messages());
    }

    public function testAFailureOfTheBusPropagatesSoThatTheEventStaysInTheOutbox(): void
    {
        $bus = self::createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willThrowException(new LogicException('transport down'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('transport down');

        (new MessengerIntegrationEventSink($bus))->dispatch(self::event());
    }

    private static function event(): EmployerRegistered
    {
        return new EmployerRegistered('event-1', '2026-11-15T10:00:00Z', 'tenant-1', 'account-1', 'employer-1', 'Z0003752390009', 'imported');
    }
}
