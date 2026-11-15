<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Estimate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\WorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedFosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedHourlyIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Money;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\WireFixture;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

/**
 * One method per scenario of the estimate values.
 *
 * @mago-expect lint:too-many-methods
 */
final class EstimateRequestTest extends TestCase
{
    public function testOfficialExample(): void
    {
        $body = WireFixture::load('estimate_request.json');
        $income = WireFixture::map($body['revenusEf']);
        $request = EstimateRequest::of(
            EmploymentKind::fromCode(WireFixture::string($body['typeEmploi'])),
            new SimulatedHourlyIncome(
                Money::fromDecimalString(WireFixture::number($income['salaireHoraire'])),
                WorkedHours::of(
                    WireFixture::int($income['heuresTravaillees']),
                    WireFixture::int($income['nbHeureSupplementaire1']),
                    WireFixture::int($income['nbHeureSupplementaire2']),
                ),
                WireFixture::int($income['heuresCp']),
                [],
                SalaryType::fromCode(WireFixture::string($income['typeSalaire'])),
                ExemptionCode::fromCode(WireFixture::string($income['codeExoneration'])),
                GeographicZone::fromCode(WireFixture::string($income['codeZoneGeographique'])),
                WireFixture::bool($income['congesPayes']),
            ),
            null,
        );
        $hourlyIncome = $request->hourlyIncome();

        static::assertSame(EmploymentKind::HomeEmployment, $request->employmentKind());
        static::assertNotNull($hourlyIncome);
        static::assertSame(1000, $hourlyIncome->hourlyWage()->cents());
        static::assertSame(12, $hourlyIncome->hours()->workedHours());
        static::assertSame(0, $hourlyIncome->hours()->firstTierOvertimeHours());
        static::assertSame(0, $hourlyIncome->hours()->secondTierOvertimeHours());
        static::assertSame(0, $hourlyIncome->paidLeaveHours());
        static::assertSame(SalaryType::Net, $hourlyIncome->salaryType());
        static::assertSame(ExemptionCode::NoExemption, $hourlyIncome->exemptionCode());
        static::assertSame(GeographicZone::Metropolitan, $hourlyIncome->geographicZone());
        static::assertTrue($hourlyIncome->paidLeaveIncluded());
        static::assertNull($request->fosterCarerIncome());
    }

    public function testASimulatedHourlyIncomeKeepsItsParts(): void
    {
        $supplement = EstimateSamples::supplement();
        $hours = WorkedHours::of(50, 5, 8);
        $income = new SimulatedHourlyIncome(
            Money::fromCents(99_999),
            $hours,
            200,
            [$supplement],
            SalaryType::Gross,
            ExemptionCode::Aeeh,
            GeographicZone::AlsaceMoselle,
            false,
        );

        static::assertSame(99_999, $income->hourlyWage()->cents());
        static::assertSame($hours, $income->hours());
        static::assertSame(200, $income->paidLeaveHours());
        static::assertSame([$supplement], $income->supplements());
        static::assertSame(SalaryType::Gross, $income->salaryType());
        static::assertSame(ExemptionCode::Aeeh, $income->exemptionCode());
        static::assertSame(GeographicZone::AlsaceMoselle, $income->geographicZone());
        static::assertFalse($income->paidLeaveIncluded());
    }

    private static function withWage(int $cents): SimulatedHourlyIncome
    {
        return new SimulatedHourlyIncome(Money::fromCents($cents), WorkedHours::of(1, null, null), null, [], SalaryType::Net, ExemptionCode::NoExemption, GeographicZone::Metropolitan, false);
    }

    private static function withLeaveHours(int $hours): SimulatedHourlyIncome
    {
        return new SimulatedHourlyIncome(Money::fromCents(1), WorkedHours::of(1, null, null), $hours, [], SalaryType::Net, ExemptionCode::NoExemption, GeographicZone::Metropolitan, false);
    }

    public function testThePaidLeaveHoursMayBeOmittedOrZeroWithTheFlagSet(): void
    {
        $omitted = new SimulatedHourlyIncome(Money::fromCents(1), WorkedHours::of(1, null, null), null, [], SalaryType::Net, ExemptionCode::NoExemption, GeographicZone::Metropolitan, true);

        static::assertNull($omitted->paidLeaveHours());
        static::assertTrue($omitted->paidLeaveIncluded());
    }

    public function testTheHourlyWageIsAtMost999Point99(): void
    {
        static::assertSame(99_999, self::withWage(99_999)->hourlyWage()->cents());
        $exception = DomainAssertions::assertViolationCode('hourly_wage.out_of_range', static fn(): SimulatedHourlyIncome => self::withWage(100_000));
        static::assertSame(['min' => '0.00', 'max' => '999.99'], $exception->params());
    }

    /**
     * @return iterable<string, array{int, string|null}>
     */
    public static function providePaidLeaveHours(): iterable
    {
        yield 'below' => [-1, 'paid_leave_hours.out_of_range'];
        yield 'zero' => [0, null];
        yield 'maximum' => [200, null];
        yield 'above' => [201, 'paid_leave_hours.out_of_range'];
    }

    #[DataProvider('providePaidLeaveHours')]
    public function testPaidLeaveHoursBounds(int $hours, ?string $violationCode): void
    {
        if ($violationCode === null) {
            static::assertSame($hours, self::withLeaveHours($hours)->paidLeaveHours());

            return;
        }
        $exception = DomainAssertions::assertViolationCode($violationCode, static fn(): SimulatedHourlyIncome => self::withLeaveHours($hours));

        static::assertSame(['min' => 0, 'max' => 200], $exception->params());
    }

    public function testASimulatedFosterCarerIncomeKeepsItsParts(): void
    {
        $income = DeclarationSamples::fosterIncome();
        $simulated = SimulatedFosterCarerIncome::of($income, GeographicZone::Dom);

        static::assertSame($income, $simulated->income());
        static::assertSame(GeographicZone::Dom, $simulated->geographicZone());
        $request = EstimateRequest::of(EmploymentKind::FosterCarer, null, $simulated);
        static::assertSame(EmploymentKind::FosterCarer, $request->employmentKind());
        static::assertNull($request->hourlyIncome());
        static::assertSame($simulated, $request->fosterCarerIncome());
    }

    public function testNoLocalRulePairsAnIncomeWithAnEmploymentKind(): void
    {
        $request = EstimateRequest::of(EmploymentKind::FosterCarer, EstimateSamples::hourlyIncome(), SimulatedFosterCarerIncome::of(DeclarationSamples::fosterIncome(), GeographicZone::Metropolitan));

        static::assertNotNull($request->hourlyIncome());
        static::assertNotNull($request->fosterCarerIncome());
    }

    public function testAnEstimateWithoutIncomeIsAccepted(): void
    {
        static::assertNull(EstimateRequest::of(EmploymentKind::HomeEmployment, null, null)->hourlyIncome());
    }

    public function testOvertimeRulesAreEnforced(): void
    {
        try {
            EstimateRequest::of(EmploymentKind::HomeEmployment, EstimateSamples::hourlyIncome(39, 1, 1), null);
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failed) {
            static::assertSame(
                [
                    'error hourlyIncome.workedHours worked_hours.overtime_requires_forty_normal_hours',
                    'error hourlyIncome.secondTierOvertimeHours worked_hours.second_tier_requires_eight_first_tier_hours',
                ],
                DomainAssertions::summary($failed->result()),
            );
        }
        static::assertNotNull(EstimateRequest::of(EmploymentKind::HomeEmployment, EstimateSamples::hourlyIncome(40, 8, 1), null)->hourlyIncome());
    }

    public function testContributionsEstimateKeepsTheContributions(): void
    {
        $contributions = Contributions::of(Money::fromCents(6310), Money::fromCents(2009));
        $estimate = ContributionsEstimate::of($contributions);

        static::assertSame($contributions, $estimate->contributions());
        static::assertSame(6310, $estimate->employerContributions()->cents());
        static::assertSame(2009, $estimate->employeeContributions()->cents());
    }
}
