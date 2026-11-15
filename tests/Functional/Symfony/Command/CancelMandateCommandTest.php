<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class CancelMandateCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:mandate:cancel';

    public function testTheMandateIsCancelledAfterTheConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['yes']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER]));

        $fields = Fields::of($command->getDisplay());
        static::assertSame('cancelled', $fields['State']);
        static::assertSame('2026-11-15T10:00:00Z', $fields['Cancelled at']);
        static::assertStringContainsString(\sprintf('Cancel the mandate of employer %s at URSSAF? [y/N] ', Scenario::EMPLOYER), $command->getDisplay());
        static::assertSame(1, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    /**
     * @param list<string> $answers
     */
    #[DataProvider('refusals')]
    public function testNothingIsSentWithoutAConfirmation(array $answers): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs($answers);

        static::assertSame(0, $command->execute(['--cesu-number' => 'Z0003752390009']));

        static::assertStringContainsString('Aborted: nothing was sent to URSSAF.', $command->getDisplay());
        static::assertStringNotContainsString('| State', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    /**
     * @return iterable<string, array{list<string>}>
     */
    public static function refusals(): iterable
    {
        yield 'no' => [['no']];
        yield 'default answer' => [['']];
    }

    public function testForceSkipsTheConfirmationEvenWhenNotInteractive(): void
    {
        $harness = Harness::boot();
        $account = Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--account-id' => $account->accountId, '--force' => true, '--tenant' => 'tenant-1'], ['interactive' => false]));

        static::assertSame('cancelled', Fields::of($command->getDisplay())['State']);
        static::assertStringNotContainsString('Cancel the mandate', $command->getDisplay());
    }

    public function testWithoutForceANonInteractiveRunIsRefused(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['--employer' => Scenario::EMPLOYER], ['interactive' => false]));

        static::assertStringContainsString('Cancelling a mandate needs a confirmation: use --force when not interactive.', $command->getDisplay());
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testTheEmployerSelectorIsRequired(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--force' => true]));

        static::assertStringContainsString('Give one of --employer, --cesu-number or --account-id.', $command->getDisplay());
    }

    public function testAnEmployerWithoutMandateExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
        static::assertSame(0, $harness->urssaf->calls(HttpScript::ROUTE_031));
    }

    public function testARefusalOfUrssafExitsWithOne(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $harness->urssaf->respondWithFixture(HttpScript::ROUTE_031, 400, 'error_param_invalide.json');
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
    }
}
