<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Declaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Deposit;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\HourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\PaidLeavePeriod;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\Address;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;

/**
 * Method 050 (declare a salary): builds the `InputDeclaration` body (api-reference §2.7). The path is
 * {@see ApiPaths::declare()}. Only the interface of {@see Declaration} is used: nothing branches on the declaration
 * kind. Which parts of `infosSalarie` go with which employee reference is guaranteed by `DeclaredEmployee`; this
 * mapper only drops the absent ones. Internal identifiers (tenant, local ids, external reference, fingerprint) are
 * never sent.
 */
final readonly class DeclarationRequestMapper
{
    /**
     * @return array<string, mixed>
     *
     * @throws InvariantViolation "wire.unsupported_employee_reference"
     */
    public function toBody(#[SensitiveParameter] Declaration $declaration): array
    {
        $content = $declaration->content();
        $period = $content->employmentPeriod();
        $deposit = $content->deposit();
        $fosterCarerIncome = $content->fosterCarerIncome();
        $hourlyIncome = $content->hourlyIncome();

        return array_filter(
            [
                'dateDebut' => DateEncoding::encode($period->start()),
                'dateFin' => DateEncoding::encode($period->end()),
                'dateVersement' => DateEncoding::encode($content->paymentDate()),
                'typeDeclaration' => $declaration->type()->value,
                'natureActivite' => $declaration->activityNature()?->value,
                'montantAcompte' => $deposit instanceof Deposit ? MoneyEncoding::encode($deposit->amount()) : null,
                'revenusAf' => $fosterCarerIncome instanceof FosterCarerIncome
                    ? JsonObject::of(IncomeEncoding::fosterCarer($fosterCarerIncome))
                    : null,
                'revenusEf' => $hourlyIncome instanceof HourlyIncome ? $this->hourly($hourlyIncome) : null,
                'infosSalarie' => $this->employee($content),
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    /**
     * `RevenusEf`.
     *
     * @return array<string, mixed>
     */
    private function hourly(HourlyIncome $income): array
    {
        $periods = $income->paidLeavePeriods();

        return IncomeEncoding::hourly(
            $income->hourlyWage(),
            $income->hours(),
            $income->paidLeaveHours(),
            $income->supplements(),
        ) + ($periods === [] ? [] : ['periodesCongesPayes' => array_map($this->leavePeriod(...), $periods)]);
    }

    /**
     * @return array{dateDebut: string, dateFin: string, nbJoursCP: JsonDecimal}
     */
    private function leavePeriod(PaidLeavePeriod $period): array
    {
        return [
            'dateDebut' => DateEncoding::encode($period->start()),
            'dateFin' => DateEncoding::encode($period->end()),
            'nbJoursCP' => QuantityEncoding::encodeHalfDays($period->days()),
        ];
    }

    /**
     * `InfosSalarie`.
     *
     * @return array<string, mixed>
     *
     * @throws InvariantViolation "wire.unsupported_employee_reference"
     */
    private function employee(DeclarationContent $content): array
    {
        $employee = $content->employee();
        $parts = EmployeeReferenceParts::of($employee->reference());
        $identity = $parts->identity();
        $address = $employee->address();
        $approval = $content->approval();

        return array_filter(
            [
                'numeroCesu' => $parts->cesuNumber()?->value(),
                'codeCivilite' => $employee->civility()?->value,
                'adresse' => $address instanceof Address ? AddressEncoding::encode($address) : null,
                'nomNaissance' => $identity?->birthName()->value(),
                'nomUsage' => $identity?->usageName()?->value(),
                'prenom' => $identity?->firstName()->value(),
                'dateNaissance' => $this->date($identity?->birthDate()),
                'villeNaissance' => $identity?->birthCity()?->value(),
                'departementNaissance' => $identity?->birthDepartment()?->value(),
                'codePaysNaissance' => $identity?->birthCountry()->value(),
                'email' => $employee->email()?->value(),
                'telephone' => $employee->phone()?->value(),
                'nir' => $parts->nir()?->value(),
                'typeEmploi' => $employee->employmentKind()->value,
                'congesPayes' => $employee->paidLeaveIncluded(),
                'agrementAF' => $approval instanceof FosterCarerApproval ? $this->approval($approval) : null,
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    /**
     * `AgrementAF`: the council, the number and the dates are optional, the two indicators are always sent.
     *
     * @return array<string, mixed>
     */
    private function approval(FosterCarerApproval $approval): array
    {
        return array_filter(
            [
                'codeConseilDepartemental' => $approval->council()?->value(),
                'numeroAgrement' => $approval->number()?->value(),
                'dateDebutAgrement' => $this->date($approval->startDate()),
                'dateFinAgrement' => $this->date($approval->endDate()),
                'indCertificationValiditeAgrement' => $approval->isCertifiedValid(),
                'indRemplacementPonctuel' => $approval->isOccasionalReplacement(),
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    private function date(?CalendarDate $date): ?string
    {
        return $date instanceof CalendarDate ? DateEncoding::encode($date) : null;
    }
}
