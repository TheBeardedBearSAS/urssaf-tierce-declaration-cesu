<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Testing;

use Closure;
use LogicException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationContent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\StandardDeclaration;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\DeclaredEmployee;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeByCesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\EmployerSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Support\GatewaySamples;
use Throwable;

/**
 * Fictitious values shared by the simulator tests (documentation examples and invented data only).
 *
 * One static factory per fictitious sample, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class SimulatorSamples
{
    private function __construct() {}

    public static function tenant1(): TenantId
    {
        return TenantId::fromString('tenant-1');
    }

    public static function tenant2(): TenantId
    {
        return TenantId::fromString('tenant-2');
    }

    public static function employer(): Employer
    {
        return EmployerSamples::employer();
    }

    public static function cesuNumber(): CesuNumber
    {
        return EmployerSamples::employer()->cesuNumber();
    }

    /**
     * The sample employer with a birth date that differs from the registered one.
     */
    public static function employerBornElsewhen(): Employer
    {
        return Employer::of(self::cesuNumber(), CalendarDate::fromString('1980-05-06'));
    }

    public static function unknownEmployer(): Employer
    {
        return Employer::of(CesuNumber::fromString('Z0000000000001'), CalendarDate::fromString('1975-01-30'));
    }

    /**
     * A simulator that knows the sample employer.
     */
    public static function gateway(): SimulatedCesuGateway
    {
        $gateway = new SimulatedCesuGateway();
        $gateway->knowEmployer(self::employer());

        return $gateway;
    }

    /**
     * A simulator where the tenant 1 holds the mandate of the sample employer.
     */
    public static function mandatedGateway(): SimulatedCesuGateway
    {
        $gateway = self::gateway();
        $gateway->mandate(self::cesuNumber(), self::tenant1());

        return $gateway;
    }

    public static function aiciRequest(): AiciActivationRequest
    {
        $sample = GatewaySamples::aiciActivation();

        return new AiciActivationRequest(
            self::employer(),
            $sample->civility(),
            $sample->birthName(),
            $sample->usageName(),
            $sample->firstName(),
            $sample->birthCountry(),
            $sample->birthMunicipalityCode(),
            $sample->birthMunicipalityName(),
        );
    }

    public static function aiciRequestFor(Employer $employer): AiciActivationRequest
    {
        $sample = self::aiciRequest();

        return new AiciActivationRequest(
            $employer,
            $sample->civility(),
            $sample->birthName(),
            $sample->usageName(),
            $sample->firstName(),
            $sample->birthCountry(),
            $sample->birthMunicipalityCode(),
            $sample->birthMunicipalityName(),
        );
    }

    /**
     * The standard declaration with another period.
     */
    public static function declarationOfPeriod(string $start, string $end): StandardDeclaration
    {
        return StandardDeclaration::of(DeclarationSamples::build(null, null, DeclarationSamples::period($start, $end), null, null));
    }

    /**
     * The standard declaration of another employee.
     */
    public static function declarationOfOtherEmployee(): StandardDeclaration
    {
        $employee = new DeclaredEmployee(
            EmployeeByCesuNumber::of(CesuNumber::fromString('00000999999999')),
            EmploymentKind::HomeEmployment,
            false,
            null,
            null,
            null,
            null,
        );

        return StandardDeclaration::of(DeclarationSamples::build(null, $employee, null, null, null));
    }

    /**
     * The standard declaration of another employer.
     */
    public static function declarationOfEmployer(CesuNumber $employer): StandardDeclaration
    {
        $content = DeclarationSamples::content();

        return StandardDeclaration::of(new DeclarationContent(
            $employer,
            $content->employmentPeriod(),
            $content->paymentDate(),
            $content->employee(),
            $content->hourlyIncome(),
            null,
            null,
            null,
            null,
        ));
    }

    /**
     * @param-immediately-invoked-callable $call
     * @param Closure(): mixed $call
     */
    public static function rejection(Closure $call): UrssafRequestRejected
    {
        try {
            $call();
        } catch (UrssafRequestRejected $rejected) {
            return $rejected;
        }

        throw new LogicException('The call was expected to be rejected.');
    }

    /**
     * @param-immediately-invoked-callable $call
     * @param Closure(): mixed $call
     */
    public static function failure(Closure $call): Throwable
    {
        try {
            $call();
        } catch (Throwable $failure) {
            return $failure;
        }

        throw new LogicException('The call was expected to fail.');
    }
}
