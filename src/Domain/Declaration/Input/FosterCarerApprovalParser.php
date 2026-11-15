<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DepartmentalCouncil;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Parses a {@see FosterCarerApprovalDraft} into a {@see FosterCarerApproval}, reporting every violation to the collector.
 * Exactly one of the two indicators must be true (api-reference §2.7); with a certified approval the council and the
 * dates are required.
 *
 * @internal
 */
final readonly class FosterCarerApprovalParser
{
    /**
     * Returns the approval, or null when there is no draft or after reporting the violations to the collector.
     */
    public function parse(?FosterCarerApprovalDraft $draft, ViolationCollector $collector): ?FosterCarerApproval
    {
        if (!$draft instanceof FosterCarerApprovalDraft) {
            return null;
        }
        $before = $collector->errorCount();
        $council = $collector->optional('councilCode', $draft->councilCode, DepartmentalCouncil::fromString(...));
        $number = $collector->optional('approvalNumber', $draft->approvalNumber, ApprovalNumber::fromString(...));
        $startDate = $collector->optional('startDate', $draft->startDate, CalendarDate::fromString(...));
        $endDate = $collector->optional('endDate', $draft->endDate, CalendarDate::fromString(...));
        $basis = $this->basis($draft, $collector);
        if ($basis === ApprovalBasis::CertifiedApproval) {
            $this->requireWhenCertified($draft, $collector);
        }
        if ($collector->errorCount() > $before) {
            return null;
        }

        return FosterCarerApproval::of($basis, $council, $number, $startDate, $endDate);
    }

    /**
     * Reports the indicators that do not define exactly one basis; both true is read as the certified approval (the
     * contract: "if true, indRemplacementPonctuel must be false"), none true as the replacement.
     */
    private function basis(FosterCarerApprovalDraft $draft, ViolationCollector $collector): ApprovalBasis
    {
        $certified = $draft->certifiedValid === true;
        $replacement = $draft->occasionalReplacement === true;
        if ($certified && $replacement) {
            $collector->add(Violation::error(
                '',
                'foster_carer_approval.indicators_exclusive',
                'The certified approval and the occasional replacement indicators must not both be true.',
            ));
        }
        if (!$certified && !$replacement) {
            $collector->add(Violation::error(
                '',
                'foster_carer_approval.indicator_required',
                'One of the certified approval and occasional replacement indicators must be true.',
            ));
        }

        return $certified ? ApprovalBasis::CertifiedApproval : ApprovalBasis::OccasionalReplacement;
    }

    private function requireWhenCertified(FosterCarerApprovalDraft $draft, ViolationCollector $collector): void
    {
        if (self::missing($draft->councilCode)) {
            $collector->add(Violation::error(
                'councilCode',
                FosterCarerApproval::COUNCIL_REQUIRED,
                FosterCarerApproval::COUNCIL_REQUIRED_MESSAGE,
            ));
        }
        if (self::missing($draft->startDate)) {
            $collector->add(Violation::error(
                'startDate',
                FosterCarerApproval::START_DATE_REQUIRED,
                FosterCarerApproval::START_DATE_REQUIRED_MESSAGE,
            ));
        }
        if (self::missing($draft->endDate)) {
            $collector->add(Violation::error(
                'endDate',
                FosterCarerApproval::END_DATE_REQUIRED,
                FosterCarerApproval::END_DATE_REQUIRED_MESSAGE,
            ));
        }
    }

    private static function missing(?string $raw): bool
    {
        return $raw === null || $raw === '';
    }
}
