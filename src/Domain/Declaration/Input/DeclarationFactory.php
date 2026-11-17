<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input;

use Closure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\CesuPlusDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Deposit;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\EmploymentPeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\DeclarationRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;

/**
 * Turns a {@see DeclarationDraft} into a {@see Declaration}, reporting every violation (notification pattern, ADR
 * 0000). Paths follow the draft ("employmentPeriod.endDate", "hourlyIncome.supplements[1].amount",
 * "employee.address.postalCode"). A missing employment period or employee is parsed as an all-missing one.
 *
 * The cross-field rules ({@see DeclarationRules}) are only evaluated once every part was built, so that they never
 * report on an incomplete draft. Warnings are part of {@see self::validate()} but never block {@see self::fromDraft()}.
 */
final readonly class DeclarationFactory
{
    public function validate(DeclarationDraft $draft, DeclarationContext $context): ValidationResult
    {
        $collector = new ViolationCollector();
        $this->parse($draft, $context, $collector);

        return $collector->result();
    }

    /**
     * @throws ValidationFailed with every violation
     */
    public function fromDraft(DeclarationDraft $draft, DeclarationContext $context): Declaration
    {
        $collector = new ViolationCollector();

        return $this->parse($draft, $context, $collector) ?? throw ValidationFailed::withResult($collector->result());
    }

    private function parse(DeclarationDraft $draft, DeclarationContext $context, ViolationCollector $collector): ?Declaration
    {
        $employerCesuNumber = $collector->required(
            'employerCesuNumber',
            'employer_cesu_number',
            $draft->employerCesuNumber,
            CesuNumber::fromString(...),
        );
        $type = $this->parseType($draft->type, $collector);
        $period = $this->nested(
            'employmentPeriod',
            $collector,
            fn(ViolationCollector $nested): ?EmploymentPeriod => $this->parsePeriod(
                $draft->employmentPeriod ?? new EmploymentPeriodDraft(null, null),
                $nested,
            ),
        );
        $paymentDate = $collector->required('paymentDate', 'payment_date', $draft->paymentDate, CalendarDate::fromString(...));
        $nature = $collector->optional('activityNature', $draft->activityNature, ActivityNature::fromCode(...));
        $deposit = $collector->optional(
            'deposit',
            $draft->deposit,
            static fn(string $raw): Deposit => Deposit::fromMoney(Money::fromDecimalString($raw)),
        );
        $hourlyIncome = $this->nested(
            'hourlyIncome',
            $collector,
            static fn(ViolationCollector $nested): ?HourlyIncome => (new HourlyIncomeParser())->parse($draft->hourlyIncome, $nested),
        );
        $fosterCarerIncome = $this->nested(
            'fosterCarerIncome',
            $collector,
            static fn(ViolationCollector $nested): ?FosterCarerIncome => (new FosterCarerIncomeParser())->parse($draft->fosterCarerIncome, $nested),
        );
        $approval = $this->nested(
            'approval',
            $collector,
            static fn(ViolationCollector $nested): ?FosterCarerApproval => (new FosterCarerApprovalParser())->parse($draft->approval, $nested),
        );
        $employee = $this->nested(
            'employee',
            $collector,
            static fn(ViolationCollector $nested): ?DeclaredEmployee => (new DeclaredEmployeeParser())->parse(
                $draft->employee ?? new DeclaredEmployeeDraft(null, null, null, null, null, null, null, null, null),
                $nested,
            ),
        );
        if ($collector->errorCount() > 0) {
            return null;
        }
        // Every required part reported a violation when it is missing, so none of them is null from here.
        /** @var CesuNumber $employerCesuNumber */
        /** @var DeclarationType $type */
        /** @var EmploymentPeriod $period */
        /** @var CalendarDate $paymentDate */
        /** @var DeclaredEmployee $employee */
        $content = new DeclarationContent(
            $employerCesuNumber,
            $period,
            $paymentDate,
            $employee,
            $hourlyIncome,
            $fosterCarerIncome,
            $deposit,
            $approval,
            $nature,
        );
        $this->checkContent($content, $type, $context, $collector);
        if ($collector->errorCount() > 0) {
            return null;
        }

        return match ($type) {
            DeclarationType::Standard => StandardDeclaration::of($content),
            DeclarationType::CesuPlus => CesuPlusDeclaration::of($content),
        };
    }

    private function parseType(?int $raw, ViolationCollector $collector): ?DeclarationType
    {
        if ($raw === null) {
            return DeclarationType::Standard;
        }

        return $collector->attempt('type', static fn(): DeclarationType => DeclarationType::fromCode($raw));
    }

    /**
     * Runs a parser on its own collector and merges its violations under the path of the nested draft.
     *
     * @template T of object
     *
     * @param Closure(ViolationCollector): ?T $parse
     *
     * @param-immediately-invoked-callable $parse
     *
     * @return T|null
     */
    private function nested(string $path, ViolationCollector $collector, Closure $parse): ?object
    {
        $nested = new ViolationCollector();
        $value = $parse($nested);
        $collector->merge($nested->result(), $path);

        return $value;
    }

    private function parsePeriod(EmploymentPeriodDraft $draft, ViolationCollector $collector): ?EmploymentPeriod
    {
        $start = $collector->required('startDate', 'start_date', $draft->startDate, CalendarDate::fromString(...));
        $end = $collector->required('endDate', 'end_date', $draft->endDate, CalendarDate::fromString(...));
        if ($start === null || $end === null) {
            return null;
        }

        return $collector->attempt('endDate', static fn(): EmploymentPeriod => EmploymentPeriod::between($start, $end));
    }

    private function checkContent(
        DeclarationContent $content,
        DeclarationType $type,
        DeclarationContext $context,
        ViolationCollector $collector,
    ): void {
        $collector->merge(DeclarationRules::intrinsic()->validate($content), '');
        $collector->merge(
            DeclarationRules::contextual($context->today(), $context->allowFosterCarerDeclarations())->validate($content),
            '',
        );
        if ($type === DeclarationType::CesuPlus && !$content->activityNature() instanceof ActivityNature) {
            $collector->add(Violation::error(
                'activityNature',
                'activity_nature.required',
                'A Cesu+ declaration requires an activity nature.',
            ));
        }
    }
}
