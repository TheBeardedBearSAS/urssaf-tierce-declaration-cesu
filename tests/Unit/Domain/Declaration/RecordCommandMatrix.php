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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;

/**
 * The (command, state) pairs of the transition matrix of a declaration record.
 */
final class RecordCommandMatrix
{
    /**
     * The commands that bring a pending record to the given state.
     *
     * @return list<RecordCommand>
     */
    public static function pathTo(DeclarationState $state): array
    {
        return match ($state) {
            DeclarationState::Pending => [],
            DeclarationState::Submitting => [RecordCommand::StartSubmission],
            DeclarationState::Declared => [RecordCommand::StartSubmission, RecordCommand::MarkDeclared],
            DeclarationState::Rejected => [RecordCommand::StartSubmission, RecordCommand::MarkRejected],
            DeclarationState::AlreadyDeclared => [RecordCommand::StartSubmission, RecordCommand::MarkAlreadyDeclared],
            DeclarationState::OutcomeUnknown => [RecordCommand::StartSubmission, RecordCommand::MarkOutcomeUnknown],
            DeclarationState::Error => [RecordCommand::StartSubmission, RecordCommand::MarkFailed],
            DeclarationState::InvalidCredentials => [RecordCommand::StartSubmission, RecordCommand::MarkInvalidCredentials],
        };
    }

    /**
     * Every (command, state) pair the command may be run from.
     *
     * @return array<string, array{RecordCommand, DeclarationState}>
     */
    public static function allowedPairs(): array
    {
        $pairs = [];
        foreach (RecordCommand::cases() as $command) {
            foreach ($command->allowedFrom() as $from) {
                $pairs[$command->value . ' from ' . $from->value] = [$command, $from];
            }
        }

        return $pairs;
    }

    /**
     * Every (command, state) pair the command is refused from.
     *
     * @return array<string, array{RecordCommand, DeclarationState}>
     */
    public static function refusedPairs(): array
    {
        $pairs = [];
        foreach (RecordCommand::cases() as $command) {
            foreach (DeclarationState::cases() as $from) {
                $pairs[$command->value . ' from ' . $from->value] = [$command, $from];
            }
        }

        return array_diff_key($pairs, self::allowedPairs());
    }
}
