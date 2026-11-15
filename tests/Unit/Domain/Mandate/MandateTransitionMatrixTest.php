<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Mandate;

use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Every command of the aggregate in every state: the resulting state, or the refusal.
 */
final class MandateTransitionMatrixTest extends TestCase
{
    /**
     * @return iterable<string, array{MandateState, string, MandateState|null}>
     */
    public static function provideMatrix(): iterable
    {
        yield 'notified, status present' => [MandateState::Notified, 'present', MandateState::Confirmed];
        yield 'notified, status absent' => [MandateState::Notified, 'absent', MandateState::Notified];
        yield 'notified, cancel' => [MandateState::Notified, 'cancel', MandateState::Cancelled];
        yield 'confirmed, status present' => [MandateState::Confirmed, 'present', MandateState::Confirmed];
        yield 'confirmed, status absent' => [MandateState::Confirmed, 'absent', MandateState::Confirmed];
        yield 'confirmed, cancel' => [MandateState::Confirmed, 'cancel', MandateState::Cancelled];
        yield 'cancelled, status present' => [MandateState::Cancelled, 'present', null];
        yield 'cancelled, status absent' => [MandateState::Cancelled, 'absent', MandateState::Cancelled];
        yield 'cancelled, cancel' => [MandateState::Cancelled, 'cancel', null];
    }

    #[DataProvider('provideMatrix')]
    public function testMatrix(MandateState $from, string $command, ?MandateState $expected): void
    {
        $mandate = MandateSamples::inState($from);
        $now = MandateSamples::later();
        if (!$expected instanceof MandateState) {
            try {
                self::execute($mandate, $command, $now);
                static::fail('Expected an InvalidStateTransition.');
            } catch (InvalidStateTransition $exception) {
                static::assertSame('mandate.invalid_state_transition', $exception->violationCode());
                static::assertSame($from->value, $exception->from());
            }
            static::assertSame($from, $mandate->state());
            static::assertSame([], $mandate->releaseEvents());

            return;
        }
        self::execute($mandate, $command, $now);

        static::assertSame($expected, $mandate->state());
    }

    /**
     * @throws InvalidStateTransition
     */
    private static function execute(Mandate $mandate, string $command, Instant $now): void
    {
        match ($command) {
            'present' => $mandate->applyStatus(true, $now),
            'absent' => $mandate->applyStatus(false, $now),
            'cancel' => $mandate->cancel($now),
            default => throw new LogicException('Unknown command.'),
        };
    }
}
