<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\DomainException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

/**
 * Thrown when an employer cannot be tracked because its external reference or its Cesu number already belongs to
 * another employer account of the tenant (ADR 0007, api-reference §2.1).
 *
 * Violation code: "employer_account.already_tracked".
 */
final class EmployerAlreadyTracked extends DomainException
{
    private const CODE = 'employer_account.already_tracked';

    public static function withExternalReference(ExternalReference $externalReference, EmployerAccountId $existing): self
    {
        return new self(
            \sprintf('The external reference "%s" is already used by another employer account.', $externalReference->value()),
            self::CODE,
            ['external_reference' => $externalReference->value(), 'employer_account_id' => $existing->value()],
        );
    }

    public static function withCesuNumber(CesuNumber $cesuNumber, EmployerAccountId $existing): self
    {
        return new self(
            \sprintf('The Cesu number "%s" is already tracked by another employer account.', $cesuNumber->value()),
            self::CODE,
            ['cesu_number' => $cesuNumber->value(), 'employer_account_id' => $existing->value()],
        );
    }
}
