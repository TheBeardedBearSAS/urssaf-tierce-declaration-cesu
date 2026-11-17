<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateAici;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ActivateAiciHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\AiciActivator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafAuthenticationFailed;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Address\MunicipalityCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\AiciActivationRequest;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\BirthMunicipalityName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\Civility;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Identity\CivilName;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CountryCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;
use Throwable;

final class ActivateAiciHandlerTest extends TestCase
{
    public function testActivatesAndStoresNothing(): void
    {
        $harness = new Harness();
        $harness->account(1);
        $harness->gateway->willActivateAici(AiciActivation::of(true));

        $activation = (new ActivateAiciHandler($harness->gateway))(new ActivateAici(Harness::tenant(), self::request()));

        static::assertTrue($activation->isActive());
        static::assertSame(['call:070'], $harness->journal->entries());
        static::assertSame([Harness::TENANT], $harness->gateway->tenants());
    }

    public function testTheTenantAndTheRequestAreForwardedUnchanged(): void
    {
        $request = self::request();
        $activator = new class implements AiciActivator {
            /** @var list<array{TenantId, AiciActivationRequest}> */
            private array $received = [];

            public function activateAici(TenantId $tenantId, AiciActivationRequest $request): AiciActivation
            {
                $this->received[] = [$tenantId, $request];

                return AiciActivation::of(false);
            }

            /**
             * @return list<array{TenantId, AiciActivationRequest}>
             */
            public function received(): array
            {
                return $this->received;
            }
        };

        $activation = (new ActivateAiciHandler($activator))(new ActivateAici(Harness::tenant(Harness::OTHER_TENANT), $request));

        static::assertFalse($activation->isActive());
        [[$tenantId, $received]] = $activator->received();
        static::assertSame(Harness::OTHER_TENANT, $tenantId->value());
        static::assertSame($request, $received);
    }

    #[DataProvider('provideCallFailures')]
    public function testEveryFailureOfTheCallPropagates(Throwable $failure): void
    {
        $harness = new Harness();
        $harness->gateway->willActivateAici($failure);

        try {
            (new ActivateAiciHandler($harness->gateway))(new ActivateAici(Harness::tenant(), self::request()));
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
        yield 'rejected' => [UrssafRequestRejected::withErrors(Operation::ActivateAici, 400, ApiErrors::none(), ErrorKind::Unknown, null)];
        yield 'invalid credentials' => [UrssafAuthenticationFailed::withStatus(401)];
        yield 'unavailable' => [UrssafUnavailable::withStatus(Operation::ActivateAici, 503, null, ApiErrors::none())];
        yield 'rate limited' => [RateLimitExceeded::forOperation(Operation::ActivateAici, 2)];
    }

    private static function request(): AiciActivationRequest
    {
        return new AiciActivationRequest(
            Harness::employer(1),
            Civility::Male,
            CivilName::birthName('Dupond'),
            CivilName::usageName('Dupond'),
            CivilName::firstName('Marcel'),
            CountryCode::france(),
            MunicipalityCode::fromString('69381'),
            BirthMunicipalityName::fromString('LYON 01'),
        );
    }
}
