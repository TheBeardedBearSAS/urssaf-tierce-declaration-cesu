<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ContributionsView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployeeVerificationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerAccountView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\EmployerStatusView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\MandateView;

/**
 * The rows of the views printed by the commands. Identifiers, Cesu numbers, states, codes, amounts and instants only:
 * no name, birth date or NIR (ADR 0007); the descriptions of URSSAF errors are not printed (lot 6 design open point 7).
 *
 * Source: lot 6 design §5.4.
 */
final class CommandViews
{
    /**
     * @return list<array{string, string}>
     */
    public static function employerStatus(EmployerStatusView $status): array
    {
        return [
            ['Registered', CommandReports::yesNo($status->registered)],
            ['Online account', CommandReports::orDash($status->onlineAccountState)],
            ['SEPA mandate', CommandReports::orDash($status->sepaMandateState)],
            ['Mandate present', CommandReports::yesNo($status->mandatePresent)],
            ['Cesu+ state', CommandReports::orDash($status->cesuPlusState)],
            ['Cesu+ blocking reason', CommandReports::orDash($status->cesuPlusBlockingReason)],
            ['AICI active', CommandReports::yesNo($status->aiciActive)],
            ['Employee reference', CommandReports::orDash($status->employeeReferenceKind)],
            ['Checked at', CommandReports::orDash($status->checkedAt)],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function employeeVerification(EmployeeVerificationView $view): array
    {
        return [
            ['Registered', CommandReports::yesNo($view->registered)],
            ['Cesu number', CommandReports::orDash($view->cesuNumber)],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function contributions(ContributionsView $view): array
    {
        return [
            ['Employer contributions (€)', CommandReports::euros($view->employerContributionsCents)],
            ['Employee contributions (€)', CommandReports::euros($view->employeeContributionsCents)],
        ];
    }

    /**
     * The account as tracked (employer:import).
     *
     * @return list<array{string, string}>
     */
    public static function trackedAccount(EmployerAccountView $account): array
    {
        return [
            ['Account id', $account->accountId],
            ['External reference', $account->externalReference],
            ['Cesu number', $account->cesuNumber],
            ['Origin', $account->origin],
            ['Stage', $account->stage],
            ['Created at', $account->createdAt],
        ];
    }

    /**
     * The account after a status refresh (employers:sync-status).
     *
     * @return list<array{string, string}>
     */
    public static function refreshedAccount(EmployerAccountView $account): array
    {
        return [
            ['Account id', $account->accountId],
            ['External reference', $account->externalReference],
            ['Cesu number', $account->cesuNumber],
            ['Stage', $account->stage],
            ['Mandate present', CommandReports::yesNo($account->lastStatus?->mandatePresent)],
            ['Mandate state', CommandReports::orDash($account->mandate?->state)],
            ['Last checked at', CommandReports::orDash($account->lastStatus?->checkedAt)],
        ];
    }

    /**
     * @return list<array{string, string}>
     */
    public static function mandate(MandateView $mandate): array
    {
        return [
            ['Mandate id', $mandate->mandateId],
            ['External reference', $mandate->externalReference],
            ['Cesu number', $mandate->cesuNumber],
            ['State', $mandate->state],
            ['Notified at', $mandate->notifiedAt],
            ['Confirmed at', CommandReports::orDash($mandate->confirmedAt)],
            ['Cancelled at', CommandReports::orDash($mandate->cancelledAt)],
        ];
    }

    /**
     * The state of a declaration record: error codes only, never the descriptions of URSSAF.
     *
     * @return list<array{string, string}>
     */
    public static function declaration(DeclarationView $declaration): array
    {
        $status = $declaration->status;

        return [
            ['Record id', $declaration->recordId],
            ['External reference', $declaration->externalReference],
            ['State', $status->state],
            ['Declaration id', CommandReports::orDash($status->declarationId)],
            ['Attempts', (string) $status->attempts],
            ['Error codes', CommandReports::orDash($status->errors === [] ? null : implode(', ', array_column($status->errors, 'code')))],
            ['Updated at', $status->updatedAt],
        ];
    }
}
