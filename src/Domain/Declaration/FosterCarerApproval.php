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

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;

/**
 * Foster carer approval (`AgrementAF`, api-reference §2.7, PDF §3.7.5 4/). With a certified approval the council and
 * the start and end dates are required; with an occasional replacement they are all optional. No start-before-end
 * rule is published. The approval lives here, not on the employee, so that the employer scope does not depend on the
 * declaration scope; the wire mapper writes it inside `infosSalarie`.
 */
final readonly class FosterCarerApproval
{
    public const COUNCIL_REQUIRED = 'foster_carer_approval.council_required';
    public const START_DATE_REQUIRED = 'foster_carer_approval.start_date_required';
    public const END_DATE_REQUIRED = 'foster_carer_approval.end_date_required';
    public const COUNCIL_REQUIRED_MESSAGE = 'The departmental council is required with a certified approval.';
    public const START_DATE_REQUIRED_MESSAGE = 'The approval start date is required with a certified approval.';
    public const END_DATE_REQUIRED_MESSAGE = 'The approval end date is required with a certified approval.';

    private function __construct(
        private ApprovalBasis $basis,
        private ?DepartmentalCouncil $council,
        private ?ApprovalNumber $number,
        private ?CalendarDate $startDate,
        private ?CalendarDate $endDate,
    ) {}

    /**
     * @throws InvalidValue "foster_carer_approval.council_required", "foster_carer_approval.start_date_required" or
     *                      "foster_carer_approval.end_date_required"
     */
    public static function of(
        ApprovalBasis $basis,
        ?DepartmentalCouncil $council,
        ?ApprovalNumber $number,
        ?CalendarDate $startDate,
        ?CalendarDate $endDate,
    ): self {
        if ($basis === ApprovalBasis::CertifiedApproval) {
            if (!$council instanceof DepartmentalCouncil) {
                throw InvalidValue::because(self::COUNCIL_REQUIRED, self::COUNCIL_REQUIRED_MESSAGE);
            }
            if (!$startDate instanceof CalendarDate) {
                throw InvalidValue::because(self::START_DATE_REQUIRED, self::START_DATE_REQUIRED_MESSAGE);
            }
            if (!$endDate instanceof CalendarDate) {
                throw InvalidValue::because(self::END_DATE_REQUIRED, self::END_DATE_REQUIRED_MESSAGE);
            }
        }

        return new self($basis, $council, $number, $startDate, $endDate);
    }

    public function basis(): ApprovalBasis
    {
        return $this->basis;
    }

    public function council(): ?DepartmentalCouncil
    {
        return $this->council;
    }

    public function number(): ?ApprovalNumber
    {
        return $this->number;
    }

    public function startDate(): ?CalendarDate
    {
        return $this->startDate;
    }

    public function endDate(): ?CalendarDate
    {
        return $this->endDate;
    }

    public function isCertifiedValid(): bool
    {
        return $this->basis === ApprovalBasis::CertifiedApproval;
    }

    public function isOccasionalReplacement(): bool
    {
        return $this->basis === ApprovalBasis::OccasionalReplacement;
    }
}
