<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input;

/**
 * Raw registration of an employer (method 010, api-reference §2.1). An empty string counts as missing. Personal data
 * (ADR 0007).
 */
final readonly class NewEmployerDraft
{
    public function __construct(
        public ?string $civility,
        public ?string $lastName,
        public ?string $firstName,
        public ?string $birthDate,
        public ?string $email,
        public ?string $phone,
        public ?AddressDraft $address,
        public ?BankDetailsDraft $bankDetails,
    ) {}
}
