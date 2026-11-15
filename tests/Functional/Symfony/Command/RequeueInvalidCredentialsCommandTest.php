<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Output;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

final class RequeueInvalidCredentialsCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:requeue-invalid-credentials';

    public function testTheDeclarationsRefusedForInvalidCredentialsAreRequeued(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::invalidCredentialsDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute([]));

        static::assertSame([['Records', 'Requeued'], ['declarations', '1']], Output::rows($command->getDisplay()));
        static::assertStringContainsString('The requeued declarations are pending: send them again with DeclareSalary.', $command->getDisplay());
        static::assertSame(DeclarationState::Pending, self::state($harness, $submission->declaration->recordId));
    }

    public function testNothingIsRequeuedForAnotherTenant(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        $submission = Scenario::invalidCredentialsDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--tenant' => 'tenant-2', '--limit' => '3']));

        static::assertSame([['Records', 'Requeued'], ['declarations', '0']], Output::rows($command->getDisplay()));
        static::assertSame(DeclarationState::InvalidCredentials, self::state($harness, $submission->declaration->recordId));
    }

    public function testADryRunListsTheDeclarationsAndChangesNothing(): void
    {
        $harness = Harness::boot();
        $submission = Scenario::invalidCredentialsDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true]));

        static::assertSame([['Tenant', 'Record'], ['tenant-1', $submission->declaration->recordId]], Output::rows($command->getDisplay()));
        static::assertStringContainsString('Dry run: 1 record(s) would be processed, URSSAF was not called.', $command->getDisplay());
        static::assertSame(DeclarationState::InvalidCredentials, self::state($harness, $submission->declaration->recordId));
    }

    public function testADryRunHonoursTheLimitAndTheTenant(): void
    {
        $harness = Harness::boot(Accounts::withSecondTenant());
        Scenario::invalidCredentialsDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--dry-run' => true, '--tenant' => 'tenant-2', '--limit' => '1']));

        static::assertStringContainsString('Dry run: 0 record(s)', $command->getDisplay());
    }

    public function testTheTenantIsRequiredWhenSeveralAreConfigured(): void
    {
        $command = Harness::boot(Accounts::withSecondTenant())->command(self::NAME);

        static::assertSame(2, $command->execute([]));

        static::assertStringContainsString('The --tenant option is required (configured tenants: 2).', $command->getDisplay());
    }

    public function testAnInvalidLimitExitsWithTwo(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--limit' => 'abc']));
    }

    private static function state(Harness $harness, string $recordId): DeclarationState
    {
        return $harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)->get(DeclarationRecordId::fromString($recordId))->status()->state();
    }
}
