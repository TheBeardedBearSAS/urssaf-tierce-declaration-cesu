<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\BodyReader;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;

final class DeclarationRequestMapperEmployeeTest extends TestCase
{
    private const NIR = '269054958815780';

    private static function infosSalarie(DeclaredEmployee $employee): string
    {
        $body = (new DeclarationRequestMapper())->toBody(StandardDeclaration::of(DeclarationSamples::build(
            null,
            $employee,
            null,
            null,
            null,
        )));

        return JsonEncoder::encode(BodyReader::object($body, 'infosSalarie'));
    }

    private static function minimalEmployee(EmployeeReference $reference): DeclaredEmployee
    {
        return new DeclaredEmployee(
            $reference,
            EmploymentKind::HomeEmployment,
            null,
            Civility::Male,
            RequestSamples::minimalAddress(),
            null,
            null,
        );
    }

    public function testAnEmployeeByNirWithEveryOptionalPart(): void
    {
        $employee = RequestSamples::fullEmployee(
            EmployeeByNir::of(Nir::fromString(self::NIR), RequestSamples::fullIdentity()),
            EmploymentKind::HomeEmployment,
        );

        static::assertSame(
            '{"codeCivilite":"2","adresse":{"numVoie":12,"lettreVoie":"B","typeVoie":"R","libelleVoie":"De la Paix",'
            . '"complementLocalisation":"Batiment C","lieuDit":"Le Hameau","codeCommune":"69383","codePostal":"69003"},'
            . '"nomNaissance":"Martin","nomUsage":"Durand","prenom":"Julie","dateNaissance":"1990-05-12",'
            . '"villeNaissance":"Lyon","departementNaissance":"69","codePaysNaissance":"100",'
            . '"email":"julie.martin@example.org","telephone":"0102030405","nir":"269054958815780",'
            . '"typeEmploi":"PE","congesPayes":false}',
            self::infosSalarie($employee),
        );
    }

    public function testAnEmployeeByIdentityWithEveryOptionalPart(): void
    {
        $employee = RequestSamples::fullEmployee(
            EmployeeByIdentity::of(RequestSamples::fullIdentity()),
            EmploymentKind::HomeEmployment,
        );

        static::assertSame(
            '{"codeCivilite":"2","adresse":{"numVoie":12,"lettreVoie":"B","typeVoie":"R","libelleVoie":"De la Paix",'
            . '"complementLocalisation":"Batiment C","lieuDit":"Le Hameau","codeCommune":"69383","codePostal":"69003"},'
            . '"nomNaissance":"Martin","nomUsage":"Durand","prenom":"Julie","dateNaissance":"1990-05-12",'
            . '"villeNaissance":"Lyon","departementNaissance":"69","codePaysNaissance":"100",'
            . '"email":"julie.martin@example.org","telephone":"0102030405","typeEmploi":"PE","congesPayes":false}',
            self::infosSalarie($employee),
        );
    }

    public function testAnEmployeeByNirWithoutOptionalParts(): void
    {
        static::assertSame(
            '{"codeCivilite":"1","adresse":{"lieuDit":"Le Hameau","codePostal":"69003"},"nomNaissance":"Martin",'
            . '"prenom":"Julie","codePaysNaissance":"350","nir":"269054958815780","typeEmploi":"PE"}',
            self::infosSalarie(self::minimalEmployee(EmployeeByNir::of(Nir::fromString(self::NIR), RequestSamples::minimalIdentity()))),
        );
    }

    public function testAnEmployeeByIdentityWithoutOptionalParts(): void
    {
        static::assertSame(
            '{"codeCivilite":"1","adresse":{"lieuDit":"Le Hameau","codePostal":"69003"},"nomNaissance":"Martin",'
            . '"prenom":"Julie","dateNaissance":"1990-05-12","villeNaissance":"Lyon","codePaysNaissance":"350",'
            . '"typeEmploi":"PE"}',
            self::infosSalarie(self::minimalEmployee(EmployeeByIdentity::of(RequestSamples::identityWithRequiredPartsOnly()))),
        );
    }
}
