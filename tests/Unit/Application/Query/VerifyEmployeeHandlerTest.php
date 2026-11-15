<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\EmployeeVerificationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployeeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use Throwable;

final class VerifyEmployeeHandlerTest extends TestCase
{
    public function testRegisteredEmployee(): void
    {
        $harness = new Harness();
        $harness->gateway->willVerifyEmployee(EmployeeVerificationResult::of(true, EmployeeSamples::cesuNumber()));

        $view = (new VerifyEmployeeHandler($harness->gateway))(self::query());

        static::assertTrue($view->registered);
        static::assertSame(EmployeeSamples::cesuNumber()->value(), $view->cesuNumber);
        static::assertSame(['call:021'], $harness->journal->entries());
        static::assertEquals([EmployeeSamples::byCesuNumber()], $harness->gateway->employees());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
    }

    public function testUnregisteredEmployee(): void
    {
        $harness = new Harness();
        $harness->gateway->willVerifyEmployee(EmployeeVerificationResult::of(false, null));

        $view = (new VerifyEmployeeHandler($harness->gateway))(self::query());

        static::assertFalse($view->registered);
        static::assertNull($view->cesuNumber);
    }

    public function testInvalidDraftIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();

        try {
            (new VerifyEmployeeHandler($harness->gateway))(new VerifyEmployee(Harness::tenant(), new EmployeeReferenceDraft(null, null, null)));
            static::fail('Expected a validation failure.');
        } catch (ValidationFailed $failure) {
            static::assertNotSame([], $failure->result()->errors());
        }

        static::assertSame([], $harness->journal->entries());
    }

    #[DataProvider('provideCallFailures')]
    public function testEveryFailureOfTheCallPropagates(Throwable $failure): void
    {
        $harness = new Harness();
        $harness->gateway->willVerifyEmployee($failure);

        try {
            (new VerifyEmployeeHandler($harness->gateway))(self::query());
            static::fail('Expected a failure.');
        } catch (Throwable $caught) {
            static::assertSame($failure, $caught);
        }
    }

    /**
     * @return iterable<string, array{Throwable}>
     */
    public static function provideCallFailures(): iterable
    {
        yield 'rejected' => [UrssafRequestRejected::withErrors(Operation::VerifyEmployee, 400, ApiErrors::none(), ErrorKind::InvalidParameter, null)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(401)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::VerifyEmployee, 503, null, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::VerifyEmployee, 2)];
    }

    private static function query(): VerifyEmployee
    {
        return new VerifyEmployee(Harness::tenant(), new EmployeeReferenceDraft(EmployeeSamples::cesuNumber()->value(), null, null));
    }
}
