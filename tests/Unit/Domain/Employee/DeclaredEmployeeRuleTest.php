<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployeeRule;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;

/**
 * @phpstan-import-type Presence from DeclaredEmployeeRule
 */
final class DeclaredEmployeeRuleTest extends TestCase
{
    /**
     * @param array{identity?: bool, civility?: bool, address?: bool, email?: bool, phone?: bool} $overrides
     *
     * @return Presence
     */
    private static function presence(array $overrides): array
    {
        return [
            'identity' => $overrides['identity'] ?? false,
            'civility' => $overrides['civility'] ?? false,
            'address' => $overrides['address'] ?? false,
            'email' => $overrides['email'] ?? false,
            'phone' => $overrides['phone'] ?? false,
        ];
    }

    /**
     * @return iterable<string, array{EmployeeReferenceKind, array{identity?: bool, civility?: bool, address?: bool, email?: bool, phone?: bool}, list<DeclaredEmployeeRule>}>
     */
    public static function provideScenarios(): iterable
    {
        $cesu = EmployeeReferenceKind::CesuNumber;
        $nir = EmployeeReferenceKind::Nir;
        $identity = EmployeeReferenceKind::Identity;
        $complete = ['identity' => true, 'civility' => true, 'address' => true];

        yield 'cesu number alone' => [$cesu, [], []];
        yield 'cesu number with identity flag only' => [$cesu, ['identity' => true], []];
        yield 'cesu number with civility' => [$cesu, ['civility' => true], [DeclaredEmployeeRule::CivilityForbiddenWithCesuNumber]];
        yield 'cesu number with address' => [$cesu, ['address' => true], [DeclaredEmployeeRule::AddressForbiddenWithCesuNumber]];
        yield 'cesu number with e-mail' => [$cesu, ['email' => true], [DeclaredEmployeeRule::EmailForbiddenWithCesuNumber]];
        yield 'cesu number with phone' => [$cesu, ['phone' => true], [DeclaredEmployeeRule::PhoneForbiddenWithCesuNumber]];
        yield 'cesu number with everything' => [
            $cesu,
            ['identity' => true, 'civility' => true, 'address' => true, 'email' => true, 'phone' => true],
            [
                DeclaredEmployeeRule::CivilityForbiddenWithCesuNumber,
                DeclaredEmployeeRule::AddressForbiddenWithCesuNumber,
                DeclaredEmployeeRule::EmailForbiddenWithCesuNumber,
                DeclaredEmployeeRule::PhoneForbiddenWithCesuNumber,
            ],
        ];
        yield 'nir complete' => [$nir, $complete, []];
        yield 'nir complete with e-mail and phone' => [$nir, $complete + ['email' => true, 'phone' => true], []];
        yield 'nir alone' => [
            $nir,
            [],
            [DeclaredEmployeeRule::CivilityRequired, DeclaredEmployeeRule::AddressRequired, DeclaredEmployeeRule::IdentityRequiredWithNir],
        ];
        yield 'nir without civility' => [$nir, ['identity' => true, 'address' => true], [DeclaredEmployeeRule::CivilityRequired]];
        yield 'nir without address' => [$nir, ['identity' => true, 'civility' => true], [DeclaredEmployeeRule::AddressRequired]];
        yield 'nir without identity' => [$nir, ['civility' => true, 'address' => true], [DeclaredEmployeeRule::IdentityRequiredWithNir]];
        yield 'identity complete' => [$identity, ['civility' => true, 'address' => true], []];
        yield 'identity alone' => [$identity, ['identity' => true], [DeclaredEmployeeRule::CivilityRequired, DeclaredEmployeeRule::AddressRequired]];
        yield 'identity without the identity flag' => [$identity, ['civility' => true, 'address' => true], []];
        yield 'identity without civility' => [$identity, ['address' => true], [DeclaredEmployeeRule::CivilityRequired]];
        yield 'identity without address' => [$identity, ['civility' => true], [DeclaredEmployeeRule::AddressRequired]];
    }

    /**
     * @param array{identity?: bool, civility?: bool, address?: bool, email?: bool, phone?: bool} $overrides
     * @param list<DeclaredEmployeeRule>                                                          $expected
     */
    #[DataProvider('provideScenarios')]
    public function testViolatedBy(EmployeeReferenceKind $kind, array $overrides, array $expected): void
    {
        $presence = self::presence($overrides);

        static::assertSame($expected, DeclaredEmployeeRule::violatedBy($kind, $presence));
        foreach (DeclaredEmployeeRule::cases() as $rule) {
            static::assertSame(\in_array($rule, $expected, true), $rule->isViolatedBy($kind, $presence), $rule->name);
        }
    }

    public function testPathsCodesAndMessages(): void
    {
        $expected = [
            'CivilityForbiddenWithCesuNumber' => ['civility', 'declared_employee.civility_forbidden_with_cesu_number', 'The civility must not be given with an employee Cesu number.'],
            'AddressForbiddenWithCesuNumber' => ['address', 'declared_employee.address_forbidden_with_cesu_number', 'The address must not be given with an employee Cesu number.'],
            'EmailForbiddenWithCesuNumber' => ['email', 'declared_employee.email_forbidden_with_cesu_number', 'The e-mail must not be given with an employee Cesu number.'],
            'PhoneForbiddenWithCesuNumber' => ['phone', 'declared_employee.phone_forbidden_with_cesu_number', 'The phone number must not be given with an employee Cesu number.'],
            'CivilityRequired' => ['civility', 'declared_employee.civility_required', 'The civility is required without an employee Cesu number.'],
            'AddressRequired' => ['address', 'declared_employee.address_required', 'The address is required without an employee Cesu number.'],
            'IdentityRequiredWithNir' => ['identity', 'declared_employee.identity_required_with_nir', 'The administrative data are required with a NIR.'],
        ];

        static::assertCount(\count($expected), DeclaredEmployeeRule::cases());
        foreach (DeclaredEmployeeRule::cases() as $rule) {
            static::assertSame($expected[$rule->name], [$rule->path(), $rule->code(), $rule->message()], $rule->name);
        }
    }
}
