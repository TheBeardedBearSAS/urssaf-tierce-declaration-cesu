<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;

final readonly class GetDeclarationHandler
{
    public function __construct(private DeclarationLocator $locator) {}

    /**
     * @throws RecordNotFound "declaration_record.not_found"
     */
    public function __invoke(GetDeclaration $query): DeclarationView
    {
        return DeclarationView::fromRecord($this->locator->locate($query->tenantId, $query->declaration));
    }
}
