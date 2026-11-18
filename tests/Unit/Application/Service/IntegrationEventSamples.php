<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

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
 * One fictitious domain event of each kind, with fixed values (shared by the translator and unit of work tests).
 *
 * @mago-expect lint:too-many-methods
 */
final class IntegrationEventSamples
{
    public const AT = '2026-11-15T10:00:00Z';
    public const CESU = 'Z0003752390009';

    public static function employerRegistered(RegistrationOrigin $origin = RegistrationOrigin::Registered, string $account = 'account-1'): EmployerRegistered
    {
        return new EmployerRegistered(
            EmployerAccountId::fromString($account),
            self::tenant(),
            ExternalReference::fromString('employer-1'),
            CesuNumber::fromString(self::CESU),
            $origin,
            self::at(),
        );
    }

    /**
     * @param list<string> $mandateIds
     * @param list<string> $recordIds
     */
    public static function employerForgotten(string $cesu = self::CESU, array $mandateIds = ['mandate-1', 'mandate-2'], array $recordIds = ['record-1', 'record-2', 'record-3']): EmployerForgotten
    {
        return new EmployerForgotten(
            EmployerAccountId::fromString('account-1'),
            self::tenant(),
            ExternalReference::fromString('employer-1'),
            CesuNumber::fromString($cesu),
            array_map(MandateId::fromString(...), $mandateIds),
            array_map(DeclarationRecordId::fromString(...), $recordIds),
            self::at(),
        );
    }

    public static function employerStatusRefreshed(
        EmployerStage $previous = EmployerStage::MandateNotified,
        EmployerStage $stage = EmployerStage::MandateConfirmed,
        EmployerStatus $status = new EmployerStatus(true, 'actif', 'actif', true, 'actif', null, false),
    ): EmployerStatusRefreshed {
        return new EmployerStatusRefreshed(
            EmployerAccountId::fromString('account-1'),
            self::tenant(),
            ExternalReference::fromString('employer-1'),
            CesuNumber::fromString(self::CESU),
            $previous,
            $stage,
            $status,
            self::at(),
        );
    }

    public static function employerStageChanged(EmployerStage $from = EmployerStage::Registered, EmployerStage $to = EmployerStage::MandateNotified): EmployerStageChanged
    {
        return new EmployerStageChanged(
            EmployerAccountId::fromString('account-1'),
            self::tenant(),
            ExternalReference::fromString('employer-1'),
            CesuNumber::fromString(self::CESU),
            $from,
            $to,
            self::at(),
        );
    }

    public static function mandateNotified(string $mandate = 'mandate-1'): MandateNotified
    {
        return new MandateNotified(MandateId::fromString($mandate), self::tenant(), ExternalReference::fromString('employer-1'), CesuNumber::fromString(self::CESU), self::at());
    }

    public static function mandateConfirmed(): MandateConfirmed
    {
        return new MandateConfirmed(MandateId::fromString('mandate-1'), self::tenant(), ExternalReference::fromString('employer-1'), CesuNumber::fromString(self::CESU), self::at());
    }

    public static function mandateCancelled(): MandateCancelled
    {
        return new MandateCancelled(MandateId::fromString('mandate-1'), self::tenant(), ExternalReference::fromString('employer-1'), CesuNumber::fromString(self::CESU), self::at());
    }

    public static function declarationRecorded(): DeclarationRecorded
    {
        return new DeclarationRecorded(self::record(), self::tenant(), self::reference(), CesuNumber::fromString(self::CESU), self::at());
    }

    public static function declarationDeclared(string $declarationId = 'declaration-1'): DeclarationDeclared
    {
        return new DeclarationDeclared(self::record(), self::tenant(), self::reference(), DeclarationId::fromString($declarationId), self::at());
    }

    public static function declarationRejected(string ...$codes): DeclarationRejected
    {
        return new DeclarationRejected(
            self::record(),
            self::tenant(),
            self::reference(),
            array_map(static fn(string $code): SubmissionError => SubmissionError::of($code, 'Refused.', 'Free text that must never be published.'), array_values($codes)),
            self::at(),
        );
    }

    public static function declarationOutcomeUnknown(OutcomeUnknownReason $reason = OutcomeUnknownReason::MayHaveReachedUrssaf): DeclarationOutcomeUnknown
    {
        return new DeclarationOutcomeUnknown(self::record(), self::tenant(), self::reference(), $reason, self::at());
    }

    public static function declarationAlreadyDeclared(): DeclarationAlreadyDeclared
    {
        return new DeclarationAlreadyDeclared(self::record(), self::tenant(), self::reference(), self::at());
    }

    public static function declarationSubmissionFailed(): DeclarationSubmissionFailed
    {
        return new DeclarationSubmissionFailed(self::record(), self::tenant(), [SubmissionError::of('urssaf.unavailable', 'Unavailable.', null)], self::at());
    }

    public static function declarationCredentialsRejected(): DeclarationCredentialsRejected
    {
        return new DeclarationCredentialsRejected(self::record(), self::tenant(), self::at());
    }

    public static function declarationRequeued(): DeclarationRequeued
    {
        return new DeclarationRequeued(self::record(), self::tenant(), DeclarationState::Error, self::at());
    }

    private static function tenant(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    private static function reference(): ExternalReference
    {
        return ExternalReference::fromString('declaration-1');
    }

    private static function record(): DeclarationRecordId
    {
        return DeclarationRecordId::fromString('record-1');
    }

    private static function at(): Instant
    {
        return Instant::fromIso8601(self::AT);
    }
}
