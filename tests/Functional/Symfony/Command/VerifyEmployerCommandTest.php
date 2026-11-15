<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

final class VerifyEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:verify-employer';
    private const EMPLOYER = 'Z0003752390009';

    public function testTheEmployerIsVerifiedWithTheBirthDateOfTheHiddenPrompt(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-01-30']);

        static::assertSame(0, $command->execute(['cesu-number' => self::EMPLOYER]));

        static::assertSame([
            'Registered' => 'yes',
            'Online account' => 'absent',
            'SEPA mandate' => 'absent',
            'Mandate present' => 'no',
            'Cesu+ state' => 'activé',
            'Cesu+ blocking reason' => '-',
            'AICI active' => 'yes',
            'Employee reference' => '-',
            'Checked at' => '2026-11-15T10:00:00Z',
        ], Fields::of($command->getDisplay()));
        static::assertStringContainsString('Employer birth date (YYYY-MM-DD): ', $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testAnEmployeeGivenByCesuNumberIsCheckedToo(): void
    {
        $command = Harness::boot()->command(self::NAME);
        $command->setInputs(['1975-01-30']);

        static::assertSame(0, $command->execute(['cesu-number' => self::EMPLOYER, '--employee-cesu-number' => 'Z0003752390009', '--tenant' => 'tenant-1']));

        static::assertSame('cesu_number', Fields::of($command->getDisplay())['Employee reference']);
    }

    public function testAnEmployeeGivenByNirIsAskedInASecondHiddenPrompt(): void
    {
        $command = Harness::boot()->command(self::NAME);
        $command->setInputs(['1975-01-30', EmployeeSamples::NIR]);

        static::assertSame(0, $command->execute(['cesu-number' => self::EMPLOYER, '--employee-nir' => true]));

        static::assertSame('nir', Fields::of($command->getDisplay())['Employee reference']);
        static::assertStringContainsString('Employee NIR: ', $command->getDisplay());
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
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        $cesu = ['cesu-number' => self::EMPLOYER];

        yield 'both employee options' => [$cesu + ['--employee-cesu-number' => 'Z0003752390009', '--employee-nir' => true], ['1975-01-30'], [], 'Give either --employee-cesu-number or --employee-nir, not both.'];
        yield 'non interactive' => [$cesu, [], ['interactive' => false], 'Employer birth date (YYYY-MM-DD) is read from a hidden prompt: run the command interactively.'];
        yield 'empty answer' => [$cesu, [''], [], '(calendar_date.invalid_format)'];
        yield 'no answer' => [$cesu, [], [], 'Employer birth date (YYYY-MM-DD) was not given.'];
        yield 'malformed birth date' => [$cesu, ['30/01/1975'], [], 'Invalid calendar_date: the value does not match the expected format (Y-m-d). (calendar_date.invalid_format)'];
        yield 'impossible birth date' => [$cesu, ['1975-02-30'], [], 'Invalid calendar_date: the date does not exist. (calendar_date.invalid_date)'];
        yield 'malformed Cesu number' => [['cesu-number' => 'abc'], ['1975-01-30'], [], 'Invalid cesu_number: the value does not match the expected format (14 alphanumeric characters). (cesu_number.invalid_format)'];
        yield 'no NIR answer' => [$cesu + ['--employee-nir' => true], ['1975-01-30'], [], 'Employee NIR was not given.'];
    }

    public function testTheTenantIsRequiredWhenSeveralAreConfigured(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['cesu-number' => self::EMPLOYER]));

        static::assertStringContainsString('The --tenant option is required (configured tenants: 2).', $command->getDisplay());
    }

    public function testAnEmployeeWithoutAnythingToIdentifyItIsRefusedByTheDomain(): void
    {
        $command = Harness::boot()->command(self::NAME);
        $command->setInputs(['1975-01-30', 'short']);

        static::assertSame(2, $command->execute(['cesu-number' => self::EMPLOYER, '--employee-nir' => true]));

        static::assertMatchesRegularExpression('/Invalid input: \d+ error\(s\)\./', $command->getDisplay());
        static::assertStringNotContainsString('short', $command->getDisplay());
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_020, 400, 'error_param_invalide.json');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-01-30']);

        static::assertSame(1, $command->execute(['cesu-number' => self::EMPLOYER]));
        static::assertStringContainsString('PARAM_INVALIDE', $command->getDisplay());
    }

    public function testAnAuthenticationFailureExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-01-30']);

        static::assertSame(1, $command->execute(['cesu-number' => self::EMPLOYER]));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testAnUnavailablePlatformExitsWithOne(): void
    {
        $harness = Harness::boot();
        $harness->urssaf->respond(HttpScript::ROUTE_020, 503, '{}');
        $command = $harness->command(self::NAME);
        $command->setInputs(['1975-01-30']);

        static::assertSame(1, $command->execute(['cesu-number' => self::EMPLOYER]));
        static::assertStringContainsString('is temporarily unavailable (HTTP 503)', $command->getDisplay());
    }
}
