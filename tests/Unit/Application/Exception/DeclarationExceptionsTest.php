<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Exception;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\DeclarationAlreadyRecorded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\MandateRequired;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Exception\RetryableUrssafFailure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\RateLimitExceeded;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafUnavailable;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationRecordId;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\ExternalReference;

final class DeclarationExceptionsTest extends TestCase
{
    public function testDeclarationAlreadyRecorded(): void
    {
        $exception = DeclarationAlreadyRecorded::withExternalReference(
            ExternalReference::fromString('declaration-9'),
            DeclarationRecordId::fromString('record-9'),
        );

        static::assertSame('declaration_record.already_recorded', $exception->violationCode());
        static::assertSame(
            ['external_reference' => 'declaration-9', 'declaration_record_id' => 'record-9'],
            $exception->params(),
        );
        static::assertSame(
            'The external reference "declaration-9" is already recorded with another declaration content.',
            $exception->getMessage(),
        );
    }

    public function testMandateRequired(): void
    {
        $exception = MandateRequired::forEmployer(CesuNumber::fromString('Z0003752390009'), Operation::DeclareSalary);

        static::assertSame('mandate.required', $exception->violationCode());
        static::assertSame(['cesu_number' => 'Z0003752390009', 'operation' => '050'], $exception->params());
        static::assertSame(
            'No active local mandate is known for the employer: method 050 is refused locally (ADR 0009).',
            $exception->getMessage(),
        );
        static::assertSame(
            '060',
            MandateRequired::forEmployer(CesuNumber::fromString('Z0003752390009'), Operation::ActivateCesuPlus)->params()['operation'],
        );
    }

    public function testRetryableFailureKeepsThePreviousExceptionAndItsDelay(): void
    {
        $unavailable = UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, 30, ApiErrors::none());
        $failure = RetryableUrssafFailure::causedBy($unavailable);

        static::assertSame($unavailable, $failure->getPrevious());
        static::assertSame(0, $failure->getCode());
        static::assertSame('URSSAF is temporarily unavailable: the declaration must be retried later.', $failure->getMessage());
        static::assertSame(30, $failure->retryAfterSeconds());
    }

    public function testRetryAfterOfARateLimitRefusal(): void
    {
        $failure = RetryableUrssafFailure::causedBy(RateLimitExceeded::forOperation(Operation::DeclareSalary, 7));

        static::assertSame(7, $failure->retryAfterSeconds());
    }

    public function testRetryAfterIsUnknownWithoutHeader(): void
    {
        $failure = RetryableUrssafFailure::causedBy(UrssafUnavailable::withStatus(Operation::DeclareSalary, 429, null, ApiErrors::none()));

        static::assertNull($failure->retryAfterSeconds());
    }

    public function testRetryAfterIsUnknownForAnotherRetryableException(): void
    {
        $other = RetryableUrssafFailure::causedBy(RateLimitExceeded::forOperation(Operation::DeclareSalary, 7));

        static::assertNull(RetryableUrssafFailure::causedBy($other)->retryAfterSeconds());
    }
}
