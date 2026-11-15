<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Specification;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\ApprovalRequiredForFosterCarer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\EmploymentNotStartedAfterToday;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\FosterCarerDeclarationsAllowed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Specification\NatureForbiddenForFosterCarer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class FosterCarerAndDateSpecificationsTest extends TestCase
{
    public function testNatureIsForbiddenForAFosterCarer(): void
    {
        $content = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, ActivityNature::Gardening, null);
        $result = (new NatureForbiddenForFosterCarer())->validate($content);

        static::assertSame(['error activityNature declaration.nature_forbidden_for_foster_carer'], DomainAssertions::summary($result));
        static::assertSame('The activity nature must not be given for a foster carer.', $result->all()[0]->message());
    }

    public function testNatureIsFineWithoutOneOrForAHomeEmployment(): void
    {
        $specification = new NatureForbiddenForFosterCarer();

        static::assertSame([], DomainAssertions::summary($specification->validate(DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null))));
        static::assertSame([], DomainAssertions::summary($specification->validate(DeclarationSamples::build(null, null, null, ActivityNature::Gardening, null))));
        static::assertSame([], DomainAssertions::summary($specification->validate(DeclarationSamples::content())));
    }

    public function testApprovalIsRequiredForAFosterCarer(): void
    {
        $content = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null);
        $result = (new ApprovalRequiredForFosterCarer())->validate($content);

        static::assertSame(['error approval declaration.approval_required'], DomainAssertions::summary($result));
        static::assertSame('The approval is required for a foster carer.', $result->all()[0]->message());
    }

    public function testApprovalIsFineWhenGivenOrForAHomeEmployment(): void
    {
        $specification = new ApprovalRequiredForFosterCarer();
        $withApproval = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, DeclarationSamples::approval());

        static::assertSame([], DomainAssertions::summary($specification->validate($withApproval)));
        static::assertSame([], DomainAssertions::summary($specification->validate(DeclarationSamples::content())));
        static::assertSame([], DomainAssertions::summary($specification->validate(DeclarationSamples::build(null, null, null, null, DeclarationSamples::approval()))));
    }

    public function testTheEmploymentMustNotStartAfterToday(): void
    {
        $specification = new EmploymentNotStartedAfterToday(DeclarationSamples::date('2026-10-05'));

        $started = DeclarationSamples::build(null, null, DeclarationSamples::period('2026-10-04', '2026-10-31'), null, null);
        $startsToday = DeclarationSamples::build(null, null, DeclarationSamples::period('2026-10-05', '2026-10-31'), null, null);
        $startsTomorrow = DeclarationSamples::build(null, null, DeclarationSamples::period('2026-10-06', '2026-10-31'), null, null);

        static::assertSame([], DomainAssertions::summary($specification->validate($started)));
        static::assertSame([], DomainAssertions::summary($specification->validate($startsToday)));
        $result = $specification->validate($startsTomorrow);
        static::assertSame(['error employmentPeriod.startDate employment_period.starts_after_today'], DomainAssertions::summary($result));
        static::assertSame('The employment period must not start after today.', $result->all()[0]->message());
    }

    public function testAnEndInTheFutureIsNotChecked(): void
    {
        $specification = new EmploymentNotStartedAfterToday(DeclarationSamples::date('2026-10-05'));

        static::assertSame([], DomainAssertions::summary($specification->validate(DeclarationSamples::content())));
    }

    public function testFosterCarerDeclarationsMustBeAllowed(): void
    {
        $foster = DeclarationSamples::build(null, DeclarationSamples::employee(EmploymentKind::FosterCarer, false), null, null, null);
        $home = DeclarationSamples::content();

        static::assertSame([], DomainAssertions::summary((new FosterCarerDeclarationsAllowed(true))->validate($foster)));
        static::assertSame([], DomainAssertions::summary((new FosterCarerDeclarationsAllowed(true))->validate($home)));
        static::assertSame([], DomainAssertions::summary((new FosterCarerDeclarationsAllowed(false))->validate($home)));
        $result = (new FosterCarerDeclarationsAllowed(false))->validate($foster);
        static::assertSame(['error employee.employmentKind declaration.foster_carer_unsupported'], DomainAssertions::summary($result));
        static::assertSame('Foster carer declarations are not supported by this workflow.', $result->all()[0]->message());
    }
}
