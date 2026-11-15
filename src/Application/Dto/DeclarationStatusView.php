<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Dto;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationResult;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\DeclarationStatus;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;

/**
 * Read model of the status of a declaration record. Instants are ISO 8601 UTC strings. The stored errors include the
 * URSSAF description (the operator needs it): never log it.
 */
final readonly class DeclarationStatusView
{
    /**
     * @param list<array{code: string, message: string, description: ?string}> $errors
     */
    private function __construct(
        public string $state,
        public int $attempts,
        public ?string $lastAttemptAt,
        public array $errors,
        public ?string $declarationId,
        public ?DeclarationResultView $result,
        public ?string $outcomeUnknownReason,
        public string $updatedAt,
    ) {}

    public static function fromStatus(DeclarationStatus $status): self
    {
        $result = $status->result();

        return new self(
            $status->state()->value,
            $status->attempts(),
            $status->lastAttemptAt()?->toIso8601(),
            array_map(
                static fn(SubmissionError $error): array => [
                    'code' => $error->code(),
                    'message' => $error->message(),
                    'description' => $error->description(),
                ],
                $status->errors(),
            ),
            $status->declarationId()?->value(),
            $result instanceof DeclarationResult ? DeclarationResultView::fromResult($result) : null,
            $status->outcomeUnknownReason()?->value,
            $status->updatedAt()->toIso8601(),
        );
    }
}
