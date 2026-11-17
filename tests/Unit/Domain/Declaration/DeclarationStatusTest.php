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

use DateInterval;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class DeclarationStatusTest extends TestCase
{
    public function testConstructorKeepsEverything(): void
    {
        $errors = [DeclarationSamples::submissionError('E1')];
        $result = DeclarationSamples::result();
        $status = new DeclarationStatus(
            DeclarationState::Declared,
            3,
            DeclarationSamples::later(-10),
            $errors,
            $result->declarationId(),
            $result,
            OutcomeUnknownReason::StaleSubmission,
            DeclarationSamples::later(-5),
        );

        static::assertSame(DeclarationState::Declared, $status->state());
        static::assertSame(3, $status->attempts());
        static::assertTrue(DeclarationSamples::later(-10)->equals($status->lastAttemptAt() ?? DeclarationSamples::now()));
        static::assertSame($errors, $status->errors());
        static::assertSame($result->declarationId(), $status->declarationId());
        static::assertSame($result, $status->result());
        static::assertSame(OutcomeUnknownReason::StaleSubmission, $status->outcomeUnknownReason());
        static::assertTrue(DeclarationSamples::later(-5)->equals($status->updatedAt()));
    }

    public function testNegativeAttemptsAreRefused(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'declaration_record.negative_attempts',
            static fn(): DeclarationStatus => new DeclarationStatus(DeclarationState::Pending, -1, null, [], null, null, null, DeclarationSamples::now()),
        );

        static::assertSame(['attempts' => -1], $exception->params());
        static::assertSame('The number of submission attempts must not be negative.', $exception->getMessage());
        static::assertSame(0, (new DeclarationStatus(DeclarationState::Pending, 0, null, [], null, null, null, DeclarationSamples::now()))->attempts());
    }

    public function testADeclaredStatusRequiresTheDeclarationId(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'declaration_record.declaration_id_required',
            static fn(): DeclarationStatus => new DeclarationStatus(DeclarationState::Declared, 1, null, [], null, null, null, DeclarationSamples::now()),
        );

        static::assertInstanceOf(InvariantViolation::class, $exception);
        static::assertSame('A declared record requires the declaration id.', $exception->getMessage());
        static::assertNull((new DeclarationStatus(DeclarationState::Rejected, 1, null, [], null, null, null, DeclarationSamples::now()))->declarationId());
    }

    public function testAnUnknownOutcomeRequiresTheReason(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            'declaration_record.outcome_unknown_reason_required',
            static fn(): DeclarationStatus => new DeclarationStatus(DeclarationState::OutcomeUnknown, 1, null, [], null, null, null, DeclarationSamples::now()),
        );

        static::assertSame('A record with an unknown outcome requires the reason.', $exception->getMessage());
        static::assertNull((new DeclarationStatus(DeclarationState::Error, 1, null, [], null, null, null, DeclarationSamples::now()))->outcomeUnknownReason());
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function provideAttemptsLeft(): iterable
    {
        yield 'one below' => [4, 5, true];
        yield 'reached' => [5, 5, false];
        yield 'above' => [6, 5, false];
        yield 'none yet' => [0, 5, true];
    }

    #[DataProvider('provideAttemptsLeft')]
    public function testHasAttemptsLeft(int $attempts, int $max, bool $expected): void
    {
        $status = new DeclarationStatus(DeclarationState::Error, $attempts, null, [], null, null, null, DeclarationSamples::now());

        static::assertSame($expected, $status->hasAttemptsLeft($max));
    }

    /**
     * @return iterable<string, array{int, bool}>
     */
    public static function provideStaleness(): iterable
    {
        yield 'one second before the age' => [-1, false];
        yield 'exactly the age' => [0, true];
        yield 'one second after the age' => [1, true];
    }

    #[DataProvider('provideStaleness')]
    public function testIsLastAttemptStartedBefore(int $offset, bool $expected): void
    {
        $lastAttempt = Instant::fromIso8601('2026-11-15T10:00:00Z');
        $status = new DeclarationStatus(DeclarationState::Submitting, 1, $lastAttempt, [], null, null, null, $lastAttempt);
        $now = DeclarationSamples::later(600 + $offset);

        static::assertSame($expected, $status->isLastAttemptStartedBefore($now, new DateInterval('PT10M')));
    }

    public function testNeverStartedIsNotStale(): void
    {
        $status = new DeclarationStatus(DeclarationState::Submitting, 0, null, [], null, null, null, DeclarationSamples::now());

        static::assertFalse($status->isLastAttemptStartedBefore(DeclarationSamples::later(86_400), new DateInterval('PT1S')));
    }
}
