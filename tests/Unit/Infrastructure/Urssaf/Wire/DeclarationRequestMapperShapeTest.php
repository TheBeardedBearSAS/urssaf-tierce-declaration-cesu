<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Wire;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByIdentity;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByNir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Nir;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\DeclarationRequestMapper;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire\JsonEncoder;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\Fixtures;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\RequestSamples;

/**
 * The official 050 template ({@see Fixtures}, `declare_request_placeholder`) only has placeholder values that violate
 * the contract on purpose: its shape is compared, never its values.
 */
final class DeclarationRequestMapperShapeTest extends TestCase
{
    /**
     * Fields of the contract that the Swagger-UI template does not show.
     */
    private const NOT_IN_THE_TEMPLATE = ['typeDeclaration', 'natureActivite', 'montantAcompte'];

    /**
     * Key paths of a decoded body: `a.b.c` for objects, `a[]` for lists (union over the elements).
     *
     * Walks a decoded JSON document, whose nodes are untyped by nature.
     *
     * @mago-expect analysis:mixed-assignment
     *
     * @return list<string>
     */
    private static function paths(mixed $node, string $prefix): array
    {
        if (!\is_array($node) || $node === []) {
            return [$prefix];
        }

        $paths = [];
        foreach ($node as $key => $child) {
            $path = array_is_list($node) ? $prefix . '[]' : ($prefix === '' ? '' : $prefix . '.') . $key;
            $paths = [...$paths, ...(\is_array($child) && $child !== [] ? self::paths($child, $path) : [$path])];
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private static function bodyPaths(StandardDeclaration $declaration): array
    {
        $json = JsonEncoder::encode((new DeclarationRequestMapper())->toBody($declaration));

        return self::paths(json_decode($json, true, 512, JSON_THROW_ON_ERROR), '');
    }

    public function testShapeMatchesTheOfficialTemplate(): void
    {
        $byCesuNumber = DeclarationSamples::employee(EmploymentKind::FosterCarer, false);
        $byNir = RequestSamples::fullEmployee(
            EmployeeByNir::of(Nir::fromString('269054958815780'), RequestSamples::fullIdentity()),
            EmploymentKind::FosterCarer,
        );
        $byIdentity = RequestSamples::fullEmployee(
            EmployeeByIdentity::of(RequestSamples::fullIdentity()),
            EmploymentKind::FosterCarer,
        );

        $produced = [];
        foreach ([$byCesuNumber, $byNir, $byIdentity] as $employee) {
            $declaration = StandardDeclaration::of(RequestSamples::maximalContent($employee));
            $produced = [...$produced, ...self::bodyPaths($declaration)];
        }
        $produced = array_values(array_unique($produced));
        $template = self::paths(Fixtures::decoded('declare_request_placeholder'), '');

        static::assertSame([], array_values(array_diff($produced, [...$template, ...self::NOT_IN_THE_TEMPLATE])));
        static::assertSame([], array_values(array_diff($template, $produced)));
    }

    public function testTheByCesuNumberVariantIsMaximalToo(): void
    {
        $employee = new DeclaredEmployee(
            EmployeeByCesuNumber::of(DeclarationSamples::employeeCesuNumber()),
            EmploymentKind::FosterCarer,
            false,
            null,
            null,
            null,
            null,
        );

        static::assertContains('infosSalarie.numeroCesu', self::bodyPaths(StandardDeclaration::of(RequestSamples::maximalContent($employee))));
    }
}
