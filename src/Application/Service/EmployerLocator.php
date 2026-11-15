<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Loads the employer account designated by an {@see EmployerSelector}, within one tenant.
 */
final readonly class EmployerLocator
{
    private const RECORD_TYPE = 'employer_account';

    public function __construct(private EmployerAccountRepository $accounts) {}

    /**
     * @throws RecordNotFound "employer_account.not_found" (also for an account of another tenant)
     */
    public function locate(TenantId $tenantId, EmployerSelector $selector): EmployerAccount
    {
        $reference = $selector->reference;
        if ($reference instanceof ExternalReference) {
            return $this->accounts->findByExternalReference($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }
        if ($reference instanceof CesuNumber) {
            return $this->accounts->findByCesuNumber($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }
        $account = $this->accounts->get($reference);
        if (!$account->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }

        return $account;
    }
}
