<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Query;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Query\EstimateContributionsHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\Contributions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmploymentKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\ContributionsEstimate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Estimate\EstimateRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration\DeclarationSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Estimate\EstimateSamples;

final class EstimateContributionsHandlerTest extends TestCase
{
    public function testTheEstimateIsReturnedWithoutStoringAnything(): void
    {
        $harness = new Harness();
        $harness->gateway->willEstimate(ContributionsEstimate::of(
            Contributions::of(DeclarationSamples::money('63.1'), DeclarationSamples::money('20.09')),
        ));

        $view = (new EstimateContributionsHandler($harness->gateway))(new EstimateContributions(Harness::tenant('tenant-2'), self::request()));

        static::assertSame(6310, $view->employerContributionsCents);
        static::assertSame(2009, $view->employeeContributionsCents);
        static::assertSame([Operation::EstimateContributions], $harness->gateway->calls());
        static::assertSame(['tenant-2'], $harness->gateway->tenants());
        static::assertSame(['call:040'], $harness->journal->entries());
    }

    public function testARejectionPropagates(): void
    {
        $harness = new Harness();
        $rejection = UrssafRequestRejected::withErrors(Operation::EstimateContributions, 400, ApiErrors::none(), ErrorKind::Unknown, null);
        $harness->gateway->willEstimate($rejection);

        try {
            (new EstimateContributionsHandler($harness->gateway))(new EstimateContributions(Harness::tenant(), self::request()));
            static::fail('Expected the rejection.');
        } catch (UrssafRequestRejected $thrown) {
            static::assertSame($rejection, $thrown);
        }
    }

    public function testTheQueryKeepsItsTenantAndRequest(): void
    {
        $request = self::request();
        $query = new EstimateContributions(Harness::tenant(), $request);

        static::assertSame('tenant-1', $query->tenantId->value());
        static::assertSame($request, $query->request);
    }

    private static function request(): EstimateRequest
    {
        return EstimateRequest::of(EmploymentKind::HomeEmployment, EstimateSamples::hourlyIncome(), null);
    }
}
