<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;

/**
 * Read model of the answer of method 021 (api-reference §2.3).
 */
final readonly class EmployeeVerificationView
{
    private function __construct(public bool $registered, public ?string $cesuNumber) {}

    public static function fromResult(EmployeeVerificationResult $result): self
    {
        return new self($result->registered(), $result->cesuNumber()?->value());
    }
}
