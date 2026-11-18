<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\CesuPlusActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Operations that write real sandbox state (010, 030, 031, 050, 060, 070), opt-in with URSSAF_SANDBOX_ALLOW_WRITES=1.
 * Each flow is one test method (the order of the tests is random) and restores the mandate it touched.
 *
 * @mago-expect lint:cyclomatic-complexity One branch per observable outcome of each write operation.
 * @mago-expect lint:too-many-methods One method per write flow plus its outcome helpers.
 */
#[Group('contract')]
#[Group('contract-writes')]
final class WriteSandboxContractTest extends TestCase
{
    private SandboxSession $session;

    public static function tearDownAfterClass(): void
    {
        SandboxReport::flush();
    }

    protected function setUp(): void
    {
        $this->session = SandboxSession::start(self::class, true);
    }

    public function testRegistersAFictitiousEmployerThenActivatesAici(): void
    {
        $registration = $this->session->attempt(fn(): CesuNumber => $this->session->sandbox->gateway->registerEmployer($this->session->sandbox->tenantId, SandboxData::newEmployer()));
        $number = $registration['result'];
        if (!$number instanceof CesuNumber) {
            $this->session->observe('5.16-010-mandate', '010_rejected:' . SandboxSession::codesOf($registration['rejected']), SandboxReport::NOT_OBSERVED);
            $this->session->assertRecorded('5.16-010-mandate');

            return;
        }

        $this->session->observe('5.23-employer-cesu-format', $registration['exchange']?->numeroCesuShape);
        $employer = Employer::of($number, CalendarDate::fromString(SandboxData::NEW_EMPLOYER_BIRTH_DATE));

        $status = $this->session->attempt(fn(): EmployerStatus => $this->session->sandbox->gateway->verifyEmployer($this->session->sandbox->tenantId, EmployerVerification::employerOnly($employer)));
        $employerStatus = $status['result'];
        $this->session->observe('5.16-010-mandate', $employerStatus instanceof EmployerStatus
            ? ['mandatePresent' => $employerStatus->mandatePresent()]
            : '020_' . (($status['rejected']?->kind() === ErrorKind::InvalidParameter) ? 'param_invalide' : 'rejected') . ':' . SandboxSession::codesOf($status['rejected']));

        $aici = $this->session->attempt(fn(): AiciActivation => $this->session->sandbox->gateway->activateAici($this->session->sandbox->tenantId, SandboxData::aiciRequestFor($employer)));
        $activation = $aici['result'];
        $this->session->observe('070-aici', $activation instanceof AiciActivation ? $this->activationOutcome($activation) : 'rejected:' . SandboxSession::codesOf($aici['rejected']));
        $this->session->assertRecorded('070-aici');
    }

    public function testMandateRepostAndCancelOfAnAbsentMandate(): void
    {
        $employer = $this->session->requireEmployer();
        $initial = $this->readMandate($employer, $this->session->env->employeeCesuNumber());

        $first = $this->session->attempt(fn() => $this->session->sandbox->gateway->registerMandate($this->session->sandbox->tenantId, $employer));
        if ($first['rejected'] instanceof UrssafRequestRejected) {
            $this->session->observe('5.17-030-repost', 'rejected:' . SandboxSession::codesOf($first['rejected']));
            if ($first['rejected']->isMandateHeldElsewhere()) {
                static::markTestSkipped('Mandate held by another platform: nothing posted, nothing to restore.');
            }
            static::fail('030 refused: ' . SandboxAssert::describe($first['rejected']));
        }

        $present = true;

        try {
            $second = $this->session->attempt(fn() => $this->session->sandbox->gateway->registerMandate($this->session->sandbox->tenantId, $employer));
            $this->session->observe('5.17-030-repost', $this->mandateOutcome($second['rejected']));

            $cancel = $this->session->attempt(fn() => $this->session->sandbox->gateway->cancelMandate($this->session->sandbox->tenantId, $employer));
            static::assertNotInstanceOf(UrssafRequestRejected::class, $cancel['rejected'], '031 refused after 030: ' . SandboxSession::codesOf($cancel['rejected']));
            $present = false;

            $again = $this->session->attempt(fn() => $this->session->sandbox->gateway->cancelMandate($this->session->sandbox->tenantId, $employer));
            $this->session->observe('5.17-031-cancel-absent', $this->mandateOutcome($again['rejected']));
        } finally {
            $this->restoreMandate($employer, $initial, $present);
        }
    }

    public function testDeclaresASalaryThenTheDuplicateIsRefused(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->requireEmployee();
        $declaration = SandboxData::standardDeclaration($employer, $employee);

        $mandate = $this->readMandate($employer, $employee);
        $posted = false;
        if (!$mandate) {
            $registered = $this->session->attempt(fn() => $this->session->sandbox->gateway->registerMandate($this->session->sandbox->tenantId, $employer));
            if ($registered['rejected'] instanceof UrssafRequestRejected) {
                static::markTestSkipped('030 refused (' . SandboxSession::codesOf($registered['rejected']) . '): cannot declare without a mandate.');
            }
            $posted = true;
        }

        try {
            $first = $this->session->attempt(fn(): DeclarationResult => $this->session->sandbox->gateway->declare($this->session->sandbox->tenantId, $declaration));
            if ($first['result'] instanceof DeclarationResult) {
                $this->session->observe('5.33-idDeclaration-type', $first['exchange']?->jsonTypes['idDeclaration'] ?? 'absent');
                $second = $this->session->attempt(fn(): DeclarationResult => $this->session->sandbox->gateway->declare($this->session->sandbox->tenantId, $declaration));
                $this->session->observe('050-duplicate', $this->declarationOutcome($second['result'], $second['rejected']));
                $this->session->assertRecorded('050-duplicate');

                return;
            }

            $this->session->observe('050-duplicate', $this->declarationOutcome(null, $first['rejected']));
            $this->session->assertRecorded('050-duplicate');
        } finally {
            if ($posted) {
                $this->restoreMandate($employer, false, true);
            }
        }
    }

    public function testCesuPlusActivationAgreementEncoding(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->requireEmployee();
        $request = CesuPlusActivationRequest::of($employer, $employee, true);

        $boolean = $this->session->attempt(fn(): CesuPlusActivation => $this->session->sandbox->gateway->activateCesuPlus($this->session->sandbox->tenantId, $request));
        if ($boolean['rejected']?->kind() === ErrorKind::InvalidParameter) {
            $numeric = $this->session->attempt(fn(): CesuPlusActivation => $this->session->sandbox->numericAgreementGateway->activateCesuPlus($this->session->sandbox->tenantId, $request));
            static::assertNotSame(ErrorKind::InvalidParameter, $numeric['rejected']?->kind(), 'accord refused in both encodings');
            $this->session->observe('5.6-accord', $this->encodingOutcome('numeric', $numeric));
            $this->observeCesuPlusState($employer, $employee);

            return;
        }

        $this->session->observe('5.6-accord', $this->encodingOutcome('boolean', $boolean));
        $this->observeCesuPlusState($employer, $employee);
    }

    private function observeCesuPlusState(Employer $employer, CesuNumber $employee): void
    {
        $pair = $this->session->attempt(fn(): EmployerStatus => $this->session->sandbox->gateway->verifyEmployer($this->session->sandbox->tenantId, EmployerVerification::forPair($employer, EmployeeByCesuNumber::of($employee))));
        $spelling = $pair['exchange']?->enumValues['etatCesuPlus'] ?? null;
        $this->session->observe('5.1-etatCesuPlus', $spelling ?? 'absent', null === $spelling ? SandboxReport::NOT_OBSERVED : SandboxReport::OBSERVED);
        $this->session->assertRecorded('5.6-accord');
    }

    /**
     * @param array{result: null|CesuPlusActivation, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    private function encodingOutcome(string $encoding, array $call): string
    {
        return $call['result'] instanceof CesuPlusActivation ? $encoding : \sprintf('undetermined:%s:%s', $encoding, SandboxSession::codesOf($call['rejected']));
    }

    private function activationOutcome(AiciActivation $activation): string
    {
        return $activation->isActive() ? 'active' : 'inactive';
    }

    private function declarationOutcome(?DeclarationResult $result, ?UrssafRequestRejected $rejected): string
    {
        return match (true) {
            $result instanceof DeclarationResult => 'accepted_twice',
            $rejected?->isDuplicateDeclaration() ?? false => 'duplicate_refused',
            default => 'rejected:' . SandboxSession::codesOf($rejected),
        };
    }

    private function mandateOutcome(?UrssafRequestRejected $rejected): string
    {
        return match (true) {
            !$rejected instanceof UrssafRequestRejected => 'accepted',
            $rejected->isMandateHeldElsewhere() => 'already_mandated',
            default => 'rejected:' . SandboxSession::codesOf($rejected),
        };
    }

    /**
     * Current mandate presence. Employer-only 020 first, then the pair when it is refused (open point 5.9).
     */
    private function readMandate(Employer $employer, ?CesuNumber $employee): bool
    {
        $verification = EmployerVerification::employerOnly($employer);
        $call = $this->session->attempt(fn(): EmployerStatus => $this->session->sandbox->gateway->verifyEmployer($this->session->sandbox->tenantId, $verification));
        if ($call['rejected']?->kind() === ErrorKind::InvalidParameter) {
            if (!$employee instanceof CesuNumber) {
                static::markTestSkipped('Employer-only 020 refused (PARAM_INVALIDE): set URSSAF_SANDBOX_EMPLOYEE_CESU_NUMBER.');
            }
            $pair = EmployerVerification::forPair($employer, EmployeeByCesuNumber::of($employee));
            $call = $this->session->attempt(fn(): EmployerStatus => $this->session->sandbox->gateway->verifyEmployer($this->session->sandbox->tenantId, $pair));
        }

        $status = $call['result'];
        static::assertInstanceOf(EmployerStatus::class, $status, 'Cannot read the initial mandate: ' . SandboxSession::codesOf($call['rejected']));

        return $status->mandatePresent();
    }

    /**
     * Puts the mandate back in its initial state. A failure here fails the test loudly, whatever happened before.
     *
     * Both flags are states of the same mandate (the one it had, the one it has now), not modes of the function.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function restoreMandate(Employer $employer, bool $initial, bool $present): void
    {
        if ($initial === $present) {
            return;
        }

        $call = $initial
            ? $this->session->attempt(fn() => $this->session->sandbox->gateway->registerMandate($this->session->sandbox->tenantId, $employer))
            : $this->session->attempt(fn() => $this->session->sandbox->gateway->cancelMandate($this->session->sandbox->tenantId, $employer));
        if ($call['rejected'] instanceof UrssafRequestRejected) {
            static::fail(\sprintf('Mandate state NOT restored: initial=%s, %s', $initial ? 'true' : 'false', SandboxAssert::describe($call['rejected'])));
        }
    }
}
