<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;

/**
 * Local follow-up stage of an {@see EmployerAccount} (ADR 0006). Stages follow URSSAF and never block a call, so there
 * is no invalid transition. The Cesu+ activation is relative to an employer / employee pair and is not a stage.
 */
enum EmployerStage: string
{
    case Registered = 'registered';
    case MandateNotified = 'mandate_notified';
    case MandateConfirmed = 'mandate_confirmed';
    case AiciActive = 'aici_active';
    case MandateCancelled = 'mandate_cancelled';

    /**
     * Method 030 succeeded: registered and cancelled employers move to "mandate_notified", others are unchanged.
     */
    public function afterMandateNotified(): self
    {
        return match ($this) {
            self::Registered, self::MandateCancelled => self::MandateNotified,
            self::MandateNotified, self::MandateConfirmed, self::AiciActive => $this,
        };
    }

    /**
     * Method 031 succeeded.
     */
    public function afterMandateCancelled(): self
    {
        return self::MandateCancelled;
    }

    /**
     * Method 020 answered. Without a mandate, a notified or cancelled employer stays as is (URSSAF may not reflect
     * method 030 / 031 yet), any other stage goes back to "registered".
     */
    public function afterStatus(EmployerStatus $status): self
    {
        if ($status->mandatePresent()) {
            return $status->aiciActive() ? self::AiciActive : self::MandateConfirmed;
        }

        return match ($this) {
            self::MandateNotified, self::MandateCancelled => $this,
            self::Registered, self::MandateConfirmed, self::AiciActive => self::Registered,
        };
    }
}
