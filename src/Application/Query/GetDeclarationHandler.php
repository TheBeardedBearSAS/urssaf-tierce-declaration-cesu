<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

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
