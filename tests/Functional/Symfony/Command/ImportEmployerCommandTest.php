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
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

final class ImportEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:employer:import';
    private const EMPLOYER = 'Z0003752390009';

    public function testAnExistingEmployerIsTrackedWithoutCallingUrssaf(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-01-30']);

        static::assertSame(0, $command->execute(['cesu-number' => self::EMPLOYER, '--reference' => 'employer-1']));

        $fields = Fields::of($command->getDisplay());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $fields['Account id']);
        unset($fields['Account id']);
        static::assertSame([
            'External reference' => 'employer-1',
            'Cesu number' => self::EMPLOYER,
            'Origin' => 'imported',
            'Stage' => 'registered',
            'Created at' => '2026-11-15T10:00:00Z',
        ], $fields);
        static::assertSame([], $harness->client->getRequests());
    }

    public function testTheSameEmployerImportedAgainPrintsTheSameAccount(): void
    {
        $harness = Harness::boot();
        $first = $harness->command(self::NAME);
        $first->setInputs(['1975-01-30']);
        $first->execute(['cesu-number' => self::EMPLOYER, '--reference' => 'employer-1']);
        $second = $harness->command(self::NAME);
        $second->setInputs(['1975-01-30']);

        static::assertSame(0, $second->execute(['cesu-number' => self::EMPLOYER, '--reference' => 'employer-1', '--tenant' => 'tenant-1']));

        static::assertSame(Fields::of($first->getDisplay()), Fields::of($second->getDisplay()));
    }

    public function testAnEmployerAlreadyTrackedUnderAnotherReferenceExitsWithOne(): void
    {
        $harness = Harness::boot();
        $first = $harness->command(self::NAME);
        $first->setInputs(['1975-01-30']);
        $first->execute(['cesu-number' => self::EMPLOYER, '--reference' => 'employer-1']);
        $second = $harness->command(self::NAME);
        $second->setInputs(['1975-01-30']);

        static::assertSame(1, $second->execute(['cesu-number' => self::EMPLOYER, '--reference' => 'employer-2']));
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string>         $answers
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwo(array $input, array $answers, array $options, string $message): void
    {
        $command = Harness::boot()->command(self::NAME);
        $command->setInputs($answers);

        static::assertSame(2, $command->execute($input, $options));

        static::assertStringContainsString($message, $command->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        $input = ['cesu-number' => self::EMPLOYER, '--reference' => 'employer-1'];

        yield 'no reference' => [['cesu-number' => self::EMPLOYER], ['1975-01-30'], [], 'The --reference option is required.'];
        yield 'non interactive' => [$input, [], ['interactive' => false], 'Employer birth date (YYYY-MM-DD) is read from a hidden prompt: run the command interactively.'];
        yield 'invalid birth date' => [$input, ['tomorrow'], [], '(calendar_date.invalid_format)'];
        yield 'invalid Cesu number' => [['cesu-number' => 'x'] + $input, ['1975-01-30'], [], '(cesu_number.invalid_format)'];
        yield 'empty reference' => [['--reference' => ''] + $input, ['1975-01-30'], [], '(external_reference.empty)'];
    }
}
