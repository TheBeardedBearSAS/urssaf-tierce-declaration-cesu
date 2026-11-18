<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Service;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\DeclarationSelector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Service\DeclarationLocator;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\RecordNotFound;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Fake\Harness;

final class DeclarationLocatorTest extends TestCase
{
    public function testLocatesByRecordId(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);

        $record = $this->locator($harness)->locate(Harness::tenant(), DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')));

        static::assertSame('record-1', $record->id()->value());
    }

    public function testARecordIdOfAnotherTenantIsNotFound(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending, 1, Harness::OTHER_TENANT);

        $this->assertNotFound(
            $this->locator($harness),
            DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-1')),
            'record-1',
        );
    }

    public function testAnUnknownRecordIdIsNotFound(): void
    {
        try {
            $this->locator(new Harness())->locate(
                Harness::tenant(),
                DeclarationSelector::byRecordId(DeclarationRecordId::fromString('record-9')),
            );
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('declaration_record', $notFound->recordType());
            static::assertSame('record-9', $notFound->identifier());
        }
    }

    public function testLocatesByExternalReference(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending);
        $harness->record(2, DeclarationState::Pending, 1, Harness::OTHER_TENANT);

        $record = $this->locator($harness)->locate(
            Harness::tenant(),
            DeclarationSelector::byExternalReference(ExternalReference::fromString('declaration-1')),
        );

        static::assertSame('record-1', $record->id()->value());
    }

    public function testAnUnknownExternalReferenceIsNotFound(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Pending, 1, Harness::OTHER_TENANT);

        $this->assertNotFound(
            $this->locator($harness),
            DeclarationSelector::byExternalReference(ExternalReference::fromString('declaration-1')),
            'declaration-1',
        );
    }

    public function testLocatesByDeclarationId(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Declared);

        $record = $this->locator($harness)->locate(
            Harness::tenant(),
            DeclarationSelector::byDeclarationId(Harness::result(1)->declarationId()),
        );

        static::assertSame('record-1', $record->id()->value());
    }

    public function testAnUnknownDeclarationIdIsNotFound(): void
    {
        $harness = new Harness();
        $harness->record(1, DeclarationState::Declared, 1, Harness::OTHER_TENANT);

        $this->assertNotFound(
            $this->locator($harness),
            DeclarationSelector::byDeclarationId(Harness::result(1)->declarationId()),
            Harness::result(1)->declarationId()->value(),
        );
    }

    private function assertNotFound(DeclarationLocator $locator, DeclarationSelector $selector, string $identifier): void
    {
        try {
            $locator->locate(Harness::tenant(), $selector);
            static::fail('Expected RecordNotFound.');
        } catch (RecordNotFound $notFound) {
            static::assertSame('declaration_record.not_found', $notFound->violationCode());
            static::assertSame($identifier, $notFound->identifier());
        }
    }

    private function locator(Harness $harness): DeclarationLocator
    {
        return new DeclarationLocator($harness->declarations);
    }
}
