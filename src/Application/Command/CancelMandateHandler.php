<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\MandateNotifier;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\EmployerLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\RecordedEvents;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\UnitOfWork;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Method 031 for a tracked employer holding a local active mandate: without one the command is refused locally, with no
 * call (the behaviour of URSSAF for an unknown mandate is not published). Every failure of the call propagates and
 * stores nothing.
 */
final readonly class CancelMandateHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
        private EmployerAccountRepository $accounts,
        private MandateNotifier $notifier,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws RecordNotFound
     * @throws MandateRequired
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(CancelMandate $command): MandateView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $mandate = $this->mandates->findActive($account->tenantId(), $account->cesuNumber())
            ?? throw MandateRequired::forEmployer($account->cesuNumber(), Operation::CancelMandate);

        $this->notifier->cancelMandate($account->tenantId(), $account->employer());

        $this->unitOfWork->run(function (RecordedEvents $events) use ($account, $mandate): void {
            $now = Instant::fromDateTime($this->clock->now());
            $mandate->cancel($now);
            $account->markMandateCancelled($now);
            $this->mandates->save($mandate);
            $this->accounts->save($account);
            $events->record(...$mandate->releaseEvents(), ...$account->releaseEvents());
        });

        return MandateView::fromMandate($mandate);
    }
}
