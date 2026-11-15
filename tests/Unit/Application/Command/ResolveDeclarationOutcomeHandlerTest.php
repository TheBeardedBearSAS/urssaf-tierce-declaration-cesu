<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Command;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Command\ResolveDeclarationOutcomeHandler;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationView;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidStateTransition;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

final class ResolveDeclarationOutcomeHandlerTest extends TestCase
{
    public function testTheOperatorFoundTheDeclarationOnCesuUrssaf(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::OutcomeUnknown);
        $declarationId = DeclarationId::fromString('209299500042');

        $view = $this->handle($harness, ResolveDeclarationOutcome::asDeclared(Harness::tenant(), self::selector(), $declarationId));

        static::assertSame('declared', $view->status->state);
        static::assertSame('209299500042', $view->status->declarationId);
        static::assertNull($view->status->result);
        static::assertSame('2026-11-15T10:00:00Z', $view->status->updatedAt);
        static::assertSame(['begin', 'save:record-1', 'commit', 'publish:DeclarationDeclared'], $harness->journal->entries());
        static::assertSame(
            DeclarationState::Declared,
            $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'))->state(),
        );
        static::assertSame([], $harness->gateway->calls());
    }

    public function testTheOperatorFoundThatUrssafNeverReceivedTheDeclaration(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::OutcomeUnknown);

        $view = $this->handle($harness, ResolveDeclarationOutcome::asNotReceived(Harness::tenant(), self::selector()));

        static::assertSame('pending', $view->status->state);
        static::assertSame(['begin', 'save:record-1', 'commit', 'publish:DeclarationRequeued'], $harness->journal->entries());
        static::assertSame([], $harness->gateway->calls());
    }

    public function testTheCommandsCarryTheirResolution(): void
    {
        $declarationId = DeclarationId::fromString('209299500042');
        $declared = ResolveDeclarationOutcome::asDeclared(Harness::tenant(), self::selector(), $declarationId);
        $missing = ResolveDeclarationOutcome::asNotReceived(Harness::tenant(), self::selector());

        static::assertSame($declarationId, $declared->declarationId);
        static::assertNull($missing->declarationId);
        static::assertSame('tenant-1', $missing->tenantId->value());
    }

    public function testARecordNotInOutcomeUnknownIsLeftAlone(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Declared);

        try {
            $this->handle($harness, ResolveDeclarationOutcome::asNotReceived(Harness::tenant(), self::selector()));
            static::fail('Expected InvalidStateTransition.');
        } catch (InvalidStateTransition $refusal) {
            static::assertSame('declaration_record.invalid_state_transition', $refusal->violationCode());
        }

        static::assertSame(['begin', 'rollback'], $harness->journal->entries());
    }

    public function testADeclarationIdAlreadyHeldByAnotherRecordIsRefused(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::OutcomeUnknown);
        $harness->record(2, DeclarationState::Declared);

        try {
            $this->handle($harness, ResolveDeclarationOutcome::asDeclared(Harness::tenant(), self::selector(), Harness::result(2)->declarationId()));
            static::fail('Expected InvariantViolation.');
        } catch (InvariantViolation $violation) {
            static::assertSame('declaration_record.duplicate_declaration_id', $violation->violationCode());
        }

        static::assertSame(
            DeclarationState::OutcomeUnknown,
            $harness->declarations->inner()->get(DeclarationRecordId::fromString('record-1'))->state(),
        );
        static::assertContains('rollback', $harness->journal->entries());
    }

    public function testAnUnknownRecordIsNotFound(): void
    {
        $harness = new Harness();

        $this->expectException(RecordNotFound::class);

        $this->handle($harness, ResolveDeclarationOutcome::asNotReceived(Harness::tenant(), self::selector()));
    }

    private static function selector(): DeclarationSelector
    {
        return DeclarationSelector::byExternalReference(ExternalReference::fromString('declaration-1'));
    }

    private function handle(Harness $harness, ResolveDeclarationOutcome $command): DeclarationView
    {
        return (new ResolveDeclarationOutcomeHandler(
            new DeclarationLocator($harness->declarations),
            $harness->declarations,
            $harness->clock,
            $harness->unitOfWork,
        ))($command);
    }
}
