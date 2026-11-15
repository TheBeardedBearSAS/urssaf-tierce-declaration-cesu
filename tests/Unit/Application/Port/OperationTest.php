<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Port;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;

final class OperationTest extends TestCase
{
    /**
     * @return iterable<string, array{Operation, string, string, bool, bool}>
     */
    public static function provideOperations(): iterable
    {
        yield 'register employer' => [Operation::RegisterEmployer, 'register_employer', '010', false, false];
        yield 'verify employer' => [Operation::VerifyEmployer, 'verify_employer', '020', true, true];
        yield 'verify employee' => [Operation::VerifyEmployee, 'verify_employee', '021', true, true];
        yield 'register mandate' => [Operation::RegisterMandate, 'register_mandate', '030', false, true];
        yield 'cancel mandate' => [Operation::CancelMandate, 'cancel_mandate', '031', false, true];
        yield 'estimate' => [Operation::EstimateContributions, 'estimate_contributions', '040', true, true];
        yield 'declare' => [Operation::DeclareSalary, 'declare_salary', '050', false, false];
        yield 'cesu plus' => [Operation::ActivateCesuPlus, 'activate_cesu_plus', '060', false, false];
        yield 'aici' => [Operation::ActivateAici, 'activate_aici', '070', false, false];
    }

    #[DataProvider('provideOperations')]
    public function testEveryOperation(Operation $operation, string $value, string $number, bool $isRead, bool $isSafeToRetry): void
    {
        static::assertSame($value, $operation->value);
        static::assertSame($number, $operation->methodNumber());
        static::assertSame($isRead, $operation->isRead());
        static::assertSame($isSafeToRetry, $operation->isSafeToRetry());
    }

    public function testThereAreNineOperations(): void
    {
        static::assertCount(9, Operation::cases());
    }
}
