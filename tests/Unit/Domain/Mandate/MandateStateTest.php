<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;

final class MandateStateTest extends TestCase
{
    /**
     * @return iterable<string, array{MandateState, MandateState}>
     */
    public static function provideAllowedTransitions(): iterable
    {
        yield 'notified to confirmed' => [MandateState::Notified, MandateState::Confirmed];
        yield 'notified to cancelled' => [MandateState::Notified, MandateState::Cancelled];
        yield 'confirmed to cancelled' => [MandateState::Confirmed, MandateState::Cancelled];
    }

    #[DataProvider('provideAllowedTransitions')]
    public function testAllowedTransition(MandateState $from, MandateState $to): void
    {
        static::assertTrue($from->canTransitionTo($to));
        static::assertSame($to, $from->transitionTo($to));
    }

    /**
     * @return iterable<string, array{MandateState, MandateState}>
     */
    public static function provideRefusedTransitions(): iterable
    {
        yield 'notified to notified' => [MandateState::Notified, MandateState::Notified];
        yield 'confirmed to notified' => [MandateState::Confirmed, MandateState::Notified];
        yield 'confirmed to confirmed' => [MandateState::Confirmed, MandateState::Confirmed];
        yield 'cancelled to notified' => [MandateState::Cancelled, MandateState::Notified];
        yield 'cancelled to confirmed' => [MandateState::Cancelled, MandateState::Confirmed];
        yield 'cancelled to cancelled' => [MandateState::Cancelled, MandateState::Cancelled];
    }

    #[DataProvider('provideRefusedTransitions')]
    public function testRefusedTransition(MandateState $from, MandateState $to): void
    {
        static::assertFalse($from->canTransitionTo($to));
        try {
            $from->transitionTo($to);
            static::fail('Expected an InvalidStateTransition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('mandate.invalid_state_transition', $exception->violationCode());
            static::assertSame('mandate', $exception->aggregate());
            static::assertSame($from->value, $exception->from());
            static::assertSame($to->value, $exception->to());
        }
    }

    public function testAllowedTransitions(): void
    {
        static::assertSame([MandateState::Confirmed, MandateState::Cancelled], MandateState::Notified->allowedTransitions());
        static::assertSame([MandateState::Cancelled], MandateState::Confirmed->allowedTransitions());
        static::assertSame([], MandateState::Cancelled->allowedTransitions());
    }

    public function testActiveAndFinal(): void
    {
        static::assertTrue(MandateState::Notified->isActive());
        static::assertTrue(MandateState::Confirmed->isActive());
        static::assertFalse(MandateState::Cancelled->isActive());
        static::assertFalse(MandateState::Notified->isFinal());
        static::assertFalse(MandateState::Confirmed->isFinal());
        static::assertTrue(MandateState::Cancelled->isFinal());
    }

    public function testWireValues(): void
    {
        static::assertSame(['notified', 'confirmed', 'cancelled'], array_map(static fn(MandateState $state): string => $state->value, MandateState::cases()));
    }
}
