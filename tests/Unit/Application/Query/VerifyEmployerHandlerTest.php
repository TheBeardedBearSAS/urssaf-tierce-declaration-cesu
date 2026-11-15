<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Query;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\VerifyEmployerHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\Input\EmployeeReferenceDraft;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\ValidationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use Throwable;

final class VerifyEmployerHandlerTest extends TestCase
{
    public function testReadsTheEmployerStatusWithoutStoringAnything(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willVerifyEmployer(EmployerSamples::status(true, true));

        $view = self::handler($harness)(new VerifyEmployer(Harness::tenant(), Harness::employer(1), null));

        static::assertTrue($view->registered);
        static::assertSame('actif', $view->onlineAccountState);
        static::assertSame('actif', $view->sepaMandateState);
        static::assertTrue($view->mandatePresent);
        static::assertTrue($view->aiciActive);
        static::assertNull($view->cesuPlusState);
        static::assertNull($view->employeeReferenceKind);
        static::assertSame(Harness::NOW, $view->checkedAt);
        static::assertSame(['call:020'], $harness->journal->entries());
        static::assertNull($harness->gateway->verifications()[0]->employee());
        static::assertEquals(Harness::employer(1), $harness->gateway->verifications()[0]->employer());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
        static::assertNull($harness->accounts->get(EmployerAccountId::fromString('account-1'))->lastCheckedAt());
    }

    public function testReadsTheStateOfAPair(): void
    {
        $harness = new Harness();
        $harness->gateway->willVerifyEmployer(EmployerSamples::statusWithCesuPlus(false, 'actif'));
        $query = new VerifyEmployer(Harness::tenant(), Harness::employer(1), new EmployeeReferenceDraft(EmployeeSamples::cesuNumber()->value(), null, null));

        $view = self::handler($harness)($query);

        static::assertSame('actif', $view->cesuPlusState);
        static::assertSame(EmployeeReferenceKind::CesuNumber->value, $view->employeeReferenceKind);
        static::assertSame(EmployeeSamples::cesuNumber()->value(), $view->employeeCesuNumber);
        static::assertEquals(EmployeeSamples::byCesuNumber(), $harness->gateway->verifications()[0]->employee());
    }

    public function testInvalidEmployeeDraftIsRefusedBeforeAnyCall(): void
    {
        $harness = new Harness();
        $query = new VerifyEmployer(Harness::tenant(), Harness::employer(1), new EmployeeReferenceDraft(null, null, null));

        try {
            self::handler($harness)($query);
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
        $harness->gateway->willVerifyEmployer($failure);

        try {
            self::handler($harness)(new VerifyEmployer(Harness::tenant(), Harness::employer(1), null));
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
        yield 'rejected' => [UrssafRequestRejected::withErrors(Operation::VerifyEmployer, 400, ApiErrors::none(), ErrorKind::InvalidParameter, null)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(401)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::VerifyEmployer, 503, null, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::VerifyEmployer, 2)];
    }

    private static function handler(Harness $harness): VerifyEmployerHandler
    {
        return new VerifyEmployerHandler($harness->gateway, $harness->clock);
    }
}
