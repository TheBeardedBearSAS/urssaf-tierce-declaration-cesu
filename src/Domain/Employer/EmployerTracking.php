<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer;

use Closure;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReferenceKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Mutable part of an {@see EmployerAccount}: stage, last 020 snapshot with the kind and, when known, the Cesu number of
 * the employee reference it was read for (the Cesu+ state is relative to a pair, §5.9; never a NIR or administrative
 * data, ADR 0007), last check and last update. The public constructor is the factory (more than 5 parts).
 */
final readonly class EmployerTracking
{
    public function __construct(
        private EmployerStage $stage,
        private ?EmployerStatus $lastStatus,
        private ?EmployeeReferenceKind $lastStatusEmployeeKind,
        private ?CesuNumber $lastStatusEmployeeCesuNumber,
        private ?Instant $lastCheckedAt,
        private Instant $updatedAt,
    ) {}

    public function stage(): EmployerStage
    {
        return $this->stage;
    }

    public function lastStatus(): ?EmployerStatus
    {
        return $this->lastStatus;
    }

    public function lastStatusEmployeeKind(): ?EmployeeReferenceKind
    {
        return $this->lastStatusEmployeeKind;
    }

    public function lastStatusEmployeeCesuNumber(): ?CesuNumber
    {
        return $this->lastStatusEmployeeCesuNumber;
    }

    public function lastCheckedAt(): ?Instant
    {
        return $this->lastCheckedAt;
    }

    public function updatedAt(): Instant
    {
        return $this->updatedAt;
    }

    public function withStage(EmployerStage $stage, Instant $now): self
    {
        return new self($stage, $this->lastStatus, $this->lastStatusEmployeeKind, $this->lastStatusEmployeeCesuNumber, $this->lastCheckedAt, $now);
    }

    public function withStatus(
        EmployerStatus $status,
        ?EmployeeReferenceKind $employeeKind,
        ?CesuNumber $employeeCesuNumber,
        EmployerStage $stage,
        Instant $now,
    ): self {
        return new self($stage, $status, $employeeKind, $employeeCesuNumber, $now, $now);
    }

    /**
     * Whether the given snapshot is the last one, read for the same employee reference.
     */
    public function hasSnapshot(EmployerStatus $status, ?EmployeeReferenceKind $employeeKind, ?CesuNumber $employeeCesuNumber): bool
    {
        return self::sameOptional($this->lastStatus, $status, static fn(EmployerStatus $left, EmployerStatus $right): bool => $left->equals($right))
            && $this->lastStatusEmployeeKind === $employeeKind
            && self::sameOptional($this->lastStatusEmployeeCesuNumber, $employeeCesuNumber, static fn(CesuNumber $left, CesuNumber $right): bool => $left->equals($right));
    }

    public function equals(self $other): bool
    {
        return $this->stage === $other->stage
            && self::sameOptional($this->lastStatus, $other->lastStatus, static fn(EmployerStatus $left, EmployerStatus $right): bool => $left->equals($right))
            && $this->lastStatusEmployeeKind === $other->lastStatusEmployeeKind
            && self::sameOptional($this->lastStatusEmployeeCesuNumber, $other->lastStatusEmployeeCesuNumber, static fn(CesuNumber $left, CesuNumber $right): bool => $left->equals($right))
            && self::sameOptional($this->lastCheckedAt, $other->lastCheckedAt, static fn(Instant $left, Instant $right): bool => $left->equals($right))
            && $this->updatedAt->equals($other->updatedAt);
    }

    /**
     * @template T of object
     *
     * @param T|null              $left
     * @param T|null              $right
     * @param Closure(T, T): bool $equals
     */
    private static function sameOptional(?object $left, ?object $right, Closure $equals): bool
    {
        if ($left === null || $right === null) {
            return $left === $right;
        }

        return $equals($left, $right);
    }
}
