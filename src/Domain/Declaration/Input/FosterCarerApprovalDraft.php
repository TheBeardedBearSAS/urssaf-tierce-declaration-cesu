<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

/**
 * Raw foster carer approval (`AgrementAF`). Personal data through the approval number (ADR 0007).
 */
final readonly class FosterCarerApprovalDraft
{
    public function __construct(
        public ?string $councilCode,
        public ?string $approvalNumber,
        public ?string $startDate,
        public ?string $endDate,
        public ?bool $certifiedValid,
        public ?bool $occasionalReplacement,
    ) {}
}
