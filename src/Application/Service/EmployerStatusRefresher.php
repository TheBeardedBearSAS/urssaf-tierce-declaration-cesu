<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerification;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployerVerifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Reads the URSSAF status of a tracked employer (method 020, optionally for an employee to read the Cesu+ state of the
 * pair) and applies the answer to the account and to its active mandate, in one transaction (ADR 0006).
 *
 * The call is made outside any transaction. No mandate is created when 020 reports a mandate unknown locally: only
 * a local mandate is confirmed by the answer (lot 4 design, open point 4).
 */
final readonly class EmployerStatusRefresher
{
    public function __construct(
        private EmployerVerifier $verifier,
        private MandateRepository $mandates,
        private EmployerAccountRepository $accounts,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * True when an event was recorded.
     *
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function refresh(EmployerAccount $account, ?EmployeeReference $employee): bool
    {
        $verification = $employee instanceof EmployeeReference
            ? EmployerVerification::forPair($account->employer(), $employee)
            : EmployerVerification::employerOnly($account->employer());
        $status = $this->verifier->verifyEmployer($account->tenantId(), $verification);

        return $this->unitOfWork->run(function (RecordedEvents $events) use ($account, $employee, $status): bool {
            $now = Instant::fromDateTime($this->clock->now());
            $account->applyStatus($status, $employee, $now);
            $mandate = $this->mandates->findActive($account->tenantId(), $account->cesuNumber());
            $mandateEvents = [];
            if ($mandate instanceof Mandate) {
                // An active mandate is never cancelled: applying "present" cannot be refused.
                $mandate->applyStatus($status->mandatePresent(), $now);
                $this->mandates->save($mandate);
                $mandateEvents = $mandate->releaseEvents();
            }
            $this->accounts->save($account);
            $released = [...$account->releaseEvents(), ...$mandateEvents];
            $events->record(...$released);

            return $released !== [];
        });
    }
}
