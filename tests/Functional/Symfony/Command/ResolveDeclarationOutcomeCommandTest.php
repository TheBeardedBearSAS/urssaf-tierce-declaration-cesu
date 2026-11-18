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
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class ResolveDeclarationOutcomeCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:declarations:resolve';

    public function testADeclarationUrssafHasIsSettledWithItsIdentifier(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--declaration' => Scenario::DECLARATION, '--declared-as' => '209299542888']));

        static::assertSame([
            'Record id' => $submission->declaration->recordId,
            'External reference' => Scenario::DECLARATION,
            'State' => 'declared',
            'Declaration id' => '209299542888',
            'Attempts' => '1',
            'Error codes' => '-',
            'Updated at' => '2026-11-15T10:00:00Z',
        ], Fields::of($command->getDisplay()));
        static::assertStringNotContainsString('Send the declaration again', $command->getDisplay());
    }

    public function testADeclarationUrssafNeverReceivedIsToBeSentAgain(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--record-id' => $submission->declaration->recordId, '--not-received' => true, '--tenant' => 'tenant-1']));

        $fields = Fields::of($command->getDisplay());
        static::assertSame('pending', $fields['State']);
        static::assertSame('-', $fields['Declaration id']);
        static::assertSame('-', $fields['Error codes']);
        static::assertStringContainsString('Send the declaration again with DeclareSalary (same external reference and content).', $command->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndChangesNothing(array $input, string $message): void
    {
        $harness = Harness::boot();
        Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        $selector = ['--declaration' => Scenario::DECLARATION];

        yield 'no selector' => [['--not-received' => true], 'Give one of --declaration or --record-id.'];
        yield 'two selectors' => [['--declaration' => Scenario::DECLARATION, '--record-id' => 'a', '--not-received' => true], 'Give one of --declaration or --record-id.'];
        yield 'no resolution' => [$selector, 'Give one of --declared-as or --not-received.'];
        yield 'two resolutions' => [$selector + ['--declared-as' => '1', '--not-received' => true], 'Give one of --declared-as or --not-received.'];
        yield 'empty declaration id' => [$selector + ['--declared-as' => ''], '(declaration_id.empty)'];
        yield 'empty declaration' => [['--declaration' => '', '--not-received' => true], '(external_reference.empty)'];
        yield 'empty record id' => [['--record-id' => '', '--not-received' => true], '(declaration_record_id.empty)'];
    }

    public function testAnUnknownDeclarationExitsWithOne(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(1, $command->execute(['--declaration' => 'unknown', '--not-received' => true]));
    }

    public function testADeclarationThatIsNotOutcomeUnknownExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--declaration' => Scenario::DECLARATION, '--not-received' => true]));
    }

    public function testAnIdentifierAlreadyUsedByAnotherDeclarationExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness, 'declaration-1');
        $unknown = Scenario::outcomeUnknownDeclaration($harness, 'declaration-2');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--record-id' => $unknown->declaration->recordId, '--declared-as' => '209299542888']));
    }
}
