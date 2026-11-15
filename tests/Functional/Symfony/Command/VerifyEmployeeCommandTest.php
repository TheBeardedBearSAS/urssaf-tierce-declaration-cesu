<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

final class VerifyEmployeeCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:verify-employee';

    public function testAnEmployeeIsVerifiedByCesuNumber(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['cesu-number' => 'Z0003752390009']));

        static::assertSame(['Registered' => 'yes', 'Cesu number' => 'Z0003752390009'], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_021));
    }

    public function testAnEmployeeIsVerifiedByNirAskedInAHiddenPrompt(): void
    {
        $command = Harness::boot()->command(self::NAME);
        $command->setInputs([EmployeeSamples::NIR]);

        static::assertSame(0, $command->execute(['--nir' => true, '--tenant' => 'tenant-1']));

        static::assertSame('yes', Fields::of($command->getDisplay())['Registered']);
        static::assertStringContainsString('Employee NIR: ', $command->getDisplay());
        static::assertStringNotContainsString(EmployeeSamples::NIR, $command->getDisplay());
    }

    /**
     * @param array<string, mixed> $input
     * @param list<string>         $answers
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndNeverCallsUrssaf(array $input, array $answers, array $options, string $message): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs($answers);

        static::assertSame(2, $command->execute($input, $options));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_021));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        yield 'nothing to identify the employee' => [[], [], [], 'Give either the cesu-number argument or --nir.'];
        yield 'both' => [['cesu-number' => 'Z0003752390009', '--nir' => true], [EmployeeSamples::NIR], [], 'Give either the cesu-number argument or --nir.'];
        yield 'non interactive' => [['--nir' => true], [], ['interactive' => false], 'Employee NIR is read from a hidden prompt: run the command interactively.'];
        yield 'malformed Cesu number' => [['cesu-number' => 'abc'], [], [], 'Invalid input:'];
        yield 'malformed NIR' => [['--nir' => true], ['too short'], [], 'Invalid input:'];
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_021, 400, 'error_param_invalide.json');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['cesu-number' => 'Z0003752390009']));
    }
}
