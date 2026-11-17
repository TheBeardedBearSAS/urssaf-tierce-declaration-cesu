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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Read-only operations against the URSSAF sandbox: token, 020, 021 and 040 (nothing is stored by 040). Hard assertions
 * cover what the SDK relies on; open points of docs/api-reference.md section 5 are observed into the report.
 *
 * One method per operation and per observed point, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
#[Group('contract')]
final class ReadOnlySandboxContractTest extends TestCase
{
    private SandboxSession $session;

    public static function tearDownAfterClass(): void
    {
        SandboxReport::flush();
    }

    protected function setUp(): void
    {
        $this->session = SandboxSession::start(self::class, false);
    }

    public function testTokenEndpointOfTheSandboxHostIssuesAToken(): void
    {
        $derived = $this->session->sandbox->rawTokenProbe($this->session->sandbox->configuration->tokenUri());
        $chosen = $derived;
        $override = null;
        $used = 'derived';
        if (!$derived->isSuccessful() && null !== $this->session->sandbox->tokenUriOverride) {
            $override = $this->session->sandbox->rawTokenProbe($this->session->sandbox->tokenUriOverride);
            if ($override->isSuccessful()) {
                $chosen = $override;
                $used = 'override';
            }
        }

        $this->session->observe('5.14-token-host', [
            'derived' => $derived->status,
            'override' => $override?->status,
            'used' => $used,
            'tokenType' => $chosen->enumValues['token_type'] ?? 'absent',
        ]);

        $message = \sprintf(
            'Token request answered %d by %s%s. %s',
            $derived->status,
            $derived->host,
            $override instanceof Exchange ? \sprintf(', %d by %s (override)', $override->status, $override->host) : '',
            SandboxAssert::authenticationHint(),
        );
        static::assertSame(200, $chosen->status, $message);
        static::assertSame(Exchange::OBJECT, $chosen->bodyShape, $message);
        static::assertContains('access_token', $chosen->bodyKeys);
        static::assertContains('expires_in', $chosen->bodyKeys);
        static::assertContains($chosen->jsonTypes['expires_in'] ?? '', ['integer', 'float']);
    }

    public function testEstimatesTheOfficialExample(): void
    {
        $call = $this->session->attempt(fn(): ContributionsEstimate => $this->session->sandbox->gateway->estimate(
            $this->session->sandbox->tenantId,
            SandboxData::officialEstimateRequest(),
        ));

        static::assertInstanceOf(ContributionsEstimate::class, $call['result'], 'The estimate was rejected: ' . SandboxSession::codesOf($call['rejected']));
        $exchange = $call['exchange'];
        static::assertInstanceOf(Exchange::class, $exchange);
        static::assertSame(200, $exchange->status);
        static::assertSame(Exchange::OBJECT, $exchange->bodyShape);
        $this->session->observe('5.32-estimate-decimals', ['moreThanTwoDecimals' => $exchange->hasMoreThanTwoDecimals]);
    }

    public function testVerifyingAnUnknownEmployerIsNotAnErrorOrIsCatalogued(): void
    {
        $employer = SandboxData::fictitiousEmployer();
        $employerOnly = $this->verifyEmployer($employer, null);
        $pair = $this->verifyEmployer($employer, EmployeeByCesuNumber::of(CesuNumber::fromString(SandboxData::fictitiousEmployeeCesuNumber())));

        foreach ([$employerOnly, $pair] as $call) {
            static::assertTrue(
                $call['result'] instanceof EmployerStatus || $call['rejected'] instanceof UrssafRequestRejected,
                'Neither a status nor a catalogued rejection.',
            );
        }

        $this->session->observe('5.18-020-unknown', [
            'pair' => $this->outcome($pair),
            'employerOnly' => $employerOnly['rejected']?->kind() === ErrorKind::InvalidParameter ? 'ambiguous' : $this->outcome($employerOnly),
        ]);
        $this->observeStatusFlags($pair);
        $this->observeRejectionShape($pair['exchange'], $pair['rejected']);
    }

    public function testVerifyingAnUnknownEmployeeIsNotAnErrorOrIsCatalogued(): void
    {
        $call = $this->session->attempt(fn(): EmployeeVerificationResult => $this->session->sandbox->gateway->verifyEmployee(
            $this->session->sandbox->tenantId,
            EmployeeByCesuNumber::of(CesuNumber::fromString(SandboxData::fictitiousEmployeeCesuNumber())),
        ));

        static::assertTrue(
            $call['result'] instanceof EmployeeVerificationResult || $call['rejected'] instanceof UrssafRequestRejected,
            'Neither a result nor a catalogued rejection.',
        );
        $result = $call['result'];
        $this->session->observe('5.18-021-unknown', $result instanceof EmployeeVerificationResult
            ? ['registered' => $result->registered(), 'numeroCesuPresent' => $result->cesuNumber() instanceof CesuNumber]
            : 'rejected:' . SandboxSession::codesOf($call['rejected']));
        $this->observeRejectionShape($call['exchange'], $call['rejected']);
    }

    public function testEmployerOnlyVerification(): void
    {
        $call = $this->verifyEmployer($this->session->requireEmployer(), null);

        $status = $call['result'];
        if ($status instanceof EmployerStatus) {
            $this->session->observe('5.9-020-employer-only', [
                'outcome' => 'accepted',
                'registered' => $status->registered(),
                'mandatePresent' => $status->mandatePresent(),
                'aiciActive' => $status->aiciActive(),
                'onlineAccount' => $call['exchange']?->enumValues['etatCompteEnLigne'] ?? 'absent',
                'sepaMandate' => $call['exchange']?->enumValues['etatMandatSepa'] ?? 'absent',
            ]);
            $this->session->assertRecorded('5.9-020-employer-only');

            return;
        }

        $rejected = $call['rejected'];
        static::assertInstanceOf(UrssafRequestRejected::class, $rejected);
        static::assertSame(ErrorKind::InvalidParameter, $rejected->kind(), 'Unexpected rejection: ' . SandboxAssert::describe($rejected));
        $this->session->observe('5.9-020-employer-only', 'param_invalide');
    }

    public function testPairVerificationExposesTheCesuPlusState(): void
    {
        $employer = $this->session->requireEmployer();
        $employee = $this->session->requireEmployee();
        $call = $this->verifyEmployer($employer, EmployeeByCesuNumber::of($employee));

        $status = $call['result'];
        static::assertInstanceOf(EmployerStatus::class, $status, 'Pair verification rejected: ' . SandboxSession::codesOf($call['rejected']));
        $exchange = $call['exchange'];
        static::assertInstanceOf(Exchange::class, $exchange);
        if (null !== $status->rawCesuPlusState()) {
            static::assertNotNull(
                $status->cesuPlusState(),
                'Unknown etatCesuPlus spelling: ' . ($exchange->enumValues['etatCesuPlus'] ?? 'absent'),
            );
        }

        $this->session->observe(
            '5.1-etatCesuPlus',
            $exchange->enumValues['etatCesuPlus'] ?? 'absent',
            \array_key_exists('etatCesuPlus', $exchange->enumValues) ? SandboxReport::OBSERVED : SandboxReport::NOT_OBSERVED,
        );
        $motifTypes = array_intersect_key($exchange->jsonTypes, ['motifBlocageCesuPlus' => 1, 'motifblocageCesuPlus' => 1]);
        $this->session->observe(
            '5.2-motifBlocage',
            ['types' => $motifTypes, 'isStringNull' => $exchange->motifIsStringNull],
            [] === $motifTypes ? SandboxReport::NOT_OBSERVED : SandboxReport::OBSERVED,
        );
    }

    public function testKnownEmployeeIsRegistered(): void
    {
        $employee = $this->session->requireEmployee();
        $call = $this->session->attempt(fn(): EmployeeVerificationResult => $this->session->sandbox->gateway->verifyEmployee(
            $this->session->sandbox->tenantId,
            EmployeeByCesuNumber::of($employee),
        ));

        $result = $call['result'];
        static::assertInstanceOf(EmployeeVerificationResult::class, $result, 'Rejected: ' . SandboxSession::codesOf($call['rejected']));
        static::assertTrue($result->registered());
        static::assertTrue($result->cesuNumber()?->equals($employee) ?? false, 'The returned Cesu number differs from the configured one.');

        $shape = $call['exchange']?->numeroCesuShape;
        static::assertNotNull($shape);
        $this->session->observe('5.34-021-numeroCesu-padding', ['padded' => $shape['length'] !== $shape['trimmedLength']]);
        $this->session->observe('5.23-employee-cesu-format', $shape);
    }

    /**
     * @return array{result: null|EmployerStatus, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    private function verifyEmployer(Employer $employer, ?EmployeeByCesuNumber $employee): array
    {
        $verification = $employee instanceof EmployeeByCesuNumber
            ? EmployerVerification::forPair($employer, $employee)
            : EmployerVerification::employerOnly($employer);

        return $this->session->attempt(fn(): EmployerStatus => $this->session->sandbox->gateway->verifyEmployer($this->session->sandbox->tenantId, $verification));
    }

    /**
     * @param array{result: null|EmployerStatus, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    private function outcome(array $call): string
    {
        $status = $call['result'];

        if (!$status instanceof EmployerStatus) {
            return 'rejected:' . SandboxSession::codesOf($call['rejected']);
        }

        return $status->registered() ? 'inscrit_true' : 'inscrit_false';
    }

    /**
     * @param array{result: null|EmployerStatus, rejected: null|UrssafRequestRejected, exchange: null|Exchange} $call
     */
    private function observeStatusFlags(array $call): void
    {
        $exchange = $call['exchange'];
        $types = $exchange instanceof Exchange ? $exchange->jsonTypes : [];
        $this->session->observe(
            '5.31-020-absent-flags',
            ['mandatPresent' => $types['mandatPresent'] ?? 'absent', 'etatAICI' => $types['etatAICI'] ?? 'absent'],
            $call['result'] instanceof EmployerStatus ? SandboxReport::OBSERVED : SandboxReport::NOT_OBSERVED,
        );
    }

    private function observeRejectionShape(?Exchange $exchange, ?UrssafRequestRejected $rejected): void
    {
        if ($exchange instanceof Exchange && 400 === $rejected?->httpStatus()) {
            $this->session->observe('5.19-400-shape', ['bodyShape' => $exchange->bodyShape, 'bodyKeys' => $exchange->bodyKeys]);
        }
    }
}
