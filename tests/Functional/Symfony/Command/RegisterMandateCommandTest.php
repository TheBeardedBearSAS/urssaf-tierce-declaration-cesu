<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class RegisterMandateCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:mandate:register';

    /**
     * @param array<string, string> $selector
     */
    #[DataProvider('selectors')]
    public function testTheMandateOfATrackedEmployerIsNotifiedWhateverTheSelector(array $selector): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(array_map(static fn(string $value): string => $value === '{account}' ? $account->accountId : $value, $selector)));

        $fields = Fields::of($command->getDisplay());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $fields['Mandate id']);
        unset($fields['Mandate id']);
        static::assertSame([
            'External reference' => Scenario::EMPLOYER,
            'Cesu number' => 'Z0003752390009',
            'State' => 'notified',
            'Notified at' => '2026-11-15T10:00:00Z',
            'Confirmed at' => '-',
            'Cancelled at' => '-',
        ], $fields);
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_030));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function selectors(): iterable
    {
        yield 'external reference' => [['--employer' => Scenario::EMPLOYER]];
        yield 'Cesu number' => [['--cesu-number' => 'Z0003752390009', '--tenant' => 'tenant-1']];
        yield 'account id' => [['--account-id' => '{account}']];
    }

    /**
     * @param array<string, string> $input
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndNeverCallsUrssaf(array $input, string $message): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_030));
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidUsages(): iterable
    {
        yield 'no selector' => [[], 'Give one of --employer, --cesu-number or --account-id.'];
        yield 'two selectors' => [['--employer' => 'employer-1', '--cesu-number' => 'Z0003752390009'], 'Give one of --employer, --cesu-number or --account-id.'];
        yield 'three selectors' => [['--employer' => 'employer-1', '--cesu-number' => 'Z0003752390009', '--account-id' => 'a'], 'Give one of --employer, --cesu-number or --account-id.'];
        yield 'malformed Cesu number' => [['--cesu-number' => 'x'], '(cesu_number.invalid_format)'];
        yield 'empty account id' => [['--account-id' => ''], '(employer_account_id.empty)'];
    }

    public function testAnUnknownEmployerExitsWithOne(): void
    {
        $harness = Harness::boot();
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => 'unknown']));

        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_030));
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_030, 400, 'error_param_invalide.json');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER]));
    }

    public function testAnAuthenticationFailureExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER]));
    }

    public function testAnUnavailablePlatformExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_030, 503, '{}');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER]));
        static::assertStringContainsString('is temporarily unavailable (HTTP 503)', $command->getDisplay());
    }
}
