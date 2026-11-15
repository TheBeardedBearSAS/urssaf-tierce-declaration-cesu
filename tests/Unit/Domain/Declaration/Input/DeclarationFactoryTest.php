<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ApprovalBasis;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\CesuPlusDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationContext;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\EmploymentPeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerApprovalDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\FosterCarerIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\HourlyIncomeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\PaidLeavePeriodDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\SalarySupplementDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\DeclaredEmployeeDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeIdentityDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\AddressDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Severity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\WireFixture;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

/**
 * One method per scenario of the factory.
 *
 * @mago-expect lint:too-many-methods
 */
final class DeclarationFactoryTest extends TestCase
{
    public function testACompleteDraftBuildsAStandardDeclaration(): void
    {
        $declaration = (new DeclarationFactory())->fromDraft(DraftFixtures::valid(), DraftFixtures::context());

        static::assertInstanceOf(StandardDeclaration::class, $declaration);
        static::assertSame(DeclarationType::Standard, $declaration->type());
        static::assertNull($declaration->activityNature());
        $content = $declaration->content();
        static::assertSame(DeclarationSamples::EMPLOYER_CESU_NUMBER, $content->employerCesuNumber()->value());
        static::assertSame('2026-10-01', $content->employmentPeriod()->start()->toString());
        static::assertSame('2026-10-31', $content->employmentPeriod()->end()->toString());
        static::assertSame('2026-10-31', $content->paymentDate()->toString());
        static::assertSame(1000, $content->deposit()?->amount()->cents());
        static::assertNull($content->fosterCarerIncome());
        static::assertNull($content->approval());
        static::assertSame(EmploymentKind::HomeEmployment, $content->employee()->employmentKind());
        static::assertSame(DeclarationSamples::EMPLOYEE_CESU_NUMBER, $content->employee()->reference()->cesuNumber()?->value());
        $income = $content->hourlyIncome();
        static::assertNotNull($income);
        static::assertSame(1250, $income->hourlyWage()->cents());
        static::assertSame([100, 8, 9], [$income->hours()->workedHours(), $income->hours()->firstTierOvertimeHours(), $income->hours()->secondTierOvertimeHours()]);
        static::assertSame(7, $income->paidLeaveHours());
        static::assertCount(2, $income->supplements());
        static::assertSame(SupplementCategory::SpecificHours, $income->supplements()[0]->category());
        static::assertSame(SupplementSubCategory::NightPresence, $income->supplements()[0]->subCategory());
        static::assertSame(2009, $income->supplements()[0]->amount()->cents());
        static::assertSame(1250, $income->supplements()[0]->hourlyWage()?->cents());
        static::assertSame(3, $income->supplements()[0]->hours());
        static::assertNull($income->supplements()[1]->hourlyWage());
        static::assertCount(2, $income->paidLeavePeriods());
        static::assertSame('1.5', $income->paidLeavePeriods()[0]->days()->toDecimalString());
        static::assertSame('2026-10-20', $income->paidLeavePeriods()[1]->start()->toString());
        static::assertSame([], DomainAssertions::summary((new DeclarationFactory())->validate(DraftFixtures::valid(), DraftFixtures::context())));
    }

    public function testAMissingTypeIsStandardAndOneIsCesuPlus(): void
    {
        $factory = new DeclarationFactory();
        $withoutType = new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            null,
            new EmploymentPeriodDraft('2026-10-01', '2026-10-31'),
            '2026-10-31',
            null,
            null,
            DraftFixtures::hourlyIncome(),
            null,
            DraftFixtures::employee(),
            null,
        );

        static::assertSame(DeclarationType::Standard, $factory->fromDraft($withoutType, DraftFixtures::context())->type());
        $cesuPlus = $factory->fromDraft(DraftFixtures::withScalars(null, 1, null, '20', null), DraftFixtures::context());
        static::assertInstanceOf(CesuPlusDeclaration::class, $cesuPlus);
        static::assertSame(ActivityNature::Childcare, $cesuPlus->activityNature());
        static::assertSame(DeclarationType::CesuPlus, $cesuPlus->type());
        $standardWithNature = $factory->fromDraft(DraftFixtures::withScalars(null, 0, null, '20', null), DraftFixtures::context());
        static::assertSame(ActivityNature::Childcare, $standardWithNature->activityNature());
    }

    public function testACesuPlusDraftWithoutNatureIsRefused(): void
    {
        $result = (new DeclarationFactory())->validate(DraftFixtures::withScalars(null, 1, null, null, null), DraftFixtures::context());

        static::assertSame(['error activityNature activity_nature.required'], DomainAssertions::summary($result));
        static::assertSame('A Cesu+ declaration requires an activity nature.', $result->all()[0]->message());
    }

    public function testAnEmptyDraftReportsEveryMissingField(): void
    {
        $result = (new DeclarationFactory())->validate(DraftFixtures::empty(), DraftFixtures::context());

        static::assertSame(
            [
                'error employerCesuNumber employer_cesu_number.required',
                'error employmentPeriod.startDate start_date.required',
                'error employmentPeriod.endDate end_date.required',
                'error paymentDate payment_date.required',
                'error employee.identity.birthName birth_name.required',
                'error employee.identity.firstName first_name.required',
                'error employee.identity.birthCountry birth_country.required',
                'error employee.identity.birthDate employee_identity.birth_date_required',
                'error employee.identity.birthCity employee_identity.birth_city_required',
                'error employee.employmentKind employment_kind.required',
                'error employee.civility declared_employee.civility_required',
                'error employee.address declared_employee.address_required',
            ],
            DomainAssertions::summary($result),
        );
        static::assertSame('The employer cesu number is required.', $result->all()[0]->message());
    }

    public function testBlankStringsCountAsMissing(): void
    {
        $draft = new DeclarationDraft(
            '',
            null,
            new EmploymentPeriodDraft('', ''),
            '',
            '',
            '',
            DraftFixtures::hourlyIncome(),
            null,
            DraftFixtures::employee(),
            null,
        );

        static::assertSame(
            [
                'error employerCesuNumber employer_cesu_number.required',
                'error employmentPeriod.startDate start_date.required',
                'error employmentPeriod.endDate end_date.required',
                'error paymentDate payment_date.required',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context())),
        );
    }

    /**
     * @return iterable<string, array{DeclarationDraft, list<string>}>
     */
    public static function provideInvalidScalars(): iterable
    {
        yield 'employer cesu number' => [DraftFixtures::withScalars('abc', null, null, null, null), ['error employerCesuNumber cesu_number.invalid_format']];
        yield 'type' => [DraftFixtures::withScalars(null, 2, null, null, null), ['error type declaration_type.unknown']];
        yield 'payment date' => [DraftFixtures::withScalars(null, null, '2026-02-30', null, null), ['error paymentDate calendar_date.invalid_date']];
        yield 'payment date format' => [DraftFixtures::withScalars(null, null, '31/10/2026', null, null), ['error paymentDate calendar_date.invalid_format']];
        yield 'nature' => [DraftFixtures::withScalars(null, null, null, '99', null), ['error activityNature activity_nature.unknown']];
        yield 'zero deposit' => [DraftFixtures::withScalars(null, null, null, null, '0'), ['error deposit deposit.not_positive']];
        yield 'deposit decimals' => [DraftFixtures::withScalars(null, null, null, null, '1.234'), ['error deposit money.too_many_decimals']];
        yield 'deposit format' => [DraftFixtures::withScalars(null, null, null, null, '1,5'), ['error deposit money.invalid_format']];
        yield 'negative deposit' => [DraftFixtures::withScalars(null, null, null, null, '-1'), ['error deposit money.negative']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidScalars')]
    public function testInvalidScalarFields(DeclarationDraft $draft, array $expected): void
    {
        static::assertSame($expected, DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context())));
    }

    /**
     * @return iterable<string, array{EmploymentPeriodDraft|null, list<string>}>
     */
    public static function provideInvalidPeriods(): iterable
    {
        yield 'no period draft' => [null, ['error employmentPeriod.startDate start_date.required', 'error employmentPeriod.endDate end_date.required']];
        yield 'missing start' => [new EmploymentPeriodDraft(null, '2026-10-31'), ['error employmentPeriod.startDate start_date.required']];
        yield 'missing end' => [new EmploymentPeriodDraft('2026-10-01', null), ['error employmentPeriod.endDate end_date.required']];
        yield 'invalid start' => [new EmploymentPeriodDraft('x', '2026-10-31'), ['error employmentPeriod.startDate calendar_date.invalid_format']];
        yield 'invalid end' => [new EmploymentPeriodDraft('2026-10-01', '2026-13-01'), ['error employmentPeriod.endDate calendar_date.invalid_date']];
        yield 'both invalid' => [new EmploymentPeriodDraft('x', 'y'), ['error employmentPeriod.startDate calendar_date.invalid_format', 'error employmentPeriod.endDate calendar_date.invalid_format']];
        yield 'end before start' => [new EmploymentPeriodDraft('2026-10-02', '2026-10-01'), ['error employmentPeriod.endDate employment_period.end_before_start']];
        yield 'several months' => [new EmploymentPeriodDraft('2026-10-31', '2026-11-01'), ['error employmentPeriod.endDate employment_period.not_single_month']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('provideInvalidPeriods')]
    public function testInvalidEmploymentPeriods(?EmploymentPeriodDraft $period, array $expected): void
    {
        $draft = new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            0,
            $period,
            '2026-10-31',
            null,
            null,
            DraftFixtures::hourlyIncome(),
            null,
            DraftFixtures::employee(),
            null,
        );

        static::assertSame($expected, DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context())));
    }

    public function testFromDraftThrowsWithEveryViolation(): void
    {
        try {
            (new DeclarationFactory())->fromDraft(DraftFixtures::withScalars('abc', 2, null, null, null), DraftFixtures::context());
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failed) {
            static::assertSame(
                ['error employerCesuNumber cesu_number.invalid_format', 'error type declaration_type.unknown'],
                DomainAssertions::summary($failed->result()),
            );
            static::assertSame('validation.failed', $failed->violationCode());
        }
    }

    public function testAnIncompleteDraftDoesNotEvaluateTheCrossFieldRules(): void
    {
        $draft = new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            0,
            new EmploymentPeriodDraft('2026-12-01', '2026-12-31'),
            null,
            null,
            null,
            new HourlyIncomeDraft('12', 10, 5, null, null),
            null,
            DraftFixtures::employee('AF'),
            null,
        );

        static::assertSame(
            ['error paymentDate payment_date.required'],
            DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context())),
        );
    }

    public function testCrossFieldRulesAreReportedTogetherOnACompleteDraft(): void
    {
        $draft = new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            0,
            new EmploymentPeriodDraft('2026-12-01', '2026-12-31'),
            '2026-12-31',
            '20',
            null,
            new HourlyIncomeDraft('12', 10, 5, null, null),
            null,
            DraftFixtures::employee('AF'),
            null,
        );

        static::assertSame(
            [
                'error hourlyIncome.workedHours worked_hours.overtime_requires_forty_normal_hours',
                'error activityNature declaration.nature_forbidden_for_foster_carer',
                'error approval declaration.approval_required',
                'error employmentPeriod.startDate employment_period.starts_after_today',
                'error employee.employmentKind declaration.foster_carer_unsupported',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context())),
        );
    }

    public function testTheEmploymentMayStartToday(): void
    {
        $factory = new DeclarationFactory();
        $startsToday = DraftFixtures::withParts(new EmploymentPeriodDraft('2026-11-15', '2026-11-30'), new HourlyIncomeDraft('12', 100, null, null, null), null, null, null);
        $startsTomorrow = DraftFixtures::withParts(new EmploymentPeriodDraft('2026-11-16', '2026-11-30'), new HourlyIncomeDraft('12', 100, null, null, null), null, null, null);

        static::assertSame([], DomainAssertions::summary($factory->validate($startsToday, DraftFixtures::context())));
        static::assertSame(
            ['error employmentPeriod.startDate employment_period.starts_after_today'],
            DomainAssertions::summary($factory->validate($startsTomorrow, DraftFixtures::context())),
        );
    }

    public function testThePaidLeaveRulesApplyToTheDraft(): void
    {
        $included = new DeclaredEmployeeDraft(DeclarationSamples::EMPLOYEE_CESU_NUMBER, null, null, null, null, null, null, 'PE', true);

        static::assertSame(
            [
                'error hourlyIncome.paidLeaveHours hourly_income.paid_leave_hours_forbidden',
                'error hourlyIncome.paidLeavePeriods hourly_income.paid_leave_periods_forbidden',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate(DraftFixtures::withParts(null, null, $included, null, null), DraftFixtures::context())),
        );
    }

    public function testPaidLeavePeriodsMustBeInsideAndNotOverlap(): void
    {
        $income = new HourlyIncomeDraft(
            '12',
            100,
            null,
            null,
            null,
            [],
            [
                new PaidLeavePeriodDraft('2026-10-05', '2026-10-10', '2'),
                new PaidLeavePeriodDraft('2026-10-10', '2026-10-12', '2'),
                new PaidLeavePeriodDraft('2026-09-30', '2026-10-01', '1'),
            ],
        );

        static::assertSame(
            [
                'error hourlyIncome.paidLeavePeriods[2] paid_leave_period.outside_employment_period',
                'error hourlyIncome.paidLeavePeriods[1] paid_leave_period.overlap',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate(DraftFixtures::withParts(null, $income, null, null, null), DraftFixtures::context())),
        );
    }

    public function testNoIncomeIsOnlyAWarning(): void
    {
        $draft = new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            0,
            new EmploymentPeriodDraft('2026-10-01', '2026-10-31'),
            '2026-10-31',
            null,
            null,
            null,
            null,
            DraftFixtures::employee(),
            null,
        );
        $factory = new DeclarationFactory();

        $result = $factory->validate($draft, DraftFixtures::context());

        static::assertTrue($result->isValid());
        static::assertSame(['warning  declaration.income_missing'], DomainAssertions::summary($result));
        static::assertSame(Severity::Warning, $result->all()[0]->severity());
        static::assertInstanceOf(StandardDeclaration::class, $factory->fromDraft($draft, DraftFixtures::context()));
    }

    public function testAFosterCarerDeclarationIsRefusedUnlessAllowed(): void
    {
        $factory = new DeclarationFactory();
        $draft = DraftFixtures::withParts(null, null, DraftFixtures::employee('AF'), DraftFixtures::fosterIncome(), DraftFixtures::approval());

        static::assertSame(
            ['error employee.employmentKind declaration.foster_carer_unsupported'],
            DomainAssertions::summary($factory->validate($draft, DraftFixtures::context(false))),
        );
        $declaration = $factory->fromDraft($draft, DraftFixtures::context(true));
        static::assertSame(EmploymentKind::FosterCarer, $declaration->content()->employee()->employmentKind());
        static::assertSame(ApprovalBasis::CertifiedApproval, $declaration->content()->approval()?->basis());
        static::assertSame(10_000, $declaration->content()->fosterCarerIncome()?->housingAllowance()?->cents());
    }

    public function testAFosterCarerNeedsItsApproval(): void
    {
        $draft = DraftFixtures::withParts(null, null, DraftFixtures::employee('AF'), DraftFixtures::fosterIncome(), null);

        static::assertSame(
            ['error approval declaration.approval_required'],
            DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context(true))),
        );
    }

    public function testOfficialPlaceholderIsRejected(): void
    {
        $body = WireFixture::load('declare_request_placeholder.json');
        $hourly = WireFixture::map($body['revenusEf']);
        $supplement = WireFixture::maps($hourly['complementsSalaire'])[0];
        $leave = WireFixture::maps($hourly['periodesCongesPayes'])[0];
        $foster = WireFixture::map($body['revenusAf']);
        $employee = WireFixture::map($body['infosSalarie']);
        $address = WireFixture::map($employee['adresse']);
        $approval = WireFixture::map($employee['agrementAF']);
        $draft = new DeclarationDraft(
            DeclarationSamples::EMPLOYER_CESU_NUMBER,
            null,
            new EmploymentPeriodDraft(WireFixture::string($body['dateDebut']), WireFixture::string($body['dateFin'])),
            WireFixture::string($body['dateVersement']),
            null,
            null,
            new HourlyIncomeDraft(
                WireFixture::number($hourly['salaireHoraire']),
                WireFixture::int($hourly['heuresTravaillees']),
                WireFixture::int($hourly['nbHeureSupplementaire1']),
                WireFixture::int($hourly['nbHeureSupplementaire2']),
                WireFixture::int($hourly['heuresCp']),
                [
                    new SalarySupplementDraft(
                        WireFixture::string($supplement['codeCategorie']),
                        WireFixture::string($supplement['codeSousCategorie']),
                        WireFixture::number($supplement['montant']),
                        WireFixture::number($supplement['salaireHoraire']),
                        WireFixture::int($supplement['nombreHeures']),
                    ),
                ],
                [
                    new PaidLeavePeriodDraft(
                        WireFixture::string($leave['dateDebut']),
                        WireFixture::string($leave['dateFin']),
                        WireFixture::number($leave['nbJoursCP']),
                    ),
                ],
            ),
            new FosterCarerIncomeDraft(
                WireFixture::number($foster['montantIndemniteLogement']),
                WireFixture::number($foster['nbJoursIndemniteEntretien']),
                WireFixture::number($foster['nbJoursSujetionsParticulieres']),
                WireFixture::number($foster['nbJoursAccueilTempsComplet']),
                WireFixture::number($foster['nbJoursAccueilTempsPartiel']),
                WireFixture::number($foster['nbMinimumGarantiJourIndemniteEntretien']),
                WireFixture::number($foster['nbSmicsJourSujetionsParticulieres']),
                WireFixture::number($foster['nbSmicsJourAccueilTempsComplet']),
                WireFixture::number($foster['nbSmicsJourAccueilTempsPartiel']),
            ),
            new DeclaredEmployeeDraft(
                WireFixture::string($employee['numeroCesu']),
                WireFixture::string($employee['nir']),
                new EmployeeIdentityDraft(
                    WireFixture::string($employee['nomNaissance']),
                    WireFixture::string($employee['nomUsage']),
                    WireFixture::string($employee['prenom']),
                    WireFixture::string($employee['dateNaissance']),
                    WireFixture::string($employee['villeNaissance']),
                    WireFixture::string($employee['departementNaissance']),
                    WireFixture::string($employee['codePaysNaissance']),
                ),
                WireFixture::string($employee['codeCivilite']),
                new AddressDraft(
                    WireFixture::int($address['numVoie']),
                    WireFixture::string($address['lettreVoie']),
                    WireFixture::string($address['typeVoie']),
                    WireFixture::string($address['libelleVoie']),
                    WireFixture::string($address['complementLocalisation']),
                    WireFixture::string($address['lieuDit']),
                    WireFixture::string($address['codeCommune']),
                    WireFixture::string($address['codePostal']),
                ),
                WireFixture::string($employee['email']),
                WireFixture::string($employee['telephone']),
                WireFixture::string($employee['typeEmploi']),
                WireFixture::bool($employee['congesPayes']),
            ),
            new FosterCarerApprovalDraft(
                WireFixture::string($approval['codeConseilDepartemental']),
                WireFixture::string($approval['numeroAgrement']),
                WireFixture::string($approval['dateDebutAgrement']),
                WireFixture::string($approval['dateFinAgrement']),
                WireFixture::bool($approval['indCertificationValiditeAgrement']),
                WireFixture::bool($approval['indRemplacementPonctuel']),
            ),
        );

        static::assertSame(
            [
                'error hourlyIncome.supplements[0].category supplement_category.unknown',
                'error hourlyIncome.supplements[0].subCategory supplement_sub_category.unknown',
                'error fosterCarerIncome.guaranteedMinimumPerMaintenanceDay guaranteed_minimum_per_day.out_of_range',
                'error employee.cesuNumber cesu_number.invalid_format',
                'error employee.nir nir.invalid_format',
                'error employee.nir employee_reference.cesu_number_and_nir_exclusive',
                'error employee.civility declared_employee.civility_forbidden_with_cesu_number',
                'error employee.address declared_employee.address_forbidden_with_cesu_number',
                'error employee.email declared_employee.email_forbidden_with_cesu_number',
                'error employee.phone declared_employee.phone_forbidden_with_cesu_number',
            ],
            DomainAssertions::summary((new DeclarationFactory())->validate($draft, DraftFixtures::context(true))),
        );
    }

    public function testDraftsOfNestedPartsCanBeContextOnly(): void
    {
        $context = DeclarationContext::of(DeclarationSamples::today(), true);

        static::assertSame('2026-11-15', $context->today()->toString());
        static::assertTrue($context->allowFosterCarerDeclarations());
        static::assertFalse(DeclarationContext::of(DeclarationSamples::today(), false)->allowFosterCarerDeclarations());
    }
}
