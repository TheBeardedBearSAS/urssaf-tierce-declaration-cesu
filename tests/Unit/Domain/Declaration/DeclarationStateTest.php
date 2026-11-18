<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;

final class DeclarationStateTest extends TestCase
{
    /**
     * @return iterable<string, array{DeclarationState, list<DeclarationState>}>
     */
    public static function provideTransitions(): iterable
    {
        yield 'pending' => [DeclarationState::Pending, [DeclarationState::Submitting]];
        yield 'submitting' => [
            DeclarationState::Submitting,
            [
                DeclarationState::Declared,
                DeclarationState::Rejected,
                DeclarationState::AlreadyDeclared,
                DeclarationState::OutcomeUnknown,
                DeclarationState::Error,
                DeclarationState::InvalidCredentials,
            ],
        ];
        yield 'declared' => [DeclarationState::Declared, []];
        yield 'rejected' => [DeclarationState::Rejected, []];
        yield 'already declared' => [DeclarationState::AlreadyDeclared, []];
        yield 'outcome unknown' => [DeclarationState::OutcomeUnknown, [DeclarationState::Declared, DeclarationState::Pending]];
        yield 'error' => [DeclarationState::Error, [DeclarationState::Pending]];
        yield 'invalid credentials' => [DeclarationState::InvalidCredentials, [DeclarationState::Pending]];
    }

    /**
     * @param list<DeclarationState> $allowed
     */
    #[DataProvider('provideTransitions')]
    public function testAllowedTransitions(DeclarationState $from, array $allowed): void
    {
        static::assertSame($allowed, $from->allowedTransitions());
        static::assertSame($allowed === [], $from->isFinal());
        foreach (DeclarationState::cases() as $target) {
            static::assertSame(\in_array($target, $allowed, true), $from->canTransitionTo($target), $from->value . ' -> ' . $target->value);
        }
    }

    /**
     * @param list<DeclarationState> $allowed
     */
    #[DataProvider('provideTransitions')]
    public function testTransitionToReturnsTheTargetOrRefuses(DeclarationState $from, array $allowed): void
    {
        foreach (DeclarationState::cases() as $target) {
            if (\in_array($target, $allowed, true)) {
                static::assertSame($target, $from->transitionTo($target));

                continue;
            }
            try {
                $from->transitionTo($target);
                static::fail('Expected a refused transition.');
            } catch (InvalidStateTransition $exception) {
                static::assertSame('declaration_record.invalid_state_transition', $exception->violationCode());
                static::assertSame('declaration_record', $exception->aggregate());
                static::assertSame($from->value, $exception->from());
                static::assertSame($target->value, $exception->to());
            }
        }
    }

    public function testWireValues(): void
    {
        static::assertSame(
            ['pending', 'submitting', 'declared', 'rejected', 'already_declared', 'outcome_unknown', 'error', 'invalid_credentials'],
            array_map(static fn(DeclarationState $state): string => $state->value, DeclarationState::cases()),
        );
    }
}
