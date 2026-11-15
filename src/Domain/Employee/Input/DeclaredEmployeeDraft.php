<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;

/**
 * Raw employee of a declaration "InfosSalarie" (api-reference §2.7). An empty string counts as missing. Personal data
 * (ADR 0007).
 */
final readonly class DeclaredEmployeeDraft
{
    public function __construct(
        public ?string $cesuNumber,
        public ?string $nir,
        public ?EmployeeIdentityDraft $identity,
        public ?string $civility,
        public ?AddressDraft $address,
        public ?string $email,
        public ?string $phone,
        public ?string $employmentKind,
        public ?bool $paidLeaveIncluded,
    ) {}
}
