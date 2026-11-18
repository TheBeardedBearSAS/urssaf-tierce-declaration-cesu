<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Maps the 050 response `OutputDeclaration` (api-reference §2.7). Only `idDeclaration` is required (a JSON integer is
 * accepted); every amount is optional because its presence is not published (`montantVirementSalaire` is Cesu+ only).
 * Amounts are decoded without float drift ({@see MoneyEncoding}); a negative amount is refused.
 */
final readonly class DeclarationResponseMapper
{
    /**
     * @throws UnexpectedUrssafResponse
     */
    public function map(Payload $payload): DeclarationResult
    {
        return new DeclarationResult(
            $this->declarationId($payload),
            $this->contributions($payload),
            $this->amount($payload, 'montantPAS'),
            $this->amount($payload, 'montantPrelevementPECotisations'),
            $this->amount($payload, 'montantPrelevementPEPAS'),
            $this->amount($payload, 'montantPrelevementPESalaire'),
            $this->amount($payload, 'montantSalaireBrut'),
            $this->amount($payload, 'montantSalaireNetImposable'),
            $this->amount($payload, 'montantVirementSalaire'),
            $this->amount($payload, 'montantAICI'),
        );
    }

    private function declarationId(Payload $payload): DeclarationId
    {
        try {
            return DeclarationId::fromString($payload->requireIdentifier('idDeclaration'));
        } catch (InvalidValue $exception) {
            throw UnexpectedUrssafResponse::causedBy('field "idDeclaration" is not valid', $exception);
        }
    }

    private function contributions(Payload $payload): ?Contributions
    {
        $contributions = $payload->object('montantsCotisations');

        return $contributions instanceof Payload ? (new ContributionsMapper())->map($contributions) : null;
    }

    private function amount(Payload $payload, string $key): ?Money
    {
        $amount = $payload->number($key);

        return $amount === null ? null : MoneyEncoding::decode($amount);
    }
}
