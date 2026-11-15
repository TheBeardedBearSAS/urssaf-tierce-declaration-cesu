<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Validation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Validation\ViolationListConverter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Input\DeclarationFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Input\NewEmployerFactory;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\Input\DraftFixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;

/**
 * The drafts are plain readonly objects: the Symfony Serializer builds them from a request body, the factories validate
 * them and the converter turns the violations into a constraint violation list, as documented in docs/symfony.md
 * (validation and forms). Reference DraftDenormalizationTest.
 */
final class DraftDenormalizationTest extends TestCase
{
    public function testADeclarationDraftIsBuiltFromJsonWithItsNestedParts(): void
    {
        $json = <<<'JSON'
            {
                "employerCesuNumber": "Z0003752390009",
                "type": 0,
                "employmentPeriod": {"startDate": "2026-10-01", "endDate": "2026-10-31"},
                "paymentDate": "2026-10-31",
                "deposit": "10",
                "hourlyIncome": {
                    "hourlyWage": "12.5",
                    "workedHours": 100,
                    "firstTierOvertimeHours": 8,
                    "secondTierOvertimeHours": 9,
                    "paidLeaveHours": 7,
                    "supplements": [
                        {"category": "HEUR", "subCategory": "NUIT", "amount": "20.09", "hourlyWage": "12.5", "hours": 3},
                        {"category": "PRIM", "subCategory": "PRIM", "amount": "50"}
                    ],
                    "paidLeavePeriods": [
                        {"startDate": "2026-10-05", "endDate": "2026-10-06", "days": "1.5"},
                        {"startDate": "2026-10-20", "endDate": "2026-10-21", "days": "2"}
                    ]
                },
                "employee": {"cesuNumber": "00000123456789", "employmentKind": "PE", "paidLeaveIncluded": false}
            }
            JSON;

        $draft = self::serializer()->deserialize($json, DeclarationDraft::class, 'json');

        static::assertEquals(DraftFixtures::valid(), $draft);
        $result = (new DeclarationFactory())->validate($draft, DraftFixtures::context());
        static::assertTrue($result->isValid());
    }

    public function testAnEmployerDraftIsBuiltFromJsonAndItsViolationsAreConverted(): void
    {
        $json = <<<'JSON'
            {
                "civility": "1",
                "lastName": "Dupond",
                "firstName": "Pierre",
                "birthDate": "1975-01-30",
                "email": "pierre.durand@example.com",
                "phone": "0412345678",
                "address": {"municipalityCode": "69383", "postalCode": "69003"},
                "bankDetails": {"iban": "FR7630001007941234567890185", "bic": "BDFEFRPPCCT", "sepaMandate": true}
            }
            JSON;

        $draft = self::serializer()->deserialize($json, NewEmployerDraft::class, 'json');

        static::assertInstanceOf(NewEmployerDraft::class, $draft);
        $list = (new ViolationListConverter(null))->convert((new NewEmployerFactory())->validate($draft), 'employer');
        static::assertCount(1, $list);
        static::assertSame('employer.address', $list->get(0)->getPropertyPath());
        static::assertSame('address.location_required', $list->get(0)->getCode());
        // @mago-expect analysis:mixed-assignment JSON is untyped: the assertion below narrows it.
        $body = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        static::assertIsArray($body);
        $body['address'] = ['streetNumber' => 12, 'streetLetter' => 'B', 'streetType' => 'R', 'streetName' => 'De la Paix', 'municipalityCode' => '69383', 'postalCode' => '69003'];
        $complete = self::serializer()->deserialize(json_encode($body, JSON_THROW_ON_ERROR), NewEmployerDraft::class, 'json');
        static::assertTrue((new NewEmployerFactory())->validate($complete)->isValid());
        static::assertSame(EmployerSamples::newEmployerDraft()->bankDetails?->iban, $complete->bankDetails?->iban);
    }

    public function testAnEmployeeReferenceDraftIsBuiltFromJsonAndMissingFieldsAreNull(): void
    {
        $draft = self::serializer()->deserialize('{"cesuNumber": "00000123456789"}', EmployeeReferenceDraft::class, 'json');

        static::assertEquals(new EmployeeReferenceDraft('00000123456789', null, null), $draft);
    }

    private static function serializer(): Serializer
    {
        $reflection = new ReflectionExtractor();
        $types = new PropertyInfoExtractor([$reflection], [new PhpStanExtractor(), $reflection]);

        return new Serializer([new ArrayDenormalizer(), new ObjectNormalizer(null, null, null, $types)], [new JsonEncoder()]);
    }
}
