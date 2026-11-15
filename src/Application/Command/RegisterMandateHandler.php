<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\MandateView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Clock;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\IdGenerator;
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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Method 030 for a tracked employer. A local active mandate is returned as is, without any call (the behaviour of
 * URSSAF on a second notification is not published). Every failure of the call propagates and stores nothing:
 * {@see UrssafRequestRejected::isMandateHeldElsewhere()}, {@see UrssafRequestRejected::isAccountUnknown()}, invalid
 * credentials, temporary failures (safe to retry).
 */
final readonly class RegisterMandateHandler
{
    public function __construct(
        private EmployerLocator $locator,
        private MandateRepository $mandates,
        private EmployerAccountRepository $accounts,
        private MandateNotifier $notifier,
        private IdGenerator $ids,
        private Clock $clock,
        private UnitOfWork $unitOfWork,
    ) {}

    /**
     * @throws RecordNotFound
     * @throws UrssafRequestRejected
     * @throws UrssafAuthenticationFailed
     * @throws UrssafUnavailable
     * @throws RateLimitExceeded
     */
    public function __invoke(RegisterMandate $command): MandateView
    {
        $account = $this->locator->locate($command->tenantId, $command->employer);
        $active = $this->mandates->findActive($account->tenantId(), $account->cesuNumber());
        if ($active instanceof Mandate) {
            return MandateView::fromMandate($active);
        }

        $this->notifier->registerMandate($account->tenantId(), $account->employer());

        $mandate = $this->unitOfWork->run(function (RecordedEvents $events) use ($account): Mandate {
            $now = Instant::fromDateTime($this->clock->now());
            $mandate = Mandate::notify(
                MandateId::fromString($this->ids->generate()),
                $account->tenantId(),
                $account->externalReference(),
                $account->employer(),
                $now,
            );
            $account->markMandateNotified($now);
            $this->mandates->save($mandate);
            $this->accounts->save($account);
            $events->record(...$mandate->releaseEvents(), ...$account->releaseEvents());

            return $mandate;
        });

        return MandateView::fromMandate($mandate);
    }
}
