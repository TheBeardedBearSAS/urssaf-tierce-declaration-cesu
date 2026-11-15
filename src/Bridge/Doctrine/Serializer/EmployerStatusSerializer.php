<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Doctrine\Serializer;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Explicit JSON shape of an {@see EmployerStatus}: the raw state codes (an unknown code survives, ADR 0000 tolerant
 * enums), never the interpreted enums.
 *
 * @phpstan-type StatusData array{registered: bool, onlineAccountState: string|null, sepaMandateState: string|null, mandatePresent: bool, cesuPlusState: string|null, cesuPlusBlockingReason: string|null, aiciActive: bool}
 */
final class EmployerStatusSerializer
{
    /**
     * @return StatusData
     */
    public static function toArray(EmployerStatus $status): array
    {
        return [
            'registered' => $status->registered(),
            'onlineAccountState' => $status->rawOnlineAccountState(),
            'sepaMandateState' => $status->rawSepaMandateState(),
            'mandatePresent' => $status->mandatePresent(),
            'cesuPlusState' => $status->rawCesuPlusState(),
            'cesuPlusBlockingReason' => $status->cesuPlusBlockingReason(),
            'aiciActive' => $status->aiciActive(),
        ];
    }

    /**
     * @param array<array-key, mixed> $data the document, of shape StatusData
     *
     * @throws InvalidValue
     */
    public static function fromArray(array $data): EmployerStatus
    {
        /** @var StatusData $data */
        return new EmployerStatus(
            $data['registered'],
            $data['onlineAccountState'],
            $data['sepaMandateState'],
            $data['mandatePresent'],
            $data['cesuPlusState'],
            $data['cesuPlusBlockingReason'],
            $data['aiciActive'],
        );
    }
}
