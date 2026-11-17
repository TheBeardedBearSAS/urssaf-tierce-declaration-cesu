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

/**
 * A salary declaration (050, ADR 0004 sum type): {@see StandardDeclaration} or {@see CesuPlusDeclaration}.
 */
interface Declaration
{
    public function type(): DeclarationType;

    public function content(): DeclarationContent;

    public function activityNature(): ?ActivityNature;

    /**
     * SHA-256 (64 lowercase hexadecimal characters) of the canonical form of the declaration, used for idempotency
     * (ADR 0006 "same content"). Personal data (NIR, names, birth data, address, e-mail, phone, approval number) are
     * excluded; so is everything that is not part of the declared content.
     */
    public function fingerprint(): string;
}
