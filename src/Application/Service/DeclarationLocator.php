<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

/**
 * Loads the declaration record designated by a {@see DeclarationSelector}, within one tenant.
 */
final readonly class DeclarationLocator
{
    private const RECORD_TYPE = 'declaration_record';

    public function __construct(private DeclarationRecordRepository $records) {}

    /**
     * @throws RecordNotFound "declaration_record.not_found" (also for a record of another tenant)
     */
    public function locate(TenantId $tenantId, DeclarationSelector $selector): DeclarationRecord
    {
        $reference = $selector->reference;
        if ($reference instanceof ExternalReference) {
            return $this->records->findByExternalReference($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }
        if ($reference instanceof DeclarationId) {
            return $this->records->findByDeclarationId($tenantId, $reference)
                ?? throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }
        $record = $this->records->get($reference);
        if (!$record->tenantId()->equals($tenantId)) {
            throw RecordNotFound::withIdentifier(self::RECORD_TYPE, $reference->value());
        }

        return $record;
    }
}
