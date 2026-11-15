<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecord;

/**
 * Read model of a declaration record (primitives only, no personal data). Instants are ISO 8601 UTC strings.
 */
final readonly class DeclarationView
{
    private function __construct(
        public string $recordId,
        public string $tenantId,
        public string $externalReference,
        public DeclarationSnapshotView $snapshot,
        public DeclarationStatusView $status,
        public string $createdAt,
    ) {}

    public static function fromRecord(DeclarationRecord $record): self
    {
        return new self(
            $record->id()->value(),
            $record->tenantId()->value(),
            $record->externalReference()->value(),
            DeclarationSnapshotView::fromSnapshot($record->snapshot()),
            DeclarationStatusView::fromStatus($record->status()),
            $record->createdAt()->toIso8601(),
        );
    }
}
