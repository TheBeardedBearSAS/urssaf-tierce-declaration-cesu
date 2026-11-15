<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration\SubmissionError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvariantViolation;

/**
 * Error item returned by URSSAF: {"code": "...", "message": "...", "description": "..."} (api-reference §1.3). Kept
 * verbatim (French); the description may be absent.
 */
final readonly class ApiError
{
    private function __construct(private string $code, private string $message, private ?string $description) {}

    /**
     * @throws InvariantViolation "api_error.empty_code"
     */
    public static function of(string $code, string $message, ?string $description): self
    {
        if (trim($code) === '') {
            throw InvariantViolation::because('api_error.empty_code', 'An URSSAF error must carry a code.');
        }

        return new self($code, $message, $description);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function hasCode(string $code): bool
    {
        return $this->code === $code;
    }

    public function equals(self $other): bool
    {
        return $this->code === $other->code
            && $this->message === $other->message
            && $this->description === $other->description;
    }

    public function toSubmissionError(): SubmissionError
    {
        return SubmissionError::of($this->code, $this->message, $this->description);
    }
}
