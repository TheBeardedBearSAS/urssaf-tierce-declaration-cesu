<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\PersonName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * Employee identified by the administrative data alone (api-reference §2.2, §2.3, §2.7): the birth date and the birth
 * city are required without a Cesu number and a NIR. Personal data.
 */
final readonly class EmployeeByIdentity implements EmployeeReference
{
    private function __construct(private EmployeeIdentity $identity) {}

    /**
     * @throws InvalidValue "employee_identity.birth_date_required", "employee_identity.birth_city_required"
     */
    public static function of(EmployeeIdentity $identity): self
    {
        if (!$identity->birthDate() instanceof CalendarDate) {
            throw InvalidValue::because(EmployeeIdentity::BIRTH_DATE_REQUIRED, EmployeeIdentity::BIRTH_DATE_REQUIRED_MESSAGE);
        }
        if (!$identity->birthCity() instanceof PersonName) {
            throw InvalidValue::because(EmployeeIdentity::BIRTH_CITY_REQUIRED, EmployeeIdentity::BIRTH_CITY_REQUIRED_MESSAGE);
        }

        return new self($identity);
    }

    public function kind(): EmployeeReferenceKind
    {
        return EmployeeReferenceKind::Identity;
    }

    public function cesuNumber(): ?CesuNumber
    {
        return null;
    }

    public function identity(): EmployeeIdentity
    {
        return $this->identity;
    }

    public function equals(EmployeeReference $other): bool
    {
        return $other instanceof self && $this->identity->equals($other->identity);
    }
}
