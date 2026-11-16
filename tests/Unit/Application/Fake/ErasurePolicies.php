<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use DateInterval;
use DateTimeZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;

/**
 * Workflow policies with the two erasure opt-ins chosen (positional arguments: the rules forbid named ones).
 */
final class ErasurePolicies
{
    private function __construct() {}

    public static function of(bool $allowUnknownOutcomes, bool $allowActiveMandate): WorkflowPolicy
    {
        return new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('PT15M'), 5, true, false, 100, 1000, $allowUnknownOutcomes, $allowActiveMandate);
    }

    /**
     * Without the local mandate guard (a declaration needs no mandate), erasure opt-ins at their defaults.
     */
    public static function withoutMandateGuard(): WorkflowPolicy
    {
        return new WorkflowPolicy(new DateTimeZone('Europe/Paris'), new DateInterval('PT15M'), 5, false, false, 100, 1000, false, false);
    }
}
