<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

/**
 * What ForgetEmployer erased: identifiers only (ADR 0007, ADR 0015, lot 10 design §5.2).
 */
final readonly class EmployerErasure
{
    /**
     * @param list<string> $mandateIds           oldest notification first
     * @param list<string> $declarationRecordIds oldest creation first
     */
    public function __construct(
        public string $accountId,
        public array $mandateIds,
        public array $declarationRecordIds,
    ) {}
}
