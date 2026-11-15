<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use DateInterval;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;

/**
 * Workflow policies of the declaration tests: one setting changed, the others left at their defaults (positional
 * arguments only).
 */
final class DeclarationPolicies
{
    /**
     * @param positive-int $maxAttempts
     */
    public static function maxAttempts(int $maxAttempts): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateTimeZone('Europe/Paris'),
            new DateInterval(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER),
            $maxAttempts,
        );
    }

    public static function staleAfter(string $interval): WorkflowPolicy
    {
        return new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval($interval));
    }

    public static function inTimeZone(string $timeZone): WorkflowPolicy
    {
        return new WorkflowPolicy(new DateTimeZone($timeZone));
    }

    public static function withoutMandateGuard(): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateTimeZone('Europe/Paris'),
            new DateInterval(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER),
            WorkflowPolicy::DEFAULT_MAX_SUBMISSION_ATTEMPTS,
            false,
        );
    }

    public static function allowingFosterCarers(): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateTimeZone('Europe/Paris'),
            new DateInterval(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER),
            WorkflowPolicy::DEFAULT_MAX_SUBMISSION_ATTEMPTS,
            true,
            true,
        );
    }

    /**
     * @param positive-int $default
     * @param positive-int $max
     */
    public static function pageSizes(int $default, int $max): WorkflowPolicy
    {
        return new WorkflowPolicy(
            new DateTimeZone('Europe/Paris'),
            new DateInterval(WorkflowPolicy::DEFAULT_STALE_SUBMISSION_AFTER),
            WorkflowPolicy::DEFAULT_MAX_SUBMISSION_ATTEMPTS,
            true,
            false,
            $default,
            $max,
        );
    }
}
