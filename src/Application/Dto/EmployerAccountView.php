<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;

/**
 * Read model of an employer account (primitives only, no birth date). Instants are ISO 8601 UTC strings.
 */
final readonly class EmployerAccountView
{
    private function __construct(
        public string $accountId,
        public string $tenantId,
        public string $externalReference,
        public string $cesuNumber,
        public string $origin,
        public string $stage,
        public ?EmployerStatusView $lastStatus,
        public ?MandateView $mandate,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromAccount(EmployerAccount $account, ?Mandate $mandate): self
    {
        return new self(
            $account->id()->value(),
            $account->tenantId()->value(),
            $account->externalReference()->value(),
            $account->cesuNumber()->value(),
            $account->origin()->value,
            $account->stage()->value,
            EmployerStatusView::fromAccount($account),
            $mandate instanceof Mandate ? MandateView::fromMandate($mandate) : null,
            $account->createdAt()->toIso8601(),
            $account->updatedAt()->toIso8601(),
        );
    }
}
