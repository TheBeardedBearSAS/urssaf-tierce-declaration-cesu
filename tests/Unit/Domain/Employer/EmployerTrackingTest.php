<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employer;

use PHPUnit\Framework\TestCase;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerStage;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerTracking;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Unit\Domain\Employee\EmployeeSamples;

final class EmployerTrackingTest extends TestCase
{
    public function testAccessors(): void
    {
        $status = EmployerSamples::status(true);
        $kind = EmployeeReferenceKind::CesuNumber;
        $cesu = EmployeeSamples::cesuNumber();
        $checked = EmployerSamples::instant('2026-11-14T10:00:00Z');
        $updated = EmployerSamples::instant();
        $tracking = new EmployerTracking(EmployerStage::MandateConfirmed, $status, $kind, $cesu, $checked, $updated);

        static::assertSame(EmployerStage::MandateConfirmed, $tracking->stage());
        static::assertSame($status, $tracking->lastStatus());
        static::assertSame($kind, $tracking->lastStatusEmployeeKind());
        static::assertSame($cesu, $tracking->lastStatusEmployeeCesuNumber());
        static::assertSame($checked, $tracking->lastCheckedAt());
        static::assertSame($updated, $tracking->updatedAt());
    }

    public function testWithStageKeepsTheSnapshotAndTouchesTheUpdateInstant(): void
    {
        $status = EmployerSamples::status(true);
        $kind = EmployeeReferenceKind::CesuNumber;
        $cesu = EmployeeSamples::cesuNumber();
        $checked = EmployerSamples::instant('2026-11-14T10:00:00Z');
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');
        $tracking = (new EmployerTracking(EmployerStage::Registered, $status, $kind, $cesu, $checked, EmployerSamples::instant()))
            ->withStage(EmployerStage::MandateNotified, $now);

        static::assertSame(EmployerStage::MandateNotified, $tracking->stage());
        static::assertSame($status, $tracking->lastStatus());
        static::assertSame($kind, $tracking->lastStatusEmployeeKind());
        static::assertSame($cesu, $tracking->lastStatusEmployeeCesuNumber());
        static::assertSame($checked, $tracking->lastCheckedAt());
        static::assertSame($now, $tracking->updatedAt());
    }

    public function testWithStatusReplacesTheSnapshot(): void
    {
        $status = EmployerSamples::status(true);
        $kind = EmployeeReferenceKind::CesuNumber;
        $cesu = EmployeeSamples::cesuNumber();
        $now = EmployerSamples::instant('2026-11-16T10:00:00Z');
        $tracking = (new EmployerTracking(EmployerStage::Registered, null, null, null, null, EmployerSamples::instant()))
            ->withStatus($status, $kind, $cesu, EmployerStage::MandateConfirmed, $now);

        static::assertSame(EmployerStage::MandateConfirmed, $tracking->stage());
        static::assertSame($status, $tracking->lastStatus());
        static::assertSame($kind, $tracking->lastStatusEmployeeKind());
        static::assertSame($cesu, $tracking->lastStatusEmployeeCesuNumber());
        static::assertSame($now, $tracking->lastCheckedAt());
        static::assertSame($now, $tracking->updatedAt());
    }

    public function testHasSnapshot(): void
    {
        $status = EmployerSamples::status(true);
        $kind = EmployeeReferenceKind::CesuNumber;
        $cesu = EmployeeSamples::cesuNumber();
        $now = EmployerSamples::instant();
        $empty = new EmployerTracking(EmployerStage::Registered, null, null, null, null, $now);
        $full = new EmployerTracking(EmployerStage::Registered, $status, $kind, $cesu, $now, $now);
        $withoutEmployee = new EmployerTracking(EmployerStage::Registered, $status, null, null, $now, $now);
        $byNir = new EmployerTracking(EmployerStage::Registered, $status, EmployeeReferenceKind::Nir, null, $now, $now);

        static::assertFalse($empty->hasSnapshot($status, null, null));
        static::assertTrue($full->hasSnapshot(EmployerSamples::status(true), $kind, EmployeeSamples::cesuNumber()));
        static::assertFalse($full->hasSnapshot(EmployerSamples::status(false), $kind, $cesu));
        static::assertFalse($full->hasSnapshot($status, null, null));
        static::assertFalse($full->hasSnapshot($status, $kind, null));
        static::assertFalse($full->hasSnapshot($status, $kind, CesuNumber::fromString('00000987654321')));
        static::assertFalse($full->hasSnapshot($status, EmployeeReferenceKind::Nir, $cesu));
        static::assertTrue($withoutEmployee->hasSnapshot($status, null, null));
        static::assertFalse($withoutEmployee->hasSnapshot($status, $kind, $cesu));
        static::assertTrue($byNir->hasSnapshot($status, EmployeeReferenceKind::Nir, null));
        static::assertFalse($byNir->hasSnapshot($status, EmployeeReferenceKind::Identity, null));
    }

    public function testEquality(): void
    {
        $status = EmployerSamples::status(true);
        $kind = EmployeeReferenceKind::CesuNumber;
        $cesu = EmployeeSamples::cesuNumber();
        $now = EmployerSamples::instant();
        $other = EmployerSamples::instant('2026-11-16T10:00:00Z');
        $tracking = new EmployerTracking(EmployerStage::Registered, $status, $kind, $cesu, $now, $now);

        static::assertTrue($tracking->equals(new EmployerTracking(EmployerStage::Registered, EmployerSamples::status(true), $kind, EmployeeSamples::cesuNumber(), EmployerSamples::instant(), EmployerSamples::instant())));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::MandateNotified, $status, $kind, $cesu, $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, EmployerSamples::status(false), $kind, $cesu, $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, null, $kind, $cesu, $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, EmployeeReferenceKind::Nir, $cesu, $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, null, $cesu, $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, $kind, CesuNumber::fromString('00000987654321'), $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, $kind, null, $now, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, $kind, $cesu, $other, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, $kind, $cesu, null, $now)));
        static::assertFalse($tracking->equals(new EmployerTracking(EmployerStage::Registered, $status, $kind, $cesu, $now, $other)));
        static::assertTrue((new EmployerTracking(EmployerStage::Registered, null, null, null, null, $now))->equals(new EmployerTracking(EmployerStage::Registered, null, null, null, null, $now)));
        static::assertTrue((new EmployerTracking(EmployerStage::Registered, $status, EmployeeReferenceKind::Nir, null, $now, $now))->equals(new EmployerTracking(EmployerStage::Registered, $status, EmployeeReferenceKind::Nir, null, $now, $now)));
        static::assertFalse((new EmployerTracking(EmployerStage::Registered, $status, EmployeeReferenceKind::Nir, null, $now, $now))->equals(new EmployerTracking(EmployerStage::Registered, $status, EmployeeReferenceKind::Identity, null, $now, $now)));
    }
}
