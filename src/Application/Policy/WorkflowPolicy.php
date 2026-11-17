<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;

/**
 * Configuration shared by the use case handlers (provided by the framework bridge). ADR 0003, ADR 0009.
 *
 * - businessTimeZone: time zone of the business rules ("today", employment not started after today);
 * - staleSubmissionAfter: how long a submission may stay unanswered before being reconciled (outcome unknown);
 * - maxSubmissionAttempts: submission attempts after which a technical failure is not requeued automatically;
 * - requireLocalMandate: whether a declaration needs a local mandate (ADR 0009: the mandate guard, unless a command opts out);
 * - allowFosterCarerDeclarations: whether declarations of foster carers (typeEmploi = AF) are accepted locally (ADR 0009);
 * - defaultPageSize / maxPageSize: number of records loaded by a batch use case when no / a larger limit is asked;
 * - allowErasureWithUnknownOutcomes / allowErasureWithActiveMandate: default of the two opt-in refusals of ForgetEmployer
 *   (a command states its own choice when it is not null; a submission in flight is always refused, ADR 0015).
 */
final readonly class WorkflowPolicy
{
    public const DEFAULT_PAGE_SIZE = 100;
    public const MAX_PAGE_SIZE = 1000;
    public const DEFAULT_MAX_SUBMISSION_ATTEMPTS = DeclarationRecord::DEFAULT_MAX_ATTEMPTS;
    public const DEFAULT_STALE_SUBMISSION_AFTER = 'PT15M';

    /** @var positive-int */
    private int $maxSubmissionAttempts;

    /** @var positive-int */
    private int $defaultPageSize;

    /** @var positive-int */
    private int $maxPageSize;

    /**
     * @throws InvariantViolation "workflow_policy.invalid_max_submission_attempts", "workflow_policy.invalid_page_size"
     *                            or "workflow_policy.default_page_size_above_max"
     */
    public function __construct(
        private DateTimeZone $businessTimeZone = new DateTimeZone('Europe/Paris'),
        private DateInterval $staleSubmissionAfter = new DateInterval(self::DEFAULT_STALE_SUBMISSION_AFTER),
        int $maxSubmissionAttempts = self::DEFAULT_MAX_SUBMISSION_ATTEMPTS,
        private bool $requireLocalMandate = true,
        private bool $allowFosterCarerDeclarations = false,
        int $defaultPageSize = self::DEFAULT_PAGE_SIZE,
        int $maxPageSize = self::MAX_PAGE_SIZE,
        private bool $allowErasureWithUnknownOutcomes = false,
        private bool $allowErasureWithActiveMandate = false,
    ) {
        if ($maxPageSize < $defaultPageSize) {
            throw InvariantViolation::because(
                'workflow_policy.default_page_size_above_max',
                'The default page size must not be greater than the maximum page size.',
                ['default_page_size' => $defaultPageSize, 'max_page_size' => $maxPageSize],
            );
        }
        $this->defaultPageSize = self::positivePageSize($defaultPageSize);
        $this->maxPageSize = self::positivePageSize($maxPageSize);
        if ($maxSubmissionAttempts < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_max_submission_attempts',
                'The maximum number of submission attempts must be a positive integer.',
                ['max_submission_attempts' => $maxSubmissionAttempts],
            );
        }
        $this->maxSubmissionAttempts = $maxSubmissionAttempts;
    }

    public function businessTimeZone(): DateTimeZone
    {
        return $this->businessTimeZone;
    }

    public function staleSubmissionAfter(): DateInterval
    {
        return $this->staleSubmissionAfter;
    }

    /**
     * Submission attempts after which a technical failure is not requeued automatically any more (the declaration
     * stays in error until handled manually).
     *
     * @return positive-int
     */
    public function maxSubmissionAttempts(): int
    {
        return $this->maxSubmissionAttempts;
    }

    /**
     * ADR 0009: the mandate guard is active unless a command opts out.
     */
    public function requireLocalMandate(): bool
    {
        return $this->requireLocalMandate;
    }

    /**
     * ADR 0009: whether typeEmploi = AF (foster carers) is accepted locally.
     */
    public function allowFosterCarerDeclarations(): bool
    {
        return $this->allowFosterCarerDeclarations;
    }

    /**
     * ADR 0015: whether ForgetEmployer erases declarations whose outcome is unknown when the command does not decide.
     */
    public function allowErasureWithUnknownOutcomes(): bool
    {
        return $this->allowErasureWithUnknownOutcomes;
    }

    /**
     * ADR 0015: whether ForgetEmployer erases an employer with an active local mandate when the command does not decide.
     */
    public function allowErasureWithActiveMandate(): bool
    {
        return $this->allowErasureWithActiveMandate;
    }

    /**
     * The calendar date of the instant in the business time zone (ADR 0001, api-reference §4.1).
     */
    public function today(DateTimeImmutable $now): CalendarDate
    {
        return CalendarDate::fromDateTime($now, $this->businessTimeZone);
    }

    /**
     * Number of records to load: the requested limit (default page size when null), capped by the maximum page size.
     *
     * @return positive-int
     *
     * @throws InvariantViolation "workflow_policy.invalid_limit" for a limit lower than 1
     */
    public function pageSize(?int $requestedLimit): int
    {
        $limit = $requestedLimit ?? $this->defaultPageSize;
        if ($limit < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_limit',
                'The limit must be a positive integer.',
                ['limit' => $limit],
            );
        }

        return min($limit, $this->maxPageSize);
    }

    /**
     * @return positive-int
     */
    private static function positivePageSize(int $pageSize): int
    {
        if ($pageSize < 1) {
            throw InvariantViolation::because(
                'workflow_policy.invalid_page_size',
                'Page sizes must be positive integers.',
                ['page_size' => $pageSize],
            );
        }

        return $pageSize;
    }
}
