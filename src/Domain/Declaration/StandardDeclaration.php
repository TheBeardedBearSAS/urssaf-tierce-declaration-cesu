<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\DeclarationRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;

/**
 * Standard declaration (`typeDeclaration = 0`): the activity nature is optional.
 */
final readonly class StandardDeclaration implements Declaration
{
    private function __construct(private DeclarationContent $content) {}

    /**
     * @throws ValidationFailed when an intrinsic rule is violated
     */
    public static function of(DeclarationContent $content): self
    {
        DeclarationRules::intrinsic()->validate($content)->throwIfInvalid();

        return new self($content);
    }

    public function type(): DeclarationType
    {
        return DeclarationType::Standard;
    }

    public function content(): DeclarationContent
    {
        return $this->content;
    }

    public function activityNature(): ?ActivityNature
    {
        return $this->content->activityNature();
    }

    public function fingerprint(): string
    {
        return CanonicalForm::fingerprint(DeclarationType::Standard, $this->content);
    }
}
