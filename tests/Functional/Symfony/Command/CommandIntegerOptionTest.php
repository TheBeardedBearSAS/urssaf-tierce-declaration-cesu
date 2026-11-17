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
use Symfony\Component\Console\Input\InputOption;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\CommandOptions;

final class CommandIntegerOptionTest extends TestCase
{
    #[DataProvider('integers')]
    public function testAnIntegerOptionIsNonNegative(?string $value, ?int $expected): void
    {
        $command = new Command('test');
        $command->addOption('hours', null, InputOption::VALUE_REQUIRED);

        static::assertSame($expected, CommandOptions::integer(new ArrayInput($value === null ? [] : ['--hours' => $value], $command->getDefinition()), 'hours'));
    }

    /**
     * @return iterable<string, array{?string, ?int}>
     */
    public static function integers(): iterable
    {
        yield 'none' => [null, null];
        yield 'zero' => ['0', 0];
        yield 'seven' => ['7', 7];
        yield 'the largest accepted' => ['999999999', 999_999_999];
    }

    #[DataProvider('invalidIntegers')]
    public function testAnInvalidIntegerIsRefused(string $value): void
    {
        $command = new Command('test');
        $command->addOption('hours', null, InputOption::VALUE_REQUIRED);
        $this->expectException(InvalidOptionException::class);
        $this->expectExceptionMessage('The --hours option must be a non-negative integer.');

        CommandOptions::integer(new ArrayInput(['--hours' => $value], $command->getDefinition()), 'hours');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIntegers(): iterable
    {
        yield 'negative' => ['-1'];
        yield 'text' => ['x'];
        yield 'decimal' => ['1.5'];
        yield 'leading zero' => ['00'];
        yield 'leading zero before a digit' => ['01'];
        yield 'empty' => [''];
        yield 'trailing newline' => ["0\n"];
        yield 'too large' => ['1000000000'];
    }
}
