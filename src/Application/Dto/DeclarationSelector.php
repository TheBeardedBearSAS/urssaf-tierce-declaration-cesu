<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Designates a declaration record of a tenant: by record id, by the host external reference or by the URSSAF
 * declaration id.
 */
final readonly class DeclarationSelector
{
    private function __construct(public DeclarationRecordId|ExternalReference|DeclarationId $reference) {}

    public static function byRecordId(DeclarationRecordId $recordId): self
    {
        return new self($recordId);
    }

    public static function byExternalReference(ExternalReference $externalReference): self
    {
        return new self($externalReference);
    }

    public static function byDeclarationId(DeclarationId $declarationId): self
    {
        return new self($declarationId);
    }
}
