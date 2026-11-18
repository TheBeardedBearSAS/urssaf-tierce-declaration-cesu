<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandTenants;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;

final class CommandTenantsTest extends TestCase
{
    public function testTheTenantOptionWinsOverTheConfiguration(): void
    {
        static::assertSame('tenant-9', CommandTenants::tenantOrSingleConfigured(self::input(['--tenant' => 'tenant-9']), ['tenant-1'])->value());
        static::assertSame('tenant-9', CommandTenants::tenantOrSingleConfigured(self::input(['--tenant' => 'tenant-9']), ['tenant-1', 'tenant-2'])->value());
    }

    public function testTheOnlyConfiguredTenantIsTheDefault(): void
    {
        static::assertSame('tenant-1', CommandTenants::tenantOrSingleConfigured(self::input([]), ['tenant-1'])->value());
    }

    public function testSeveralOrNoConfiguredTenantNeedTheOption(): void
    {
        foreach ([[], ['tenant-1', 'tenant-2'], ['a', 'b', 'c']] as $configured) {
            try {
                CommandTenants::tenantOrSingleConfigured(self::input([]), $configured);
                static::fail('The tenant option must be required.');
            } catch (InvalidOptionException $failure) {
                static::assertSame(\sprintf('The --tenant option is required (configured tenants: %d).', \count($configured)), $failure->getMessage());
            }
        }
    }

    public function testTheTenantOptionOrEveryConfiguredTenant(): void
    {
        static::assertEquals([TenantId::fromString('tenant-9')], CommandTenants::tenantsOrConfigured(self::input(['--tenant' => 'tenant-9']), ['tenant-1']));
        static::assertEquals(
            [TenantId::fromString('tenant-1'), TenantId::fromString('tenant-2')],
            CommandTenants::tenantsOrConfigured(self::input([]), ['tenant-1', 'tenant-2']),
        );
    }

    public function testNoTenantAtAllIsRefused(): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('No tenant to process: use --tenant=<id> or configure "urssaf_tierce_declaration_cesu.tenants".');

        CommandTenants::tenantsOrConfigured(self::input([]), []);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function input(array $arguments): ArrayInput
    {
        $command = new Command('test');
        CommandOptions::addTenant($command, 'The tenant.');

        return new ArrayInput($arguments, $command->getDefinition());
    }
}
