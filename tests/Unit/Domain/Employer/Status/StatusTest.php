<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer\Status;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\CesuPlusState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\SepaMandateState;

/**
 * One method per scenario of the status values.
 *
 * @mago-expect lint:too-many-methods
 */
final class StatusTest extends TestCase
{
    public function testOnlineAccountStates(): void
    {
        static::assertSame(OnlineAccountState::Absent, OnlineAccountState::tryFromWire('absent'));
        static::assertSame(OnlineAccountState::Inactive, OnlineAccountState::tryFromWire('inactif'));
        static::assertSame(OnlineAccountState::Active, OnlineAccountState::tryFromWire(' actif '));
        static::assertSame(OnlineAccountState::Blocked, OnlineAccountState::tryFromWire('bloque'));
        static::assertSame(OnlineAccountState::StruckOff, OnlineAccountState::tryFromWire('radie'));
        static::assertNull(OnlineAccountState::tryFromWire('ACTIF'));
        static::assertNull(OnlineAccountState::tryFromWire(''));
    }

    public function testSepaMandateStates(): void
    {
        static::assertSame(SepaMandateState::Absent, SepaMandateState::tryFromWire('absent'));
        static::assertSame(SepaMandateState::Inactive, SepaMandateState::tryFromWire('inactif'));
        static::assertSame(SepaMandateState::Active, SepaMandateState::tryFromWire("actif\n"));
        static::assertNull(SepaMandateState::tryFromWire('radie'));
        static::assertNull(SepaMandateState::tryFromWire(''));
    }

    /**
     * @return iterable<string, array{string, CesuPlusState|null}>
     */
    public static function provideCesuPlusStates(): iterable
    {
        yield 'active' => ['active', CesuPlusState::Active];
        yield 'blocked' => ['bloque', CesuPlusState::Blocked];
        yield 'deactivated' => ['desactive', CesuPlusState::Deactivated];
        yield 'active (PDF spelling)' => ['activé', CesuPlusState::Active];
        yield 'blocked (PDF spelling)' => ['bloqué', CesuPlusState::Blocked];
        yield 'deactivated (PDF spelling)' => ['désactivé', CesuPlusState::Deactivated];
        yield 'trimmed' => [' activé ', CesuPlusState::Active];
        yield 'unknown' => ['inconnu', null];
        yield 'empty' => ['', null];
        yield 'upper case' => ['ACTIVE', null];
        yield 'decomposed accent is not normalised' => ["acti\u{0076}e\u{0301}", null];
    }

    #[DataProvider('provideCesuPlusStates')]
    public function testCesuPlusStates(string $raw, ?CesuPlusState $expected): void
    {
        static::assertSame($expected, CesuPlusState::tryFromWire($raw));
    }

    public function testStatusExposesTheRawAndTheParsedStates(): void
    {
        $status = new EmployerStatus(true, 'actif', 'inactif', true, 'activé', 'motif', true);

        static::assertTrue($status->registered());
        static::assertTrue($status->mandatePresent());
        static::assertTrue($status->aiciActive());
        static::assertSame(OnlineAccountState::Active, $status->onlineAccountState());
        static::assertSame('actif', $status->rawOnlineAccountState());
        static::assertSame(SepaMandateState::Inactive, $status->sepaMandateState());
        static::assertSame('inactif', $status->rawSepaMandateState());
        static::assertSame(CesuPlusState::Active, $status->cesuPlusState());
        static::assertSame('activé', $status->rawCesuPlusState());
        static::assertSame('motif', $status->cesuPlusBlockingReason());
        static::assertTrue($status->isCesuPlusActive());
    }

    public function testBooleansAreNotMixedUp(): void
    {
        $status = new EmployerStatus(false, null, null, false, null, null, false);

        static::assertFalse($status->registered());
        static::assertFalse($status->mandatePresent());
        static::assertFalse($status->aiciActive());
        static::assertFalse($status->isCesuPlusActive());
        static::assertNull($status->onlineAccountState());
        static::assertNull($status->rawOnlineAccountState());
        static::assertNull($status->sepaMandateState());
        static::assertNull($status->rawSepaMandateState());
        static::assertNull($status->cesuPlusState());
        static::assertNull($status->rawCesuPlusState());
        static::assertNull($status->cesuPlusBlockingReason());
        static::assertFalse((new EmployerStatus(true, null, null, true, null, null, false))->aiciActive());
        static::assertTrue((new EmployerStatus(false, null, null, false, null, null, true))->aiciActive());
        static::assertFalse((new EmployerStatus(false, null, null, true, null, null, false))->registered());
        static::assertTrue((new EmployerStatus(true, null, null, false, null, null, false))->registered());
        static::assertFalse((new EmployerStatus(true, null, null, false, null, null, false))->mandatePresent());
    }

    public function testUnknownStatesAreKeptRaw(): void
    {
        $status = new EmployerStatus(true, 'nouveau', 'autre', false, 'suspendu', null, false);

        static::assertNull($status->onlineAccountState());
        static::assertSame('nouveau', $status->rawOnlineAccountState());
        static::assertNull($status->sepaMandateState());
        static::assertSame('autre', $status->rawSepaMandateState());
        static::assertNull($status->cesuPlusState());
        static::assertSame('suspendu', $status->rawCesuPlusState());
        static::assertFalse($status->isCesuPlusActive());
    }

    public function testOtherCesuPlusStatesAreNotActive(): void
    {
        static::assertFalse((new EmployerStatus(true, null, null, false, 'bloque', null, false))->isCesuPlusActive());
        static::assertTrue((new EmployerStatus(true, null, null, false, 'active', null, false))->isCesuPlusActive());
    }

    /**
     * @return iterable<string, array{string|null, string|null}>
     */
    public static function provideBlockingReasons(): iterable
    {
        yield 'absent' => [null, null];
        yield 'empty' => ['', null];
        yield 'blank' => ['  ', null];
        yield 'null text' => ['null', null];
        yield 'upper case null text' => ['NULL', null];
        yield 'padded null text' => [' Null ', null];
        yield 'text' => ['IBAN refusé', 'IBAN refusé'];
        yield 'trimmed text' => ['  motif  ', 'motif'];
        yield 'text containing null' => ['nullable', 'nullable'];
    }

    #[DataProvider('provideBlockingReasons')]
    public function testBlockingReasonNormalisation(?string $raw, ?string $expected): void
    {
        static::assertSame($expected, (new EmployerStatus(true, null, null, false, null, $raw, false))->cesuPlusBlockingReason());
    }

    public function testEquality(): void
    {
        $status = new EmployerStatus(true, 'actif', 'actif', true, 'active', 'motif', false);

        static::assertTrue($status->equals(new EmployerStatus(true, 'actif', 'actif', true, 'active', ' motif ', false)));
        static::assertFalse($status->equals(new EmployerStatus(false, 'actif', 'actif', true, 'active', 'motif', false)));
        static::assertFalse($status->equals(new EmployerStatus(true, 'bloque', 'actif', true, 'active', 'motif', false)));
        static::assertFalse($status->equals(new EmployerStatus(true, 'actif', 'inactif', true, 'active', 'motif', false)));
        static::assertFalse($status->equals(new EmployerStatus(true, 'actif', 'actif', false, 'active', 'motif', false)));
        static::assertFalse($status->equals(new EmployerStatus(true, 'actif', 'actif', true, 'bloque', 'motif', false)));
        static::assertFalse($status->equals(new EmployerStatus(true, 'actif', 'actif', true, 'active', 'autre', false)));
        static::assertFalse($status->equals(new EmployerStatus(true, 'actif', 'actif', true, 'active', 'motif', true)));
    }
}
