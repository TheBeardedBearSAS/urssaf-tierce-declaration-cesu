<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Infrastructure\Urssaf\Fake\CapturingLogger;

/**
 * ADR 0007: the employer birth date, the employee NIR and the names of the fixtures never reach the console output
 * (normal or debug verbosity), also when the command fails, nor the logs.
 */
final class PersonalDataOutputTest extends TestCase
{
    private const BIRTH_DATE = '1975-01-30';
    private const NIR = EmployeeSamples::NIR;
    private const BAD_BIRTH_DATE = '1975-14-45';
    private const BAD_NIR = 'SECRETNIR12345';

    /** What must never be printed or logged. */
    private const PERSONAL = [self::BIRTH_DATE, self::NIR, self::BAD_BIRTH_DATE, self::BAD_NIR, 'Dupond', 'Pierre', '30/01/1975'];

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('verbosities')]
    public function testNoCommandPrintsPersonalData(array $options): void
    {
        $harness = Harness::boot();
        $displays = [];

        $verify = $harness->command('urssaf:cesu:verify-employer');
        $verify->setInputs([self::BIRTH_DATE, self::NIR]);
        $verify->execute(['cesu-number' => 'Z0003752390009', '--employee-nir' => true], $options);
        $displays[] = $verify->getDisplay();

        $employee = $harness->command('urssaf:cesu:verify-employee');
        $employee->setInputs([self::NIR]);
        $employee->execute(['--nir' => true], $options);
        $displays[] = $employee->getDisplay();

        $import = $harness->command('urssaf:cesu:employer:import');
        $import->setInputs([self::BIRTH_DATE]);
        $import->execute(['cesu-number' => 'Z0003752390009', '--reference' => Scenario::EMPLOYER], $options);
        $displays[] = $import->getDisplay();

        foreach (['urssaf:cesu:mandate:register' => [], 'urssaf:cesu:mandate:cancel' => ['--force' => true], 'urssaf:cesu:employers:sync-status' => ['--tenant' => 'tenant-1']] as $name => $extra) {
            $command = $harness->command($name);
            $command->execute($extra + ['--employer' => Scenario::EMPLOYER], $options);
            $displays[] = $command->getDisplay();
        }
        $harness->urssaf->respond(HttpScript::ROUTE_050, 500, '{}');
        $unknown = Scenario::outcomeUnknownDeclaration($harness);
        $resolve = $harness->command('urssaf:cesu:declarations:resolve');
        $resolve->execute(['--record-id' => $unknown->declaration->recordId, '--not-received' => true], $options);
        $displays[] = $resolve->getDisplay();

        $forget = $harness->command('urssaf:cesu:employer:forget');
        $forget->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true, '--allow-unknown-outcomes' => true], $options);
        $displays[] = $forget->getDisplay();

        self::assertNothingPersonal(implode("\n", $displays) . $harness->privateService('logger', CapturingLogger::class)->dump());
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('verbosities')]
    public function testNoFailureMessagePrintsPersonalData(array $options): void
    {
        $harness = Harness::boot();
        $displays = [];

        $invalid = $harness->command('urssaf:cesu:verify-employer');
        $invalid->setInputs([self::BAD_BIRTH_DATE]);
        $invalid->execute(['cesu-number' => 'Z0003752390009'], $options);
        $displays[] = $invalid->getDisplay();

        $invalidNir = $harness->command('urssaf:cesu:verify-employee');
        $invalidNir->setInputs([self::BAD_NIR]);
        $invalidNir->execute(['--nir' => true], $options);
        $displays[] = $invalidNir->getDisplay();

        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_020, 400, 'error_param_invalide.json');
        $refused = $harness->command('urssaf:cesu:verify-employer');
        $refused->setInputs([self::BIRTH_DATE, self::NIR]);
        $refused->execute(['cesu-number' => 'Z0003752390009', '--employee-nir' => true], $options);
        $displays[] = $refused->getDisplay();

        $harness->urssaf->respond(HttpScript::ROUTE_021, 503, '{}');
        $unavailable = $harness->command('urssaf:cesu:verify-employee');
        $unavailable->setInputs([self::NIR]);
        $unavailable->execute(['--nir' => true], $options);
        $displays[] = $unavailable->getDisplay();

        $nonInteractive = $harness->command('urssaf:cesu:employer:import');
        $nonInteractive->execute(['cesu-number' => 'Z0003752390009', '--reference' => 'employer-1'], ['interactive' => false] + $options);
        $displays[] = $nonInteractive->getDisplay();

        self::assertNothingPersonal(implode("\n", $displays) . $harness->privateService('logger', CapturingLogger::class)->dump());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function verbosities(): iterable
    {
        yield 'normal' => [[]];
        yield 'debug (-vvv)' => [['verbosity' => OutputInterface::VERBOSITY_DEBUG]];
    }

    private static function assertNothingPersonal(string $text): void
    {
        foreach (self::PERSONAL as $personal) {
            static::assertStringNotContainsString($personal, $text);
        }
        static::assertStringContainsString('Employer birth date (YYYY-MM-DD): ', $text, 'The commands did print something.');
    }
}
