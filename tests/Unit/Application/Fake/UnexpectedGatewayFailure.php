<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;

/**
 * A package exception other than the port ones (stands for UnexpectedUrssafResponse without importing the
 * Infrastructure).
 */
final class UnexpectedGatewayFailure extends RuntimeException implements UrssafException {}
