<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncAllEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusesHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\SyncEmployerStatusHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Policy\WorkflowPolicy;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Command\SyncEmployerStatusesCommand;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Lock\OperationLocks;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\Message\SyncEmployerStatusesMessage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\RecordingMessageBus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

/**
 * @mago-expect lint:too-many-methods Each mode and exit case of the command is one test method.
 */
final class SyncEmployerStatusesCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:employers:sync-status';

    /**
     * @param array<string, string> $selector
     */
    #[DataProvider('selectors')]
    public function testOneEmployerIsRefreshedWhateverTheSelector(array $selector): void
    {
        $harness = Harness::boot();
        $account = Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-1'] + array_map(static fn(string $value): string => $value === '{account}' ? $account->accountId : $value, $selector)));

        static::assertSame([
            'Account id' => $account->accountId,
            'External reference' => Scenario::EMPLOYER,
            'Cesu number' => 'Z0003752390009',
            'Stage' => 'mandate_notified',
            'Mandate present' => 'no',
            'Mandate state' => 'notified',
            'Last checked at' => '2026-11-15T10:00:00Z',
        ], Fields::of($command->getDisplay()));
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function selectors(): iterable
    {
        yield 'external reference' => [['--employer' => Scenario::EMPLOYER]];
        yield 'Cesu number' => [['--cesu-number' => 'Z0003752390009']];
        yield 'account id' => [['--account-id' => '{account}']];
    }

    public function testTheAccountOfAnEmployerNeverCheckedShowsDashes(): void
    {
        $harness = Harness::boot();
        $account = Scenario::importedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_020, 200, HttpScript::fixture('verify_employer_response.json'));
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-1', '--employer' => Scenario::EMPLOYER]));

        static::assertSame('-', Fields::of($command->getDisplay())['Mandate state']);
        static::assertSame($account->accountId, Fields::of($command->getDisplay())['Account id']);
    }

    public function testAnEmployerSelectorNeedsATenant(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['--employer' => Scenario::EMPLOYER]));

        static::assertStringContainsString('The --tenant option is required with an employer selector.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidUsages')]
    public function testAnInvalidUsageExitsWithTwoAndNeverCallsUrssaf(array $input, string $message): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute($input));

        static::assertStringContainsString($message, $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidUsages(): iterable
    {
        $selector = ['--tenant' => 'tenant-1', '--employer' => Scenario::EMPLOYER];
        $combination = 'An employer selector cannot be combined with --limit, --dry-run or --async.';

        yield 'selector and limit' => [$selector + ['--limit' => '5'], $combination];
        yield 'selector and dry run' => [$selector + ['--dry-run' => true], $combination];
        yield 'selector and async' => [$selector + ['--async' => true], $combination];
        yield 'two selectors' => [$selector + ['--cesu-number' => 'Z0003752390009'], 'Give one of --employer, --cesu-number or --account-id.'];
        yield 'invalid limit' => [['--limit' => 'many'], 'The --limit option must be a positive integer.'];
        yield 'zero limit' => [['--limit' => '0'], 'The --limit option must be a positive integer.'];
        yield 'async without messenger' => [['--async' => true], '--async requires "messenger.enabled".'];
        yield 'async and dry run' => [['--async' => true, '--dry-run' => true], '--async cannot be combined with --dry-run.'];
    }

    public function testTheTenantsAreRefreshedWithAReportOfTheOutcomes(): void
    {
        $harness = Harness::boot();
        $account = Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-1']));

        static::assertSame(['Record', 'Outcome', 'Codes'], Output::rows($command->getDisplay())[0]);
        static::assertSame([$account->accountId, 'changed'], \array_slice(Output::rows($command->getDisplay())[1], 0, 2));
        static::assertStringContainsString('1 record(s) processed (changed: 1).', $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testEveryTenantIsRefreshedWithoutTenantOption(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--limit' => '5']));

        static::assertStringContainsString('1 record(s) processed', $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testTheLimitCapsTheEmployersRefreshed(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness, 'tenant-1', 'employer-1');
        Accounts::import($harness, 'tenant-1', 'employer-2', 'Z0003752390010');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-1', '--limit' => '1']));

        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testADryRunListsTheEmployersWithoutCallingUrssaf(): void
    {
        $harness = Harness::boot();
        $account = Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertSame([['Tenant', 'Record'], ['tenant-1', $account->accountId]], Output::rows($command->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s) would be processed, URSSAF was not called.', $command->getDisplay());
        static::assertSame(0, $command->execute(['--dry-run' => true, '--tenant' => 'tenant-2', '--limit' => '3']));
        static::assertStringContainsString('Dry run: 0 record(s)', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_020));
    }

    public function testADryRunHonoursTheLimit(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness, 'tenant-1', 'employer-1');
        Accounts::import($harness, 'tenant-1', 'employer-2', 'Z0003752390010');
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true, '--limit' => '1']));

        static::assertStringContainsString('Dry run: 1 record(s)', $command->getDisplay());
    }

    public function testOneMessagePerTenantIsDispatchedWithAsync(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness, 'tenant-1', 'employer-1');
        Accounts::import($harness, 'tenant-2', 'employer-2', 'Z0003752390010');
        $bus = new RecordingMessageBus();
        $command = new CommandTester(self::command($harness, $bus));

        static::assertSame(0, $command->execute(['--async' => true]));

        static::assertEquals([new SyncEmployerStatusesMessage('tenant-1'), new SyncEmployerStatusesMessage('tenant-2')], $bus->messages());
        static::assertStringContainsString('2 message(s) dispatched.', $command->getDisplay());
        static::assertSame(0, $command->execute(['--async' => true, '--tenant' => 'tenant-9']));
        static::assertStringContainsString('1 message(s) dispatched.', $command->getDisplay());
        static::assertEquals(new SyncEmployerStatusesMessage('tenant-9'), $bus->messages()[2]);
        static::assertSame([], $harness->client->getRequests());
    }

    public function testAnAsyncRunWithoutBusIsRefused(): void
    {
        $harness = Harness::boot();
        $command = new CommandTester(self::command($harness, null));

        static::assertSame(2, $command->execute(['--async' => true]));

        static::assertStringContainsString('--async requires "messenger.enabled".', $command->getDisplay());
    }

    public function testTheBusOfTheBundleIsGivenToTheCommandWhenMessengerIsEnabled(): void
    {
        $harness = Harness::boot(['messenger' => ['enabled' => true]], true);
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--async' => true]));

        static::assertStringContainsString('1 message(s) dispatched.', $command->getDisplay());
        static::assertSame([], $harness->client->getRequests());
    }

    public function testAnUnknownEmployerExitsWithOne(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-1', '--employer' => 'unknown']));
    }

    public function testAnUnavailablePlatformMakesTheBatchExitWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $harness->urssaf->respond(HttpScript::ROUTE_020, 503, '{}');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-1']));

        static::assertStringContainsString('URSSAF method 020 is temporarily unavailable (HTTP 503).', $command->getDisplay());
    }

    public function testARefusedAuthenticationMakesTheSingleRefreshExitWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $harness->clock->modify('+2 hours');
        $harness->urssaf->respond(HttpScript::ROUTE_AUTHENTICATION, 401, '{"error":"invalid_client"}');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--tenant' => 'tenant-1', '--employer' => Scenario::EMPLOYER]));
    }

    private static function command(Harness $harness, ?RecordingMessageBus $bus): SyncEmployerStatusesCommand
    {
        return new SyncEmployerStatusesCommand(
            $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class),
            $harness->get(SyncEmployerStatusHandler::class),
            $harness->get(SyncEmployerStatusesHandler::class),
            $harness->get(SyncAllEmployerStatusesHandler::class),
            $harness->privateService(WorkflowPolicy::class, WorkflowPolicy::class),
            $harness->privateService(OperationLocks::class, OperationLocks::class),
            $bus,
        );
    }
}
