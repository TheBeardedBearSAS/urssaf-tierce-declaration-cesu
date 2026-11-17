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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandSelectors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

final class CommandDeclarationSelectorTest extends TestCase
{
    public function testADeclarationIsDesignatedByItsExternalReference(): void
    {
        static::assertEquals(
            DeclarationSelector::byExternalReference(ExternalReference::fromString('declaration-1')),
            CommandSelectors::declarationSelector(self::input(['--declaration' => 'declaration-1'])),
        );
    }

    public function testADeclarationIsDesignatedByItsRecordId(): void
    {
        static::assertEquals(
            DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')),
            CommandSelectors::declarationSelector(self::input(['--record-id' => 'record-1'])),
        );
    }

    /**
     * @param array<string, string> $options
     */
    #[DataProvider('refusedSelectors')]
    public function testNoneOrBothOptionsAreRefused(array $options): void
    {
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('Give one of --declaration or --record-id.');

        CommandSelectors::declarationSelector(self::input($options));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function refusedSelectors(): iterable
    {
        yield 'none' => [[]];
        yield 'both' => [['--declaration' => 'a', '--record-id' => 'b']];
    }

    public function testAnInvalidValueIsRefusedByTheValueObject(): void
    {
        $this->expectException(InvalidValue::class);

        CommandSelectors::declarationSelector(self::input(['--record-id' => '']));
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private static function input(array $arguments): ArrayInput
    {
        $command = new Command('test');
        CommandSelectors::addDeclarationSelector($command);

        return new ArrayInput($arguments, $command->getDefinition());
    }
}
