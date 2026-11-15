<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Declaration;

use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;

/**
 * Error reported for a submission: URSSAF business error (`code`, `message`, `description`, api-reference §1.3) or
 * technical failure. The three fields are kept verbatim; the description may be absent.
 */
final readonly class SubmissionError
{
    private function __construct(private string $code, private string $message, private ?string $description) {}

    /**
     * @throws InvalidValue "submission_error_code.empty"
     */
    public static function of(string $code, string $message, ?string $description): self
    {
        if (trim($code) === '') {
            throw InvalidValue::empty('submission_error_code');
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
}
