<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;

/**
 * A package exception other than the port ones (stands for UnexpectedUrssafResponse without importing the
 * Infrastructure).
 */
final class UnexpectedGatewayFailure extends RuntimeException implements UrssafException {}
