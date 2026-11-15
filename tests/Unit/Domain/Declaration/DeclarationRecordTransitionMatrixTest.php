<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;

/**
 * Every command against every state: the allowed ones reach their target, the others leave state, status and events
 * untouched and report the refused transition.
 */
final class DeclarationRecordTransitionMatrixTest extends TestCase
{
    private static function recordIn(DeclarationState $state): DeclarationRecord
    {
        $record = DeclarationSamples::record();
        foreach (RecordCommandMatrix::pathTo($state) as $step => $command) {
            $command->run($record, DeclarationSamples::later($step + 1));
        }
        $record->releaseEvents();

        return $record;
    }

    /**
     * @return array<string, array{RecordCommand, DeclarationState}>
     */
    public static function provideAllowedTransitions(): array
    {
        return RecordCommandMatrix::allowedPairs();
    }

    /**
     * @return array<string, array{RecordCommand, DeclarationState}>
     */
    public static function provideRefusedTransitions(): array
    {
        return RecordCommandMatrix::refusedPairs();
    }

    #[DataProvider('provideAllowedTransitions')]
    public function testAnAllowedCommandReachesItsTarget(RecordCommand $command, DeclarationState $from): void
    {
        $record = self::recordIn($from);
        $now = DeclarationSamples::later(100);

        $command->run($record, $now);

        static::assertSame($command->target(), $record->state());
        static::assertSame($now, $record->updatedAt());
        static::assertCount($command === RecordCommand::StartSubmission ? 0 : 1, $record->releaseEvents());
    }

    #[DataProvider('provideRefusedTransitions')]
    public function testARefusedCommandChangesNothing(RecordCommand $command, DeclarationState $from): void
    {
        $record = self::recordIn($from);
        $statusBefore = $record->status();

        try {
            $command->run($record, DeclarationSamples::later(100));
            static::fail('Expected a refused transition.');
        } catch (InvalidStateTransition $exception) {
            static::assertSame('declaration_record.invalid_state_transition', $exception->violationCode());
            static::assertSame('declaration_record', $exception->aggregate());
            static::assertSame($from->value, $exception->from());
            static::assertSame($command->target()->value, $exception->to());
        }
        static::assertSame($from, $record->state());
        static::assertSame($statusBefore, $record->status());
        static::assertSame([], $record->releaseEvents());
    }

    public function testEveryStateIsReachable(): void
    {
        foreach (DeclarationState::cases() as $state) {
            static::assertSame($state, self::recordIn($state)->state());
        }
    }

    public function testTheCommandsFollowTheStateMachine(): void
    {
        $reachable = [];
        foreach (RecordCommand::cases() as $command) {
            foreach ($command->allowedFrom() as $from) {
                $reachable[$from->value . '>' . $command->target()->value] = true;
            }
        }
        $expected = [];
        foreach (DeclarationState::cases() as $from) {
            foreach ($from->allowedTransitions() as $target) {
                $expected[$from->value . '>' . $target->value] = true;
            }
        }

        static::assertEquals($expected, $reachable);
    }
}
