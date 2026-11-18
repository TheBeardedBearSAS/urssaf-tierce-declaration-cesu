<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Validation;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ValidationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\Violation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Validation\ViolationCollector;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\DomainAssertions;

final class ViolationCollectorTest extends TestCase
{
    public function testANewCollectorIsValid(): void
    {
        $collector = new ViolationCollector();

        static::assertTrue($collector->result()->isValid());
        static::assertSame(0, $collector->errorCount());
    }

    public function testRequired(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('Z0003752390009', $collector->required('cesuNumber', 'cesu_number', 'Z0003752390009', CesuNumber::fromString(...))?->value());
        static::assertSame(0, $collector->errorCount());
        static::assertNull($collector->required('cesuNumber', 'cesu_number', null, CesuNumber::fromString(...)));
        static::assertNull($collector->required('cesuNumber', 'cesu_number', '', CesuNumber::fromString(...)));
        static::assertNull($collector->required('cesuNumber', 'cesu_number', 'short', CesuNumber::fromString(...)));

        $all = $collector->result()->all();
        static::assertSame(
            [
                'error cesuNumber cesu_number.required',
                'error cesuNumber cesu_number.required',
                'error cesuNumber cesu_number.invalid_format',
            ],
            DomainAssertions::summary($collector->result()),
        );
        static::assertSame('The cesu number is required.', $all[0]->message());
        static::assertSame([], $all[0]->params());
        static::assertSame(['expected_format' => '14 alphanumeric characters'], $all[2]->params());
        static::assertSame(3, $collector->errorCount());
    }

    public function testOptional(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('Z0003752390009', $collector->optional('cesuNumber', 'Z0003752390009', CesuNumber::fromString(...))?->value());
        static::assertNull($collector->optional('cesuNumber', null, CesuNumber::fromString(...)));
        static::assertNull($collector->optional('cesuNumber', '', CesuNumber::fromString(...)));
        static::assertTrue($collector->result()->isValid());
        static::assertNull($collector->optional('cesuNumber', 'x', CesuNumber::fromString(...)));
        static::assertSame(['error cesuNumber cesu_number.invalid_format'], DomainAssertions::summary($collector->result()));
    }

    public function testAttemptAddAndMerge(): void
    {
        $collector = new ViolationCollector();

        static::assertSame('Z0003752390009', $collector->attempt('a', static fn(): CesuNumber => CesuNumber::fromString('Z0003752390009'))?->value());
        static::assertNull($collector->attempt('b', static fn(): CesuNumber => CesuNumber::fromString('1')));
        $collector->add(Violation::warning('c', 'c.warning', 'Warning.'));
        $collector->merge(ValidationResult::of(Violation::error('d', 'd.error', 'Error.')), 'parent');
        $collector->merge(ValidationResult::of(Violation::error('', 'e.error', 'Error.')), 'root');

        static::assertSame(
            [
                'error b cesu_number.invalid_format',
                'warning c c.warning',
                'error parent.d d.error',
                'error root e.error',
            ],
            DomainAssertions::summary($collector->result()),
        );
        static::assertSame(3, $collector->errorCount());
    }

    public function testAttemptOnlyCatchesInvalidValue(): void
    {
        $collector = new ViolationCollector();

        $this->expectException(InvariantViolation::class);

        $collector->attempt('a', static fn(): never => throw InvariantViolation::because('a.b', 'm'));
    }

    public function testWarningsDoNotCountAsErrors(): void
    {
        $collector = new ViolationCollector();
        $collector->add(Violation::warning('a', 'a.warning', 'Warning.'));

        static::assertSame(0, $collector->errorCount());
        static::assertTrue($collector->result()->isValid());
    }
}
