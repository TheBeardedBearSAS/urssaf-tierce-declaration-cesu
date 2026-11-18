<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Builds the {@see EmployeeByNir} of an {@see EmployeeReferenceFactory}: a lookup sends the NIR alone, a declaration
 * may add the administrative data, parsed in partial mode (api-reference §4.7).
 *
 * @internal
 */
final readonly class NirReferenceParser
{
    public function __construct(private bool $identityAllowed) {}

    /**
     * Returns the reference, or null after reporting the violations to the collector.
     */
    public function parse(?Nir $nir, ?EmployeeIdentityDraft $identityDraft, ViolationCollector $collector): ?EmployeeReference
    {
        if (!$identityDraft instanceof EmployeeIdentityDraft) {
            return $nir instanceof Nir ? EmployeeByNir::of($nir, null) : null;
        }
        if (!$this->identityAllowed) {
            $collector->add(Violation::error(
                'identity',
                'employee_reference.identity_forbidden_with_nir',
                'The administrative data must not be given with a NIR.',
            ));

            return null;
        }
        $identity = EmployeeIdentityParser::partial()->parse($identityDraft, $collector);

        return $nir instanceof Nir && $identity instanceof EmployeeIdentity ? EmployeeByNir::of($nir, $identity) : null;
    }
}
