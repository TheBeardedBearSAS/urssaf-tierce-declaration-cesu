<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Thrown when a tenant records again an external reference with a different declaration content (ADR 0006).
 *
 * Violation code: "declaration_record.already_recorded".
 */
final class DeclarationAlreadyRecorded extends DomainException
{
    public static function withExternalReference(ExternalReference $externalReference, DeclarationRecordId $existing): self
    {
        return new self(
            \sprintf('The external reference "%s" is already recorded with another declaration content.', $externalReference->value()),
            'declaration_record.already_recorded',
            ['external_reference' => $externalReference->value(), 'declaration_record_id' => $existing->value()],
        );
    }
}
