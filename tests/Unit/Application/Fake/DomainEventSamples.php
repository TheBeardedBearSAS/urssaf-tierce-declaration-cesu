<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationAlreadyDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationCredentialsRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationDeclared;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationOutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRecorded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationRequeued;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Event\DeclarationSubmissionFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\OutcomeUnknownReason;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerRegistered;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStageChanged;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Event\EmployerStatusRefreshed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\RegistrationOrigin;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateCancelled;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateConfirmed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Event\MandateNotified;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Domain events built directly (fixed fictitious values), to test what is derived from them: one factory per event.
 *
 * @mago-expect lint:too-many-methods
 */
final class DomainEventSamples
{
    public const AT = '2026-11-15T10:00:00Z';
    public const CESU_NUMBER = 'Z0003752390009';

    private function __construct() {}

    public static function employerRegistered(): EmployerRegistered
    {
        return new EmployerRegistered(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            self::cesu(),
            RegistrationOrigin::Registered,
            self::at(),
        );
    }

    public static function employerStatusRefreshed(): EmployerStatusRefreshed
    {
        return new EmployerStatusRefreshed(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            self::cesu(),
            EmployerStage::MandateNotified,
            EmployerStage::MandateConfirmed,
            new EmployerStatus(true, 'actif', 'actif', true, null, null, false),
            self::at(),
        );
    }

    public static function employerStageChanged(): EmployerStageChanged
    {
        return new EmployerStageChanged(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            self::cesu(),
            EmployerStage::Registered,
            EmployerStage::MandateNotified,
            self::at(),
        );
    }

    public static function employerForgotten(): EmployerForgotten
    {
        return new EmployerForgotten(
            self::accountId(),
            self::tenant(),
            self::reference('employer-1'),
            self::cesu(),
            [self::mandateId()],
            [self::recordId()],
            self::at(),
        );
    }

    public static function mandateNotified(): MandateNotified
    {
        return new MandateNotified(self::mandateId(), self::tenant(), self::reference('employer-1'), self::cesu(), self::at());
    }

    public static function mandateConfirmed(): MandateConfirmed
    {
        return new MandateConfirmed(self::mandateId(), self::tenant(), self::reference('employer-1'), self::cesu(), self::at());
    }

    public static function mandateCancelled(): MandateCancelled
    {
        return new MandateCancelled(self::mandateId(), self::tenant(), self::reference('employer-1'), self::cesu(), self::at());
    }

    public static function declarationRecorded(): DeclarationRecorded
    {
        return new DeclarationRecorded(self::recordId(), self::tenant(), self::reference('declaration-1'), self::cesu(), self::at());
    }

    public static function declarationDeclared(): DeclarationDeclared
    {
        return new DeclarationDeclared(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            DeclarationId::fromString('209299542888'),
            self::at(),
        );
    }

    public static function declarationRejected(): DeclarationRejected
    {
        return new DeclarationRejected(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            [
                SubmissionError::of('ERR_METIER_1', 'First message.', 'First description.'),
                SubmissionError::of('ERR_METIER_2', 'Second message.', null),
            ],
            self::at(),
        );
    }

    public static function declarationAlreadyDeclared(): DeclarationAlreadyDeclared
    {
        return new DeclarationAlreadyDeclared(self::recordId(), self::tenant(), self::reference('declaration-1'), self::at());
    }

    public static function declarationOutcomeUnknown(): DeclarationOutcomeUnknown
    {
        return new DeclarationOutcomeUnknown(
            self::recordId(),
            self::tenant(),
            self::reference('declaration-1'),
            OutcomeUnknownReason::MayHaveReachedUrssaf,
            self::at(),
        );
    }

    public static function declarationSubmissionFailed(): DeclarationSubmissionFailed
    {
        return new DeclarationSubmissionFailed(
            self::recordId(),
            self::tenant(),
            [SubmissionError::of('urssaf.unavailable', 'The service is unavailable.', null)],
            self::at(),
        );
    }

    public static function declarationCredentialsRejected(): DeclarationCredentialsRejected
    {
        return new DeclarationCredentialsRejected(self::recordId(), self::tenant(), self::at());
    }

    public static function declarationRequeued(): DeclarationRequeued
    {
        return new DeclarationRequeued(self::recordId(), self::tenant(), DeclarationState::Error, self::at());
    }

    private static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    private static function reference(string $value): ExternalReference
    {
        return ExternalReference::fromString($value);
    }

    private static function cesu(): CesuNumber
    {
        return CesuNumber::fromString(self::CESU_NUMBER);
    }

    private static function accountId(): EmployerAccountId
    {
        return EmployerAccountId::fromString('account-1');
    }

    private static function mandateId(): MandateId
    {
        return MandateId::fromString('mandate-1');
    }

    private static function recordId(): DeclarationRecordId
    {
        return DeclarationRecordId::fromString('record-1');
    }

    private static function at(): Instant
    {
        return Instant::fromIso8601(self::AT);
    }
}
