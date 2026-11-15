<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message;

/**
 * Asks to refresh (method 020, employer only) one tracked employer, e.g. dispatched with a DelayStamp after the mandate
 * notification (030). Holds identifiers only (no personal data, ADR 0007); safe to retry (a read). Lot 6 design 7.1.
 */
final readonly class SyncEmployerStatusMessage
{
    public function __construct(public string $tenantId, public string $employerAccountId) {}
}
