<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandOptions;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * The shared options and their reading (reference adaptation, lot 6 design §5.1).
 */
final class CommandOptionsTest extends TestCase
{
    public function testTheSharedOptionsAreDeclared(): void
    {
        $command = new Command('test');

        CommandOptions::addTenant($command, 'The tenant.');
        CommandOptions::addLimit($command);
        CommandOptions::addDryRun($command);

        $definition = $command->getDefinition();
        static::assertSame('The tenant.', $definition->getOption('tenant')->getDescription());
        static::assertTrue($definition->getOption('tenant')->isValueRequired());
        static::assertTrue($definition->getOption('limit')->isValueRequired());
        static::assertFalse($definition->getOption('dry-run')->acceptValue());
        static::assertSame(['tenant', 'limit', 'dry-run'], array_keys($definition->getOptions()));
    }

    public function testTheDryRunFlag(): void
    {
        static::assertTrue(CommandOptions::dryRun(self::input(['--dry-run' => true])));
        static::assertFalse(CommandOptions::dryRun(self::input([])));
    }

    public function testTheTenantIsOptional(): void
    {
        static::assertNull(CommandOptions::tenant(self::input([])));
        static::assertSame('tenant-1', CommandOptions::tenant(self::input(['--tenant' => 'tenant-1']))?->value());
    }

    public function testAnInvalidTenantIsAnInvalidValue(): void
    {
        $this->expectException(InvalidValue::class);

        CommandOptions::tenant(self::input(['--tenant' => '']));
    }

    public function testTheTextOfAnOptionIsNullWhenItIsNotAString(): void
    {
        $command = new Command('test');
        CommandOptions::addTenant($command, 'The tenant.');
        CommandOptions::addDryRun($command);
        $command->addArgument('name');

        static::assertSame('tenant-1', CommandOptions::text(self::inputOf($command, ['--tenant' => 'tenant-1']), 'tenant'));
        static::assertNull(CommandOptions::text(self::inputOf($command, []), 'tenant'));
        static::assertNull(CommandOptions::text(self::inputOf($command, ['--dry-run' => true]), 'dry-run'));
        static::assertSame('Z0003752390009', CommandOptions::argument(self::inputOf($command, ['name' => 'Z0003752390009']), 'name'));
        static::assertNull(CommandOptions::argument(self::inputOf($command, []), 'name'));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function input(array $arguments): ArrayInput
    {
        $command = new Command('test');
        CommandOptions::addTenant($command, 'The tenant.');
        CommandOptions::addLimit($command);
        CommandOptions::addDryRun($command);

        return self::inputOf($command, $arguments);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function inputOf(Command $command, array $arguments): ArrayInput
    {
        return new ArrayInput($arguments, $command->getDefinition());
    }
}
