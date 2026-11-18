<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Declaration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationFingerprint;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * One method per identifier scenario.
 *
 * @mago-expect lint:too-many-methods
 */
final class IdentifiersTest extends TestCase
{
    public function testDeclarationIdIsKeptVerbatim(): void
    {
        $id = DeclarationId::fromString('209299542888');

        static::assertSame('209299542888', $id->value());
        static::assertSame('209299542888', (string) $id);
        static::assertTrue($id->equals(DeclarationId::fromString('209299542888')));
        static::assertFalse($id->equals(DeclarationId::fromString('209299542889')));
    }

    public function testDeclarationIdLengths(): void
    {
        static::assertSame(str_repeat('é', 64), DeclarationId::fromString(str_repeat('é', 64))->value());
        $this->expectExceptionObject(InvalidValue::tooLong('declaration_id', 64));

        DeclarationId::fromString(str_repeat('é', 65));
    }

    public function testBlankDeclarationIdIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('declaration_id'));

        DeclarationId::fromString(' ');
    }

    public function testRecordIdIsKeptVerbatim(): void
    {
        $id = DeclarationRecordId::fromString(' rec 1 ');

        static::assertSame(' rec 1 ', $id->value());
        static::assertSame(' rec 1 ', (string) $id);
        static::assertTrue($id->equals(DeclarationRecordId::fromString(' rec 1 ')));
        static::assertFalse($id->equals(DeclarationRecordId::fromString('rec 1')));
    }

    public function testRecordIdLengths(): void
    {
        static::assertSame(str_repeat('é', 64), DeclarationRecordId::fromString(str_repeat('é', 64))->value());
        $this->expectExceptionObject(InvalidValue::tooLong('declaration_record_id', 64));

        DeclarationRecordId::fromString(str_repeat('é', 65));
    }

    public function testBlankRecordIdIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('declaration_record_id'));

        DeclarationRecordId::fromString("\t ");
    }

    public function testFingerprintAcceptsSixtyFourLowerCaseHexadecimalCharacters(): void
    {
        $value = str_repeat('0123456789abcdef', 4);
        $fingerprint = DeclarationFingerprint::fromString($value);

        static::assertSame($value, $fingerprint->value());
        static::assertTrue($fingerprint->equals(DeclarationFingerprint::fromString($value)));
        static::assertFalse($fingerprint->equals(DeclarationFingerprint::fromString(str_repeat('f', 64))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidFingerprints(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => [str_repeat('a', 63)];
        yield 'too long' => [str_repeat('a', 65)];
        yield 'upper case' => [str_repeat('A', 64)];
        yield 'not hexadecimal' => [str_repeat('g', 64)];
        yield 'leading junk' => ['x' . str_repeat('a', 64)];
        yield 'trailing newline' => [str_repeat('a', 64) . "\n"];
        yield 'leading newline' => ["\n" . str_repeat('a', 64)];
    }

    #[DataProvider('provideInvalidFingerprints')]
    public function testInvalidFingerprintsAreRefused(string $value): void
    {
        $this->expectExceptionObject(InvalidValue::invalidFormat('declaration_fingerprint', '64 lowercase hexadecimal characters'));

        DeclarationFingerprint::fromString($value);
    }

    public function testSubmissionErrorKeepsEverythingVerbatim(): void
    {
        $error = SubmissionError::of(' PARAM_INVALIDE ', ' Message ', ' Description ');

        static::assertSame(' PARAM_INVALIDE ', $error->code());
        static::assertSame(' Message ', $error->message());
        static::assertSame(' Description ', $error->description());
    }

    public function testSubmissionErrorDescriptionMayBeAbsent(): void
    {
        static::assertNull(SubmissionError::of('MANDAT_INEXISTANT', 'Mandat absent', null)->description());
    }

    public function testSubmissionErrorWithBlankCodeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidValue::empty('submission_error_code'));

        SubmissionError::of(' ', 'message', null);
    }
}
