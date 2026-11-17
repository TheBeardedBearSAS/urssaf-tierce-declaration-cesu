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
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;

/**
 * Cesu+ declaration (`typeDeclaration = 1`): the activity nature is required (api-reference §2.7, NATURE_ABSENTE).
 */
final readonly class CesuPlusDeclaration implements Declaration
{
    private function __construct(private DeclarationContent $content, private ActivityNature $activityNature) {}

    /**
     * @throws InvalidValue     "activity_nature.required"
     * @throws ValidationFailed when an intrinsic rule is violated
     */
    public static function of(DeclarationContent $content): self
    {
        $activityNature = $content->activityNature() ?? throw InvalidValue::because(
            'activity_nature.required',
            'A Cesu+ declaration requires an activity nature.',
        );
        DeclarationRules::intrinsic()->validate($content)->throwIfInvalid();

        return new self($content, $activityNature);
    }

    public function type(): DeclarationType
    {
        return DeclarationType::CesuPlus;
    }

    public function content(): DeclarationContent
    {
        return $this->content;
    }

    public function activityNature(): ActivityNature
    {
        return $this->activityNature;
    }

    public function fingerprint(): string
    {
        return CanonicalForm::fingerprint(DeclarationType::CesuPlus, $this->content);
    }
}
