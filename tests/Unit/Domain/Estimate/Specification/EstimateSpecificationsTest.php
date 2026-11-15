<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Estimate\Specification;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SimulatedFosterCarerIncome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification\EstimateIncomeIsPresent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification\EstimateRules;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\Specification\EstimateWorkedHours;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Severity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Estimate\EstimateSamples;

final class EstimateSpecificationsTest extends TestCase
{
    public function testMissingIncomeIsAWarningAtTheRoot(): void
    {
        $result = (new EstimateIncomeIsPresent())->validate(EstimateRequest::of(EmploymentKind::HomeEmployment, null, null));

        static::assertSame(['warning  estimate_request.income_missing'], DomainAssertions::summary($result));
        static::assertTrue($result->isValid());
        static::assertSame(Severity::Warning, $result->all()[0]->severity());
        static::assertSame('No income is given: URSSAF requires hours or an income element.', $result->all()[0]->message());
    }

    public function testAnIncomeIsEnough(): void
    {
        $specification = new EstimateIncomeIsPresent();
        $hourly = EstimateRequest::of(EmploymentKind::HomeEmployment, EstimateSamples::hourlyIncome(), null);
        $foster = EstimateRequest::of(EmploymentKind::FosterCarer, null, SimulatedFosterCarerIncome::of(DeclarationSamples::fosterIncome(), GeographicZone::Metropolitan));

        static::assertSame([], DomainAssertions::summary($specification->validate($hourly)));
        static::assertSame([], DomainAssertions::summary($specification->validate($foster)));
    }

    public function testWorkedHoursRulesAreNestedUnderTheHourlyIncome(): void
    {
        $specification = new EstimateWorkedHours();

        static::assertSame([], DomainAssertions::summary($specification->validate(EstimateRequest::of(EmploymentKind::HomeEmployment, null, null))));
        static::assertSame([], DomainAssertions::summary($specification->validate(EstimateRequest::of(EmploymentKind::HomeEmployment, EstimateSamples::hourlyIncome(40, 8, 1), null))));
    }

    public function testIntrinsicRulesRunInOrder(): void
    {
        $rules = EstimateRules::intrinsic();

        static::assertSame(['warning  estimate_request.income_missing'], DomainAssertions::summary($rules->validate(EstimateRequest::of(EmploymentKind::HomeEmployment, null, null))));
        static::assertSame([], DomainAssertions::summary($rules->validate(EstimateRequest::of(EmploymentKind::HomeEmployment, EstimateSamples::hourlyIncome(), null))));
    }
}
