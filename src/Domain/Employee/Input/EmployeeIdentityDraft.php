<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input;

/**
 * Raw administrative data of an employee (api-reference §2.2, §2.3, §2.7). An empty string counts as missing.
 * Personal data (ADR 0007).
 */
final readonly class EmployeeIdentityDraft
{
    public function __construct(
        public ?string $birthName,
        public ?string $usageName,
        public ?string $firstName,
        public ?string $birthDate,
        public ?string $birthCity,
        public ?string $birthDepartment,
        public ?string $birthCountry,
    ) {}

    /**
     * Whether at least one field is filled in.
     */
    public function isFilled(): bool
    {
        foreach ([$this->birthName, $this->usageName, $this->firstName, $this->birthDate, $this->birthCity, $this->birthDepartment, $this->birthCountry] as $raw) {
            if ($raw !== null && $raw !== '') {
                return true;
            }
        }

        return false;
    }
}
