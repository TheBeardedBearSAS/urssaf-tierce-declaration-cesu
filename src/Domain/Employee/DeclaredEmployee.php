<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;

/**
 * Employee of a declaration "InfosSalarie" (api-reference §2.7, PDF §3.7.4 / §3.7.5). The public constructor is the
 * factory (more than 5 parts).
 *
 * By Cesu number only the employment kind and the paid leave flag may accompany the reference; by NIR or by identity
 * the civility and the address are required and the administrative data are required with a NIR (see
 * {@see DeclaredEmployeeRule}). The foster carer approval "agrementAF" is held by the declaration content, not here.
 * That the Cesu number cannot be used for the first declaration of a pair is left to URSSAF: the package keeps no
 * registry of employer/employee pairs (api-reference §4.7).
 */
final readonly class DeclaredEmployee
{
    /**
     * @param bool|null $paidLeaveIncluded "congesPayes": true when the 10 % paid leave is included in the hourly wage
     *
     * @throws InvalidValue The code and message of the first violated {@see DeclaredEmployeeRule}
     */
    public function __construct(
        private EmployeeReference $reference,
        private EmploymentKind $employmentKind,
        private ?bool $paidLeaveIncluded,
        private ?Civility $civility,
        private ?Address $address,
        private ?EmailAddress $email,
        private ?PhoneNumber $phone,
    ) {
        $rules = DeclaredEmployeeRule::violatedBy($reference->kind(), [
            'identity' => !$reference instanceof EmployeeByNir || $reference->identity() instanceof EmployeeIdentity,
            'civility' => $civility instanceof Civility,
            'address' => $address instanceof Address,
            'email' => $email instanceof EmailAddress,
            'phone' => $phone instanceof PhoneNumber,
        ]);
        if ($rules !== []) {
            throw InvalidValue::because($rules[0]->code(), $rules[0]->message());
        }
    }

    public function reference(): EmployeeReference
    {
        return $this->reference;
    }

    public function employmentKind(): EmploymentKind
    {
        return $this->employmentKind;
    }

    public function paidLeaveIncluded(): ?bool
    {
        return $this->paidLeaveIncluded;
    }

    public function civility(): ?Civility
    {
        return $this->civility;
    }

    public function address(): ?Address
    {
        return $this->address;
    }

    public function email(): ?EmailAddress
    {
        return $this->email;
    }

    public function phone(): ?PhoneNumber
    {
        return $this->phone;
    }

    public function equals(self $other): bool
    {
        return $this->reference->equals($other->reference)
            && $this->employmentKind === $other->employmentKind
            && $this->paidLeaveIncluded === $other->paidLeaveIncluded
            && $this->civility === $other->civility
            && ($this->address instanceof Address ? $other->address instanceof Address && $this->address->equals($other->address) : !$other->address instanceof Address)
            && $this->email?->value() === $other->email?->value()
            && $this->phone?->value() === $other->phone?->value();
    }
}
