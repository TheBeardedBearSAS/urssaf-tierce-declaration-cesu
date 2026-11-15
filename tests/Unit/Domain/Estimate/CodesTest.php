<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Estimate;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ExemptionCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\GeographicZone;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\SalaryType;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

final class CodesTest extends TestCase
{
    public function testSalaryTypes(): void
    {
        static::assertSame(SalaryType::Gross, SalaryType::fromCode('BRUT'));
        static::assertSame(SalaryType::Net, SalaryType::fromCode('NET'));
        static::assertCount(2, SalaryType::cases());
    }

    public function testUnknownSalaryTypeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('salary_type'));

        SalaryType::fromCode('net');
    }

    /**
     * @return iterable<string, array{string, ExemptionCode}>
     */
    public static function provideExemptionCodes(): iterable
    {
        yield '02' => ['02', ExemptionCode::NoExemption];
        yield '01' => ['01', ExemptionCode::OverSeventy];
        yield '03' => ['03', ExemptionCode::Aeeh];
        yield '05' => ['05', ExemptionCode::Disability80Percent];
        yield '06' => ['06', ExemptionCode::ThirdPartyAssistance];
        yield '70' => ['70', ExemptionCode::DisabilityCompensation];
        yield '71' => ['71', ExemptionCode::ChildDisabilityCompensation];
        yield '80' => ['80', ExemptionCode::PersonalisedAutonomyAllowance];
    }

    #[DataProvider('provideExemptionCodes')]
    public function testExemptionCodes(string $code, ExemptionCode $expected): void
    {
        static::assertSame($expected, ExemptionCode::fromCode($code));
        static::assertSame($code, $expected->value);
    }

    public function testThereAreEightExemptionCodes(): void
    {
        static::assertCount(8, ExemptionCode::cases());
    }

    public function testUnknownExemptionCodeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('exemption_code'));

        ExemptionCode::fromCode('04');
    }

    /**
     * @return iterable<string, array{string, GeographicZone}>
     */
    public static function provideZones(): iterable
    {
        yield 'NATIO' => ['NATIO', GeographicZone::Metropolitan];
        yield '97100' => ['97100', GeographicZone::Overseas];
        yield 'ALSMO' => ['ALSMO', GeographicZone::AlsaceMoselle];
        yield 'DOM' => ['DOM', GeographicZone::Dom];
        yield 'ETRAN' => ['ETRAN', GeographicZone::Etran];
        yield '97200' => ['97200', GeographicZone::Zone97200];
        yield '97300' => ['97300', GeographicZone::Zone97300];
        yield '97400' => ['97400', GeographicZone::Zone97400];
        yield '97700' => ['97700', GeographicZone::Zone97700];
        yield '97800' => ['97800', GeographicZone::Zone97800];
    }

    #[DataProvider('provideZones')]
    public function testGeographicZones(string $code, GeographicZone $expected): void
    {
        static::assertSame($expected, GeographicZone::fromCode($code));
        static::assertSame($code, $expected->value);
    }

    public function testThereAreTenGeographicZones(): void
    {
        static::assertCount(10, GeographicZone::cases());
    }

    public function testUnknownGeographicZoneIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::unknown('geographic_zone'));

        GeographicZone::fromCode('97500');
    }
}
