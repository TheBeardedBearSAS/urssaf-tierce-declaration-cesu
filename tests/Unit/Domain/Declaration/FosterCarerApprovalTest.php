<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DepartmentalCouncil;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class FosterCarerApprovalTest extends TestCase
{
    public function testACertifiedApprovalKeepsItsParts(): void
    {
        $number = ApprovalNumber::fromString('12345678910111213145');
        $approval = FosterCarerApproval::of(
            ApprovalBasis::CertifiedApproval,
            DepartmentalCouncil::fromString('001'),
            $number,
            CalendarDate::fromString('2019-01-01'),
            CalendarDate::fromString('2021-01-30'),
        );

        static::assertSame(ApprovalBasis::CertifiedApproval, $approval->basis());
        static::assertSame('001', $approval->council()?->value());
        static::assertSame($number, $approval->number());
        static::assertSame('2019-01-01', $approval->startDate()?->toString());
        static::assertSame('2021-01-30', $approval->endDate()?->toString());
        static::assertTrue($approval->isCertifiedValid());
        static::assertFalse($approval->isOccasionalReplacement());
    }

    public function testNoStartBeforeEndRuleIsPublished(): void
    {
        $approval = FosterCarerApproval::of(
            ApprovalBasis::CertifiedApproval,
            DepartmentalCouncil::fromString('001'),
            null,
            CalendarDate::fromString('2021-01-30'),
            CalendarDate::fromString('2019-01-01'),
        );

        static::assertTrue($approval->isCertifiedValid());
    }

    public function testAnOccasionalReplacementNeedsNothingElse(): void
    {
        $approval = FosterCarerApproval::of(ApprovalBasis::OccasionalReplacement, null, null, null, null);

        static::assertSame(ApprovalBasis::OccasionalReplacement, $approval->basis());
        static::assertNull($approval->council());
        static::assertNull($approval->number());
        static::assertNull($approval->startDate());
        static::assertNull($approval->endDate());
        static::assertFalse($approval->isCertifiedValid());
        static::assertTrue($approval->isOccasionalReplacement());
    }

    public function testACertifiedApprovalRequiresTheCouncilFirst(): void
    {
        $exception = DomainAssertions::assertViolationCode(
            FosterCarerApproval::COUNCIL_REQUIRED,
            static fn(): FosterCarerApproval => FosterCarerApproval::of(ApprovalBasis::CertifiedApproval, null, null, null, null),
        );

        static::assertSame('foster_carer_approval.council_required', $exception->violationCode());
        static::assertSame('The departmental council is required with a certified approval.', $exception->getMessage());
    }

    public function testACertifiedApprovalRequiresTheStartDateBeforeTheEndDate(): void
    {
        $council = DepartmentalCouncil::fromString('001');
        $exception = DomainAssertions::assertViolationCode(
            FosterCarerApproval::START_DATE_REQUIRED,
            static fn(): FosterCarerApproval => FosterCarerApproval::of(ApprovalBasis::CertifiedApproval, $council, null, null, null),
        );

        static::assertSame('foster_carer_approval.start_date_required', $exception->violationCode());
        static::assertSame('The approval start date is required with a certified approval.', $exception->getMessage());
    }

    public function testACertifiedApprovalRequiresTheEndDate(): void
    {
        $council = DepartmentalCouncil::fromString('001');
        $start = CalendarDate::fromString('2019-01-01');
        $exception = DomainAssertions::assertViolationCode(
            FosterCarerApproval::END_DATE_REQUIRED,
            static fn(): FosterCarerApproval => FosterCarerApproval::of(ApprovalBasis::CertifiedApproval, $council, null, $start, null),
        );

        static::assertSame('foster_carer_approval.end_date_required', $exception->violationCode());
        static::assertSame('The approval end date is required with a certified approval.', $exception->getMessage());
        static::assertInstanceOf(InvalidValue::class, $exception);
    }
}
