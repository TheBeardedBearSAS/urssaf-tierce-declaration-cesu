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
 * Why the outcome of a submission is unknown (ADR 0003).
 */
enum OutcomeUnknownReason: string
{
    /** Transport error or 5xx on 050: the request may have reached URSSAF. */
    case MayHaveReachedUrssaf = 'may_have_reached_urssaf';

    /** Left in state "submitting" past the stale submission delay. */
    case StaleSubmission = 'stale_submission';
}
