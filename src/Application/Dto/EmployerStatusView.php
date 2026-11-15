<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employee\EmployeeReference;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\EmployerAccount;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\EmployerStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\Instant;

/**
 * Read model of the last answer of method 020 (api-reference §2.2). States are the raw wire values (unknown values are
 * kept). Never exposes personal data.
 */
final readonly class EmployerStatusView
{
    private function __construct(
        public bool $registered,
        public ?string $onlineAccountState,
        public ?string $sepaMandateState,
        public bool $mandatePresent,
        public ?string $cesuPlusState,
        public ?string $cesuPlusBlockingReason,
        public bool $aiciActive,
        public ?string $employeeReferenceKind,
        public ?string $employeeCesuNumber,
        public ?string $checkedAt,
    ) {}

    public static function fromStatus(EmployerStatus $status, ?EmployeeReference $employee, ?Instant $checkedAt): self
    {
        return self::of($status, $employee?->kind()->value, $employee?->cesuNumber()?->value(), $checkedAt);
    }

    /**
     * Null when the account was never synchronised.
     */
    public static function fromAccount(EmployerAccount $account): ?self
    {
        $status = $account->lastStatus();
        if (!$status instanceof EmployerStatus) {
            return null;
        }

        return self::of(
            $status,
            $account->lastStatusEmployeeKind()?->value,
            $account->lastStatusEmployeeCesuNumber()?->value(),
            $account->lastCheckedAt(),
        );
    }

    private static function of(EmployerStatus $status, ?string $employeeKind, ?string $employeeCesuNumber, ?Instant $checkedAt): self
    {
        return new self(
            $status->registered(),
            $status->rawOnlineAccountState(),
            $status->rawSepaMandateState(),
            $status->mandatePresent(),
            $status->rawCesuPlusState(),
            $status->cesuPlusBlockingReason(),
            $status->aiciActive(),
            $employeeKind,
            $employeeCesuNumber,
            $checkedAt?->toIso8601(),
        );
    }
}
