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
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Outbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\OutboxEntry;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\EmployerForgotten;
use TheBeardedBear\UrssafTierceDeclarationCesu\Contract\Event\IntegrationEvent;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccountRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\Mandate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Mandate\MandateRepository;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\TenantId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Outbox\InMemoryOutbox;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\HttpScript;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Scenario;

/**
 * Lot 10 design §8.4: the console entry point of ForgetEmployer. Nothing is ever sent to URSSAF.
 *
 * @mago-expect lint:too-many-methods
 */
final class ForgetEmployerCommandTest extends TestCase
{
    private const NAME = 'urssaf:cesu:employer:forget';
    private const CESU_NUMBER = 'Z0003752390009';
    private const NOTHING_SENT = 'Erased from the local store only: nothing was sent to URSSAF.';

    public function testTheEmployerIsErasedAfterTheConfirmation(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $accountId = self::account($harness)?->id()->value();
        $calls = self::calls($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs(['yes']);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--allow-active-mandate' => true]));

        $display = $command->getDisplay();
        $fields = Fields::of($display);
        static::assertSame($accountId, $fields['Account id']);
        static::assertSame('1', $fields['Mandates erased']);
        static::assertSame('1', $fields['Declarations erased']);
        static::assertStringContainsString(\sprintf('Erase employer %s, its mandates and its declaration records from the local store? This cannot be undone and nothing is sent to URSSAF. [y/N] ', Scenario::EMPLOYER), $display);
        static::assertStringContainsString(self::NOTHING_SENT, $display);
        self::assertErased($harness);
        static::assertSame($calls, self::calls($harness));
    }

    /**
     * @param list<string> $answers
     */
    #[DataProvider('refusals')]
    public function testNothingIsErasedWithoutAConfirmation(array $answers): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);
        $command->setInputs($answers);

        static::assertSame(0, $command->execute(['--cesu-number' => self::CESU_NUMBER]));

        static::assertStringContainsString('Aborted: nothing was erased.', $command->getDisplay());
        static::assertStringNotContainsString('| Account id', $command->getDisplay());
        static::assertStringNotContainsString(self::NOTHING_SENT, $command->getDisplay());
        static::assertNotNull(self::account($harness));
        static::assertCount(1, self::mandates($harness));
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
        $account = Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--account-id' => $account->accountId, '--force' => true, '--tenant' => Scenario::TENANT], ['interactive' => false]));

        static::assertSame('0', Fields::of($command->getDisplay())['Mandates erased']);
        static::assertStringNotContainsString('Erase employer', $command->getDisplay());
        static::assertNull(self::account($harness));
    }

    public function testWithoutForceANonInteractiveRunIsRefused(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(2, $command->execute(['--employer' => Scenario::EMPLOYER], ['interactive' => false]));

        static::assertStringContainsString('Erasing an employer needs a confirmation: use --force when not interactive.', $command->getDisplay());
        static::assertNotNull(self::account($harness));
    }

    public function testTheEmployerSelectorIsRequired(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(2, $command->execute(['--force' => true]));

        static::assertStringContainsString('Give one of --employer, --cesu-number or --account-id.', $command->getDisplay());
    }

    public function testAnUnknownEmployerExitsWithOne(): void
    {
        $command = Harness::boot()->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => 'unknown', '--force' => true]));

        static::assertStringContainsString('No employer_account found for identifier "unknown".', $command->getDisplay());
    }

    public function testAnErasedEmployerIsNotFoundAnymore(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        static::assertSame(0, $harness->command(self::NAME)->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
        $second = $harness->command(self::NAME);

        static::assertSame(1, $second->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
    }

    public function testAnActiveMandateIsRefusedUnlessAllowed(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        $calls = self::calls($harness);
        $refused = $harness->command(self::NAME);

        static::assertSame(1, $refused->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));

        static::assertStringContainsString('The employer still has an active mandate', $refused->getDisplay());
        static::assertNotNull(self::account($harness));
        static::assertCount(1, self::mandates($harness));
        $allowed = $harness->command(self::NAME);

        static::assertSame(0, $allowed->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true]));

        self::assertErased($harness);
        static::assertSame($calls, self::calls($harness));
    }

    public function testAnUnknownOutcomeIsRefusedUnlessAllowed(): void
    {
        $harness = Harness::boot();
        Scenario::outcomeUnknownDeclaration($harness);
        $refused = $harness->command(self::NAME);

        static::assertSame(1, $refused->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true]));

        static::assertStringContainsString('have an unknown outcome', $refused->getDisplay());
        static::assertNotNull(self::account($harness));
        $allowed = $harness->command(self::NAME);

        static::assertSame(0, $allowed->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true, '--allow-unknown-outcomes' => true]));

        static::assertSame('1', Fields::of($allowed->getDisplay())['Declarations erased']);
        self::assertErased($harness);
    }

    public function testASubmissionInFlightIsAlwaysRefused(): void
    {
        $harness = Harness::boot();
        Scenario::mandatedEmployer($harness);
        Declarations::submittingNow($harness, Scenario::TENANT, Scenario::DECLARATION);
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true, '--allow-unknown-outcomes' => true]));

        static::assertStringContainsString('are being submitted', $command->getDisplay());
        static::assertNotNull(self::account($harness));
        static::assertCount(1, self::mandates($harness));
    }

    public function testTheWorkflowPolicyProvidesTheDefaultsOfTheOptIns(): void
    {
        $harness = Harness::boot(['workflow' => ['erasure' => ['allow_active_mandate' => true, 'allow_unknown_outcomes' => true]]]);
        Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));

        self::assertErased($harness);
    }

    public function testTheOptionsAreIndependentOfThePolicy(): void
    {
        $harness = Harness::boot(['workflow' => ['erasure' => ['allow_active_mandate' => true]]]);
        Scenario::outcomeUnknownDeclaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(1, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));

        static::assertStringContainsString('have an unknown outcome', $command->getDisplay());
        static::assertNotNull(self::account($harness));
    }

    public function testTheOnlyConfiguredTenantIsTheDefault(): void
    {
        $harness = Harness::boot();
        Scenario::importedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true]));
    }

    public function testNoPersonalDataIsPrinted(): void
    {
        $harness = Harness::boot();
        Scenario::declaration($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true], ['verbosity' => 256]));

        foreach (['1975-01-30', 'Dupond', 'Pierre', self::CESU_NUMBER] as $personal) {
            static::assertStringNotContainsString($personal, $command->getDisplay());
        }
    }

    public function testTheKeyRowOfTheMandateIsFreedWithDoctrine(): void
    {
        $harness = Harness::boot(['persistence' => ['driver' => 'doctrine']], false, false, true);
        $harness->createDatabaseSchema();
        Scenario::mandatedEmployer($harness);
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true]));

        static::assertSame([], self::mandates($harness));
        static::assertNull(self::account($harness));
        Scenario::mandatedEmployer($harness);
        static::assertCount(1, self::mandates($harness));
    }

    public function testTheErasureIsAppendedToTheOutbox(): void
    {
        $harness = Harness::boot(['outbox' => ['enabled' => true, 'sink' => 'test.sink']]);
        Scenario::mandatedEmployer($harness);
        $accountId = self::account($harness)?->id()->value();
        $command = $harness->command(self::NAME);

        static::assertSame(0, $command->execute(['--employer' => Scenario::EMPLOYER, '--force' => true, '--allow-active-mandate' => true]));

        $forgotten = array_values(array_filter(
            array_map(static fn(OutboxEntry $entry): IntegrationEvent => $entry->event, $harness->privateService(Outbox::class, InMemoryOutbox::class)->pending(100)),
            static fn(IntegrationEvent $event): bool => $event instanceof EmployerForgotten,
        ));
        static::assertCount(1, $forgotten);
        static::assertSame('urssaf.cesu.employer.forgotten', $forgotten[0]->type());
        static::assertSame($accountId, $forgotten[0]->aggregateId);
        static::assertSame(self::CESU_NUMBER, $forgotten[0]->cesuNumber);
        static::assertCount(1, $forgotten[0]->mandateIds);
    }

    private static function account(Harness $harness): ?EmployerAccount
    {
        return $harness->privateService(EmployerAccountRepository::class, EmployerAccountRepository::class)
            ->findByExternalReference(TenantId::fromString(Scenario::TENANT), ExternalReference::fromString(Scenario::EMPLOYER));
    }

    /**
     * @return list<Mandate>
     */
    private static function mandates(Harness $harness): array
    {
        return $harness->privateService(MandateRepository::class, MandateRepository::class)
            ->findByEmployer(TenantId::fromString(Scenario::TENANT), CesuNumber::fromString(self::CESU_NUMBER));
    }

    private static function assertErased(Harness $harness): void
    {
        static::assertNull(self::account($harness));
        static::assertSame([], self::mandates($harness));
        static::assertSame(
            0,
            array_sum($harness->privateService(DeclarationRecordRepository::class, DeclarationRecordRepository::class)->countByEmployer(TenantId::fromString(Scenario::TENANT), CesuNumber::fromString(self::CESU_NUMBER))),
        );
    }

    /**
     * Number of calls to every scripted URSSAF route: nothing is ever sent by the erasure.
     *
     * @return array<string, int>
     */
    private static function calls(Harness $harness): array
    {
        $calls = [];
        foreach ([HttpScript::ROUTE_AUTHENTICATION, HttpScript::ROUTE_020, HttpScript::ROUTE_030, HttpScript::ROUTE_031, HttpScript::ROUTE_050] as $route) {
            $calls[$route] = $harness->urssaf->calls($route);
        }

        return $calls;
    }
}
