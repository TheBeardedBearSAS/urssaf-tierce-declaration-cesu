<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * The non-personal part of a declaration kept by the {@see DeclarationRecord} (ADR 0007): no NIR, name, birth data,
 * address nor amount. The fingerprint identifies the content for idempotency.
 *
 * The public constructor is the factory (more than 5 parts).
 */
final readonly class DeclarationSnapshot
{
    public function __construct(
        private CesuNumber $employerCesuNumber,
        private DeclarationType $type,
        private EmploymentPeriod $employmentPeriod,
        private CalendarDate $paymentDate,
        private EmploymentKind $employmentKind,
        private EmployeeReferenceKind $employeeReferenceKind,
        private ?CesuNumber $employeeCesuNumber,
        private DeclarationFingerprint $fingerprint,
    ) {}

    /**
     * Keeps the non-personal part of the declaration; the fingerprint is the one computed by
     * {@see Declaration::fingerprint()}.
     */
    public static function fromDeclaration(Declaration $declaration): self
    {
        $content = $declaration->content();
        $reference = $content->employee()->reference();

        return new self(
            $content->employerCesuNumber(),
            $declaration->type(),
            $content->employmentPeriod(),
            $content->paymentDate(),
            $content->employee()->employmentKind(),
            $reference->kind(),
            $reference->cesuNumber(),
            DeclarationFingerprint::fromString($declaration->fingerprint()),
        );
    }

    public function employerCesuNumber(): CesuNumber
    {
        return $this->employerCesuNumber;
    }

    public function type(): DeclarationType
    {
        return $this->type;
    }

    public function employmentPeriod(): EmploymentPeriod
    {
        return $this->employmentPeriod;
    }

    public function paymentDate(): CalendarDate
    {
        return $this->paymentDate;
    }

    public function employmentKind(): EmploymentKind
    {
        return $this->employmentKind;
    }

    public function employeeReferenceKind(): EmployeeReferenceKind
    {
        return $this->employeeReferenceKind;
    }

    /**
     * Null unless the employee was identified by a Cesu number.
     */
    public function employeeCesuNumber(): ?CesuNumber
    {
        return $this->employeeCesuNumber;
    }

    public function fingerprint(): DeclarationFingerprint
    {
        return $this->fingerprint;
    }

    public function hasSameContentAs(self $other): bool
    {
        return $this->fingerprint->equals($other->fingerprint);
    }
}
