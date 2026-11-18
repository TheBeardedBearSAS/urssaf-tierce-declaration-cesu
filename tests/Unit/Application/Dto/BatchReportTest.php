<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Application\Dto;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchItem;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\BatchReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\ItemOutcome;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\RelayReport;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto\RequeueReport;

final class BatchReportTest extends TestCase
{
    public function testAnEmptyReport(): void
    {
        $report = BatchReport::of();

        static::assertSame([], $report->items());
        static::assertSame(0, $report->total());
        static::assertSame(0, $report->count(ItemOutcome::Declared));
    }

    public function testItemsKeepTheirOrderAndAreCounted(): void
    {
        $first = new BatchItem('record-1', ItemOutcome::Changed);
        $second = new BatchItem('record-2', ItemOutcome::Failed, ['PARAM_INVALIDE']);
        $third = new BatchItem('record-3', ItemOutcome::Changed);
        $report = BatchReport::of($first, $second, $third);

        static::assertSame([$first, $second, $third], $report->items());
        static::assertSame(3, $report->total());
        static::assertSame(2, $report->count(ItemOutcome::Changed));
        static::assertSame(1, $report->count(ItemOutcome::Failed));
        static::assertSame(0, $report->count(ItemOutcome::Unchanged));
        static::assertSame([], $first->codes);
        static::assertSame(['PARAM_INVALIDE'], $second->codes);
        static::assertSame('record-2', $second->subject);
        static::assertSame(ItemOutcome::Failed, $second->outcome);
    }

    public function testMergeConcatenatesWithoutChangingTheOperands(): void
    {
        $first = new BatchItem('a', ItemOutcome::Changed);
        $second = new BatchItem('b', ItemOutcome::Unchanged);
        $third = new BatchItem('c', ItemOutcome::Failed);
        $fourth = new BatchItem('d', ItemOutcome::Declared);
        $left = BatchReport::of($first, $second);
        $right = BatchReport::of($third, $fourth);

        $merged = $left->merge($right);

        static::assertSame([$first, $second, $third, $fourth], $merged->items());
        static::assertSame([$first, $second], $left->items());
        static::assertSame([$third, $fourth], $right->items());
        static::assertSame([$first], BatchReport::of($first)->merge(BatchReport::of())->items());
        static::assertSame([$fourth], BatchReport::of()->merge(BatchReport::of($fourth))->items());
    }

    public function testItemsAreReindexed(): void
    {
        $item = new BatchItem('a', ItemOutcome::Declared);

        static::assertSame([0 => $item], BatchReport::of(...['x' => $item])->items());
    }

    public function testTheReportsOfTheOutboxAndTheRequeueAreValueObjects(): void
    {
        $relay = new RelayReport(3, 2, 1);
        $requeue = new RequeueReport(4);

        static::assertSame([3, 2, 1], [$relay->dispatched, $relay->failed, $relay->deferred]);
        static::assertSame(4, $requeue->declarations);
    }
}
