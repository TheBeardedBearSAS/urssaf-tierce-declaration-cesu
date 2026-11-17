<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Policy;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\ErasurePolicies;

/**
 * One method per scenario (ADR 0003, ADR 0009).
 *
 * @mago-expect lint:too-many-methods
 */
final class WorkflowPolicyTest extends TestCase
{
    public function testDefaults(): void
    {
        $policy = new WorkflowPolicy();

        static::assertSame('Europe/Paris', $policy->businessTimeZone()->getName());
        static::assertSame('0 0 15 0', $policy->staleSubmissionAfter()->format('%d %h %i %s'));
        static::assertSame(5, $policy->maxSubmissionAttempts());
        static::assertTrue($policy->requireLocalMandate());
        static::assertFalse($policy->allowFosterCarerDeclarations());
        static::assertFalse($policy->allowErasureWithUnknownOutcomes());
        static::assertFalse($policy->allowErasureWithActiveMandate());
        static::assertSame(100, $policy->pageSize(null));
        static::assertSame(1, $policy->pageSize(1));
        static::assertSame(1000, $policy->pageSize(1000));
        static::assertSame(1000, $policy->pageSize(1001));
    }

    public function testCustomValues(): void
    {
        $timeZone = new DateTimeZone('UTC');
        $stale = new DateInterval('PT1H');
        $policy = new WorkflowPolicy($timeZone, $stale, 1, false, true, 5, 5);

        static::assertSame($timeZone, $policy->businessTimeZone());
        static::assertSame($stale, $policy->staleSubmissionAfter());
        static::assertSame(1, $policy->maxSubmissionAttempts());
        static::assertFalse($policy->requireLocalMandate());
        static::assertTrue($policy->allowFosterCarerDeclarations());
        static::assertSame(5, $policy->pageSize(null));
        static::assertSame(5, $policy->pageSize(6));
        static::assertSame(4, $policy->pageSize(4));
    }

    public function testSmallestValues(): void
    {
        $policy = new WorkflowPolicy(new DateTimeZone('UTC'), new DateInterval('PT1M'), 1, true, false, 1, 1);

        static::assertSame(1, $policy->pageSize(null));
        static::assertSame(1, $policy->pageSize(7));
        static::assertSame(1, $policy->maxSubmissionAttempts());
    }

    public function testADefaultPageSizeEqualToTheMaximumIsAccepted(): void
    {
        static::assertSame(7, (self::policy(5, 7, 7))->pageSize(null));
    }

    public function testRefusesADefaultPageSizeAboveTheMaximum(): void
    {
        try {
            self::policy(5, 11, 10);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.default_page_size_above_max', $exception->violationCode());
            static::assertSame(['default_page_size' => 11, 'max_page_size' => 10], $exception->params());
            static::assertSame('The default page size must not be greater than the maximum page size.', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function invalidPageSizes(): iterable
    {
        yield 'both zero' => [0, 0];
        yield 'default zero' => [0, 10];
        yield 'both negative' => [-2, -1];
    }

    #[DataProvider('invalidPageSizes')]
    public function testRefusesANonPositivePageSize(int $default, int $max): void
    {
        try {
            self::policy(5, $default, $max);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_page_size', $exception->violationCode());
            static::assertSame(['page_size' => $default], $exception->params());
            static::assertSame('Page sizes must be positive integers.', $exception->getMessage());
        }
    }

    public function testRefusesANonPositiveMaximumPageSizeWhenTheDefaultIsValid(): void
    {
        // The default is checked against the maximum first: a valid default cannot be above a non-positive maximum.
        try {
            self::policy(5, 1, 0);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.default_page_size_above_max', $exception->violationCode());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidAttempts(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('invalidAttempts')]
    public function testRefusesANonPositiveMaximumOfAttempts(int $attempts): void
    {
        try {
            self::policy($attempts, 100, 1000);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_max_submission_attempts', $exception->violationCode());
            static::assertSame(['max_submission_attempts' => $attempts], $exception->params());
            static::assertSame('The maximum number of submission attempts must be a positive integer.', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-5];
    }

    #[DataProvider('invalidLimits')]
    public function testRefusesANonPositiveLimit(int $limit): void
    {
        try {
            (new WorkflowPolicy())->pageSize($limit);
            static::fail('Expected an invariant violation.');
        } catch (InvariantViolation $exception) {
            static::assertSame('workflow_policy.invalid_limit', $exception->violationCode());
            static::assertSame(['limit' => $limit], $exception->params());
            static::assertSame('The limit must be a positive integer.', $exception->getMessage());
        }
    }

    public function testTodayIsTheCalendarDateInTheBusinessTimeZone(): void
    {
        $now = new DateTimeImmutable('2026-11-14T23:30:00Z');

        static::assertSame('2026-11-15', (new WorkflowPolicy())->today($now)->toString());
        static::assertSame('2026-11-14', (new WorkflowPolicy(new DateTimeZone('UTC')))->today($now)->toString());
    }

    private static function policy(int $attempts, int $defaultPageSize, int $maxPageSize): WorkflowPolicy
    {
        return new WorkflowPolicy(new DateTimeZone('UTC'), new DateInterval('PT15M'), $attempts, true, false, $defaultPageSize, $maxPageSize);
    }

    public function testTheErasureOptInsAreIndependent(): void
    {
        $unknown = ErasurePolicies::of(true, false);
        $active = ErasurePolicies::of(false, true);

        static::assertTrue($unknown->allowErasureWithUnknownOutcomes());
        static::assertFalse($unknown->allowErasureWithActiveMandate());
        static::assertFalse($active->allowErasureWithUnknownOutcomes());
        static::assertTrue($active->allowErasureWithActiveMandate());
    }
}
