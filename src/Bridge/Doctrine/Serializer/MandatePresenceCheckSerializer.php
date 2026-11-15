<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandatePresenceCheck;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Explicit JSON shape of a {@see MandatePresenceCheck}: the answer of method 020 and the instant it was read.
 *
 * @phpstan-type CheckData array{mandatePresent: bool, checkedAt: string}
 */
final class MandatePresenceCheckSerializer
{
    /**
     * @return CheckData
     */
    public static function toArray(MandatePresenceCheck $check): array
    {
        return ['mandatePresent' => $check->mandatePresent(), 'checkedAt' => $check->checkedAt()->toIso8601()];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape CheckData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): MandatePresenceCheck
    {
        /** @var CheckData $data */
        return MandatePresenceCheck::of($data['mandatePresent'], Instant::fromIso8601($data['checkedAt']));
    }
}
