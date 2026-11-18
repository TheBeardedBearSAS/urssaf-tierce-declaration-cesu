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

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\OutcomeUnknown;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use Throwable;

/**
 * What a live contract test class shares (composition, not inheritance: the project forbids extending test classes):
 * skips without credentials, one shared gateway (one token) and the helpers that record observations without ever
 * echoing URSSAF texts.
 */
final readonly class SandboxSession
{
    private function __construct(
        public SandboxEnvironment $env,
        public SandboxGateway $sandbox,
        private string $testClass,
    ) {}

    /**
     * Skips the calling test unless the credentials (and, for write tests, the opt-in) are set.
     *
     * The flag is the class of tests (read-only or writing), fixed by each test class, not a mode chosen per call.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     * @throws SandboxMisconfigured
     */
    public static function start(string $testClass, bool $writes): self
    {
        $env = SandboxEnvironment::fromProcess();
        $sandbox = SandboxGateway::shared();
        if (!$sandbox instanceof SandboxGateway) {
            TestCase::markTestSkipped(
                'URSSAF sandbox contract tests are opt-in: set URSSAF_SANDBOX_CLIENT_ID and URSSAF_SANDBOX_CLIENT_SECRET (see README, "Sandbox contract tests").',
            );
        }
        if ($writes && !$env->writesAllowed()) {
            TestCase::markTestSkipped('Writes real sandbox state: set URSSAF_SANDBOX_ALLOW_WRITES=1 to run (010/030/031/050/060/070).');
        }

        return new self($env, $sandbox, $testClass);
    }

    public function requireEmployer(): Employer
    {
        $employer = $this->env->employer();
        if (!$employer instanceof Employer) {
            TestCase::markTestSkipped('Set URSSAF_SANDBOX_EMPLOYER_CESU_NUMBER and URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE to run this test.');
        }

        return $employer;
    }

    public function requireEmployee(): CesuNumber
    {
        $employee = $this->env->employeeCesuNumber();
        if (!$employee instanceof CesuNumber) {
            TestCase::markTestSkipped('Set URSSAF_SANDBOX_EMPLOYEE_CESU_NUMBER to run this test.');
        }

        return $employee;
    }

    public function observe(string $point, mixed $value, string $state = SandboxReport::OBSERVED): void
    {
        SandboxReport::record($point, $state, $value, $this->testClass);
    }

    /**
     * Every flow ends here: the test asserts that it did record something about the point it observes.
     */
    public function assertRecorded(string $point): void
    {
        Assert::assertNotSame(SandboxReport::NOT_RUN, SandboxReport::points()[$point]['state'], \sprintf('Nothing was recorded for %s.', $point));
    }

    /**
     * Runs one gateway call. A business rejection is returned (the caller decides), everything else is translated:
     * authentication failure and unavailability fail with a redacted message, quota (429) and unknown outcome mark the
     * test incomplete (never retried: ADR 0003).
     *
     * @template T
     *
     * @param-immediately-invoked-callable $call
     * @param callable(): T $call
     *
     * @return array{result: null|T, rejected: null|UrssafRequestRejected, exchange: null|Exchange}
     */
    public function attempt(callable $call): array
    {
        $mark = $this->sandbox->http->mark();
        $result = null;
        $rejected = null;

        try {
            $result = $call();
        } catch (UrssafRequestRejected $e) {
            SandboxAssert::allCodesCatalogued($e->errors());
            $rejected = $e;
        } catch (UrssafUnavailable $e) {
            if ($e->isQuotaExceeded()) {
                TestCase::markTestIncomplete('URSSAF quota reached (429), retry later.');
            }
            Assert::fail('Unavailable: ' . SandboxAssert::describe($e));
        } catch (OutcomeUnknown $e) {
            TestCase::markTestIncomplete('Outcome unknown, never retried: ' . SandboxAssert::describe($e));
        } catch (UrssafAuthenticationFailed $e) {
            Assert::fail(SandboxAssert::describe($e));
        } catch (Throwable $e) {
            Assert::fail('Unexpected ' . SandboxAssert::describe($e));
        }

        $apiExchanges = array_values(array_filter(
            $this->sandbox->http->since($mark),
            fn(Exchange $exchange): bool => !$this->sandbox->http->isTokenExchange($exchange),
        ));

        return [
            'result' => $result,
            'rejected' => $rejected,
            'exchange' => [] === $apiExchanges ? null : $apiExchanges[\count($apiExchanges) - 1],
        ];
    }

    public static function codesOf(?UrssafRequestRejected $rejected): string
    {
        return $rejected instanceof UrssafRequestRejected ? implode(',', $rejected->errors()->codes()) : '';
    }
}
