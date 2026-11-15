<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployeeRule;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressValidator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\EmailAddress;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\PhoneNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Validator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Turns a {@see DeclaredEmployeeDraft} into a {@see DeclaredEmployee}, reporting every violation (notification pattern,
 * ADR 0000). Paths follow the draft ("cesuNumber", "identity.birthName", "address.postalCode"...).
 *
 * The civility, address, e-mail and phone are not parsed for an employee identified by Cesu number: they are only
 * reported as forbidden ({@see DeclaredEmployeeRule}).
 *
 * @implements Validator<DeclaredEmployeeDraft>
 *
 * @internal
 */
final readonly class DeclaredEmployeeParser implements Validator
{
    public function validate(object $subject): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($subject, $collector);

        return $collector->result();
    }

    /**
     * Returns the employee, or null after reporting the violations to the collector.
     */
    public function parse(DeclaredEmployeeDraft $draft, ViolationCollector $collector): ?DeclaredEmployee
    {
        $errorsBefore = $collector->errorCount();
        $reference = EmployeeReferenceFactory::forDeclaration()->parse(
            new EmployeeReferenceDraft($draft->cesuNumber, $draft->nir, $draft->identity),
            $collector,
        );
        $employmentKind = $collector->required('employmentKind', 'employment_kind', $draft->employmentKind, EmploymentKind::fromCode(...));
        $kind = self::rawKind($draft);
        $civility = null;
        $address = null;
        $email = null;
        $phone = null;
        if ($kind !== EmployeeReferenceKind::CesuNumber) {
            $civility = $collector->optional('civility', $draft->civility, Civility::fromCode(...));
            $address = $this->parseAddress($draft->address, $collector);
            $email = $collector->optional('email', $draft->email, EmailAddress::fromString(...));
            $phone = $collector->optional('phone', $draft->phone, PhoneNumber::fromString(...));
        }
        foreach (DeclaredEmployeeRule::violatedBy($kind, [
            'identity' => $draft->identity instanceof EmployeeIdentityDraft && $draft->identity->isFilled(),
            'civility' => self::filled($draft->civility),
            'address' => $draft->address instanceof AddressDraft,
            'email' => self::filled($draft->email),
            'phone' => self::filled($draft->phone),
        ]) as $rule) {
            $collector->add(Violation::error($rule->path(), $rule->code(), $rule->message()));
        }
        if ($collector->errorCount() > $errorsBefore) {
            return null;
        }
        // No error was reported: the reference and the employment kind were parsed.
        /** @var \TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference $reference */
        /** @var EmploymentKind $employmentKind */
        return new DeclaredEmployee($reference, $employmentKind, $draft->paidLeaveIncluded, $civility, $address, $email, $phone);
    }

    private function parseAddress(?AddressDraft $draft, ViolationCollector $collector): ?Address
    {
        if (!$draft instanceof AddressDraft) {
            return null;
        }
        $addressCollector = new ViolationCollector();
        $address = (new AddressValidator())->parse($draft, $addressCollector);
        $collector->merge($addressCollector->result(), 'address');

        return $address;
    }

    private static function rawKind(DeclaredEmployeeDraft $draft): EmployeeReferenceKind
    {
        if (self::filled($draft->cesuNumber)) {
            return EmployeeReferenceKind::CesuNumber;
        }

        return self::filled($draft->nir) ? EmployeeReferenceKind::Nir : EmployeeReferenceKind::Identity;
    }

    private static function filled(?string $raw): bool
    {
        return $raw !== null && $raw !== '';
    }
}
