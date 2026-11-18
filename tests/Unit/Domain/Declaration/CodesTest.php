<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\ActivityNature;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SupplementSubCategory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * One method per code table of the declaration scope.
 *
 * @mago-expect lint:too-many-methods
 */
final class CodesTest extends TestCase
{
    public function testDeclarationTypeCodes(): void
    {
        static::assertSame(DeclarationType::Standard, DeclarationType::fromCode(0));
        static::assertSame(DeclarationType::CesuPlus, DeclarationType::fromCode(1));
        static::assertSame(0, DeclarationType::Standard->value);
        static::assertSame(1, DeclarationType::CesuPlus->value);
    }

    #[DataProvider('provideUnknownTypeCodes')]
    public function testUnknownDeclarationTypeIsRefused(int $code): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('declaration_type'));

        DeclarationType::fromCode($code);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function provideUnknownTypeCodes(): iterable
    {
        yield 'negative' => [-1];
        yield 'two' => [2];
    }

    /**
     * @return iterable<string, array{string, ActivityNature}>
     */
    public static function provideActivityNatures(): iterable
    {
        yield '03' => ['03', ActivityNature::DailyHousework];
        yield '04' => ['04', ActivityNature::ShoppingOrLaundryDelivery];
        yield '05' => ['05', ActivityNature::AdministrativeAssistance];
        yield '06' => ['06', ActivityNature::Tutoring];
        yield '07' => ['07', ActivityNature::HomeWatching];
        yield '08' => ['08', ActivityNature::Gardening];
        yield '09' => ['09', ActivityNature::HandymanWork];
        yield '10' => ['10', ActivityNature::ItAssistance];
        yield '11' => ['11', ActivityNature::ElderlyOrDisabledAssistance];
        yield '12' => ['12', ActivityNature::TemporarilyDependentAssistance];
        yield '13' => ['13', ActivityNature::MealsForElderlyOrDisabled];
        yield '14' => ['14', ActivityNature::DrivingElderlyOrDisabled];
        yield '15' => ['15', ActivityNature::DrivingTemporarilyDependent];
        yield '16' => ['16', ActivityNature::PetCareForElderlyOrDisabled];
        yield '17' => ['17', ActivityNature::BeautyCareForElderlyOrDisabled];
        yield '18' => ['18', ActivityNature::SignLanguageInterpreting];
        yield '19' => ['19', ActivityNature::RemoteAssistance];
        yield '20' => ['20', ActivityNature::Childcare];
        yield '21' => ['21', ActivityNature::DisabledChildcare];
        yield '22' => ['22', ActivityNature::OtherServices];
    }

    #[DataProvider('provideActivityNatures')]
    public function testActivityNatureCodes(string $code, ActivityNature $expected): void
    {
        static::assertSame($expected, ActivityNature::fromCode($code));
        static::assertSame($code, $expected->value);
    }

    public function testThereAreTwentyActivityNatures(): void
    {
        static::assertCount(20, ActivityNature::cases());
    }

    #[DataProvider('provideUnknownActivityNatures')]
    public function testUnknownActivityNatureIsRefused(string $code): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('activity_nature'));

        ActivityNature::fromCode($code);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideUnknownActivityNatures(): iterable
    {
        yield 'below the range' => ['02'];
        yield 'above the range' => ['23'];
        yield 'without leading zero' => ['3'];
        yield 'empty' => [''];
    }

    public function testSupplementCategoryCodes(): void
    {
        static::assertSame(SupplementCategory::SpecificHours, SupplementCategory::fromCode('HEUR'));
        static::assertSame(SupplementCategory::BenefitsInKind, SupplementCategory::fromCode('AVAN'));
        static::assertSame(SupplementCategory::EmployeeContribution, SupplementCategory::fromCode('PART'));
        static::assertSame(SupplementCategory::Bonus, SupplementCategory::fromCode('PRIM'));
        static::assertSame(SupplementCategory::Transport, SupplementCategory::fromCode('TRAN'));
        static::assertSame(SupplementCategory::EndOfContract, SupplementCategory::fromCode('FINC'));
        static::assertCount(6, SupplementCategory::cases());
    }

    public function testUnknownSupplementCategoryIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('supplement_category'));

        SupplementCategory::fromCode('heur');
    }

    /**
     * @return iterable<string, array{string, SupplementSubCategory, SupplementCategory}>
     */
    public static function provideSubCategories(): iterable
    {
        yield 'NUIT' => ['NUIT', SupplementSubCategory::NightPresence, SupplementCategory::SpecificHours];
        yield 'GARD' => ['GARD', SupplementSubCategory::NightSickCare, SupplementCategory::SpecificHours];
        yield 'RESP' => ['RESP', SupplementSubCategory::ResponsiblePresence, SupplementCategory::SpecificHours];
        yield 'NOUR' => ['NOUR', SupplementSubCategory::Food, SupplementCategory::BenefitsInKind];
        yield 'LOGE' => ['LOGE', SupplementSubCategory::Housing, SupplementCategory::BenefitsInKind];
        yield 'AUTR' => ['AUTR', SupplementSubCategory::OtherBenefit, SupplementCategory::BenefitsInKind];
        yield 'PART' => ['PART', SupplementSubCategory::EmployeeContribution, SupplementCategory::EmployeeContribution];
        yield 'PRIM' => ['PRIM', SupplementSubCategory::Bonus, SupplementCategory::Bonus];
        yield 'KILO' => ['KILO', SupplementSubCategory::Mileage, SupplementCategory::Transport];
        yield 'FRAI' => ['FRAI', SupplementSubCategory::TransportCosts, SupplementCategory::Transport];
        yield 'LICE' => ['LICE', SupplementSubCategory::DismissalIndemnity, SupplementCategory::EndOfContract];
        yield 'RETR' => ['RETR', SupplementSubCategory::TerminationIndemnity, SupplementCategory::EndOfContract];
    }

    #[DataProvider('provideSubCategories')]
    public function testSupplementSubCategoryCodesAndCategory(
        string $code,
        SupplementSubCategory $expected,
        SupplementCategory $category,
    ): void {
        static::assertSame($expected, SupplementSubCategory::fromCode($code));
        static::assertSame($code, $expected->value);
        static::assertSame($category, $expected->category());
    }

    public function testThereAreTwelveSubCategories(): void
    {
        static::assertCount(12, SupplementSubCategory::cases());
    }

    public function testUnknownSupplementSubCategoryIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('supplement_sub_category'));

        SupplementSubCategory::fromCode('XXXX');
    }
}
