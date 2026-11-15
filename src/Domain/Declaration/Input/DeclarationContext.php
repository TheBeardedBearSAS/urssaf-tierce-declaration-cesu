<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;

/**
 * What the factory needs to know besides the draft: today's date, computed by the application in the business time
 * zone (ADR 0001), and whether foster carer declarations are allowed (`WorkflowPolicy::allowFosterCarerDeclarations()`,
 * ADR 0009).
 */
final readonly class DeclarationContext
{
    private function __construct(private CalendarDate $today, private bool $allowFosterCarerDeclarations) {}

    public static function of(CalendarDate $today, bool $allowFosterCarerDeclarations): self
    {
        return new self($today, $allowFosterCarerDeclarations);
    }

    public function today(): CalendarDate
    {
        return $this->today;
    }

    public function allowFosterCarerDeclarations(): bool
    {
        return $this->allowFosterCarerDeclarations;
    }
}
