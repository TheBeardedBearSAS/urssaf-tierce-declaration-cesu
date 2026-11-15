<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\FosterCarerApproval;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerApprovalDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerApprovalParser;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class FosterCarerApprovalParserTest extends TestCase
{
    /**
     * @return array{FosterCarerApproval|null, ValidationResult}
     */
    private static function parse(?FosterCarerApprovalDraft $draft): array
    {
        $collector = new ViolationCollector();
        $approval = (new FosterCarerApprovalParser())->parse($draft, $collector);

        return [$approval, $collector->result()];
    }

    public function testNoDraftIsNoApproval(): void
    {
        [$approval, $result] = self::parse(null);

        static::assertNull($approval);
        static::assertSame([], $result->all());
    }

    public function testACertifiedApprovalIsParsed(): void
    {
        [$approval, $result] = self::parse(DraftFixtures::approval());

        static::assertSame([], $result->all());
        static::assertNotNull($approval);
        static::assertSame(ApprovalBasis::CertifiedApproval, $approval->basis());
        static::assertSame('001', $approval->council()?->value());
        static::assertSame('12345678910111213145', $approval->number()?->value());
        static::assertSame('2019-01-01', $approval->startDate()?->toString());
        static::assertSame('2030-01-30', $approval->endDate()?->toString());
    }

    public function testAnOccasionalReplacementNeedsNothingElse(): void
    {
        [$approval, $result] = self::parse(new FosterCarerApprovalDraft(null, null, null, null, false, true));

        static::assertSame([], $result->all());
        static::assertNotNull($approval);
        static::assertSame(ApprovalBasis::OccasionalReplacement, $approval->basis());
        static::assertNull($approval->council());
    }

    public function testAnUnsetIndicatorCountsAsFalse(): void
    {
        [$approval] = self::parse(new FosterCarerApprovalDraft(null, null, null, null, null, true));
        [$certified] = self::parse(new FosterCarerApprovalDraft('001', null, '2019-01-01', '2020-01-01', true, null));

        static::assertSame(ApprovalBasis::OccasionalReplacement, $approval?->basis());
        static::assertSame(ApprovalBasis::CertifiedApproval, $certified?->basis());
    }

    /**
     * @return iterable<string, array{FosterCarerApprovalDraft, list<string>}>
     */
    public static function provideInvalidDrafts(): iterable
    {
        yield 'both indicators' => [new FosterCarerApprovalDraft('001', null, '2019-01-01', '2020-01-01', true, true), ['error  foster_carer_approval.indicators_exclusive']];
        yield 'no indicator' => [new FosterCarerApprovalDraft('001', null, '2019-01-01', '2020-01-01', false, false), ['error  foster_carer_approval.indicator_required']];
        yield 'unset indicators' => [new FosterCarerApprovalDraft(null, null, null, null, null, null), ['error  foster_carer_approval.indicator_required']];
        yield 'certified without anything' => [
            new FosterCarerApprovalDraft(null, null, null, null, true, false),
            [
                'error councilCode foster_carer_approval.council_required',
                'error startDate foster_carer_approval.start_date_required',
                'error endDate foster_carer_approval.end_date_required',
            ],
        ];
        yield 'certified with blank strings' => [
            new FosterCarerApprovalDraft('', null, '', '', true, false),
            [
                'error councilCode foster_carer_approval.council_required',
                'error startDate foster_carer_approval.start_date_required',
                'error endDate foster_carer_approval.end_date_required',
            ],
        ];
        yield 'certified without the start date' => [new FosterCarerApprovalDraft('001', null, null, '2020-01-01', true, false), ['error startDate foster_carer_approval.start_date_required']];
        yield 'certified without the end date' => [new FosterCarerApprovalDraft('001', null, '2019-01-01', null, true, false), ['error endDate foster_carer_approval.end_date_required']];
        yield 'invalid values' => [
            new FosterCarerApprovalDraft('020', str_repeat('x', 21), '2019-02-30', 'x', false, true),
            [
                'error councilCode departmental_council.unknown',
                'error approvalNumber approval_number.too_long',
                'error startDate calendar_date.invalid_date',
                'error endDate calendar_date.invalid_format',
            ],
        ];
        yield 'invalid values are not reported as missing' => [
            new FosterCarerApprovalDraft('020', null, 'x', 'y', true, false),
            [
                'error councilCode departmental_council.unknown',
                'error startDate calendar_date.invalid_format',
                'error endDate calendar_date.invalid_format',
            ],
        ];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidDrafts')]
    public function testInvalidDraftsReportEveryViolationAndYieldNothing(FosterCarerApprovalDraft $draft, array $expected): void
    {
        [$approval, $result] = self::parse($draft);

        static::assertNull($approval);
        static::assertSame($expected, DomainAssertions::summary($result));
    }

    public function testMessages(): void
    {
        [, $both] = self::parse(new FosterCarerApprovalDraft(null, null, null, null, true, true));
        [, $none] = self::parse(new FosterCarerApprovalDraft(null, null, null, null, false, false));
        [, $missing] = self::parse(new FosterCarerApprovalDraft(null, null, null, null, true, false));

        static::assertSame('The certified approval and the occasional replacement indicators must not both be true.', $both->all()[0]->message());
        static::assertSame('One of the certified approval and occasional replacement indicators must be true.', $none->all()[0]->message());
        static::assertSame(FosterCarerApproval::COUNCIL_REQUIRED_MESSAGE, $missing->all()[0]->message());
        static::assertSame(FosterCarerApproval::START_DATE_REQUIRED_MESSAGE, $missing->all()[1]->message());
        static::assertSame(FosterCarerApproval::END_DATE_REQUIRED_MESSAGE, $missing->all()[2]->message());
    }
}
