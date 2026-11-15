<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;

/**
 * The mandate shown is the active one, else the most recent one (a cancelled mandate), else none.
 */
final readonly class GetEmployerAccountHandler
{
    public function __construct(private EmployerLocator $locator, private MandateRepository $mandates) {}

    /**
     * @throws RecordNotFound
     */
    public function __invoke(GetEmployerAccount $query): EmployerAccountView
    {
        $account = $this->locator->locate($query->tenantId, $query->employer);
        $mandate = $this->mandates->findActive($account->tenantId(), $account->cesuNumber())
            ?? $this->mostRecent($this->mandates->findByEmployer($account->tenantId(), $account->cesuNumber()));

        return EmployerAccountView::fromAccount($account, $mandate);
    }

    /**
     * @param list<Mandate> $mandates oldest notification first
     */
    private function mostRecent(array $mandates): ?Mandate
    {
        return $mandates === [] ? null : $mandates[\count($mandates) - 1];
    }
}
