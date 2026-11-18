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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Turns an {@see EmployeeReferenceDraft} into an {@see EmployeeReference}, reporting every violation (notification
 * pattern, ADR 0000). Paths follow the draft ("cesuNumber", "nir", "identity.birthName").
 *
 * The modes are exclusive (api-reference §4.7): a Cesu number or a NIR or the administrative data alone. A lookup
 * ({@see self::forLookup()}, methods 020 and 021) sends the NIR alone; a declaration ({@see self::forDeclaration()},
 * method 050) may add the administrative data to the NIR, whose requirement is checked by
 * {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployeeRule}. Without a Cesu number and a NIR the data are parsed in full mode (birth
 * date and birth city required); with a NIR they are parsed in partial mode.
 *
 * @implements Validator<EmployeeReferenceDraft>
 */
final readonly class EmployeeReferenceFactory implements Validator
{
    private function __construct(private bool $identityAllowedWithNir) {}

    /**
     * Factory for the lookups (methods 020, 021).
     */
    public static function forLookup(): self
    {
        return new self(false);
    }

    /**
     * Factory for the declaration (method 050).
     */
    public static function forDeclaration(): self
    {
        return new self(true);
    }

    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * @throws ValidationFailed with every violation
     */
    public function fromDraft(EmployeeReferenceDraft $draft): EmployeeReference
    {
        $collector = new ViolationCollector();

        return $this->parse($draft, $collector) ?? throw ValidationFailed::withResult($collector->result());
    }

    /**
     * Returns the reference, or null after reporting the violations to the collector.
     */
    public function parse(EmployeeReferenceDraft $draft, ViolationCollector $collector): ?EmployeeReference
    {
        $cesuFilled = self::filled($draft->cesuNumber);
        $nirFilled = self::filled($draft->nir);
        $identityDraft = $draft->filledIdentity();
        $cesuNumber = $collector->optional('cesuNumber', $draft->cesuNumber, CesuNumber::fromString(...));
        $nir = $collector->optional('nir', $draft->nir, Nir::fromString(...));
        if ($cesuFilled && $nirFilled) {
            $collector->add(Violation::error(
                'nir',
                'employee_reference.cesu_number_and_nir_exclusive',
                'An employee is identified by a Cesu number or a NIR, not both.',
            ));

            return null;
        }
        if ($cesuFilled) {
            return $this->byCesuNumber($cesuNumber, $identityDraft, $collector);
        }
        if ($nirFilled) {
            return (new NirReferenceParser($this->identityAllowedWithNir))->parse($nir, $identityDraft, $collector);
        }
        $identity = EmployeeIdentityParser::full()->parse(
            $draft->identity ?? new EmployeeIdentityDraft(null, null, null, null, null, null, null),
            $collector,
        );

        return $identity instanceof EmployeeIdentity ? EmployeeByIdentity::of($identity) : null;
    }

    private function byCesuNumber(?CesuNumber $cesuNumber, ?EmployeeIdentityDraft $identityDraft, ViolationCollector $collector): ?EmployeeReference
    {
        if ($identityDraft instanceof EmployeeIdentityDraft) {
            $collector->add(Violation::error(
                'identity',
                'employee_reference.identity_forbidden_with_cesu_number',
                'The administrative data must not be given with an employee Cesu number.',
            ));
        }

        return !$cesuNumber instanceof CesuNumber || $identityDraft instanceof EmployeeIdentityDraft ? null : EmployeeByCesuNumber::of($cesuNumber);
    }

    private static function filled(?string $raw): bool
    {
        return $raw !== null && $raw !== '';
    }
}
