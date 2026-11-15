<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf;

use RuntimeException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\UrssafException;

/**
 * URSSAF refused the whole call (HTTP 400, ADR 0002). Not retryable as is. The gateway classifies the errors
 * ({@see ErrorKind}, {@see BusinessError}, ADR 0005): use {@see self::kind()} and {@see self::businessError()} rather
 * than comparing error codes or texts. With several errors, the kind is the one of the first error and the business
 * error the first classifiable `ERR_METIER` one.
 *
 * The message holds the operation number, the HTTP status and the error codes only: never a description, which may echo
 * a value of the request.
 *
 * One predicate per documented rejection, hence the method count.
 *
 * @mago-expect lint:too-many-methods
 */
final class UrssafRequestRejected extends RuntimeException implements UrssafException
{
    private function __construct(
        string $message,
        private readonly Operation $operation,
        private readonly int $httpStatus,
        private readonly ApiErrors $errors,
        private readonly ErrorKind $kind,
        private readonly ?BusinessError $businessError,
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function withErrors(
        Operation $operation,
        int $httpStatus,
        ApiErrors $errors,
        ErrorKind $kind,
        ?BusinessError $businessError,
    ): self {
        return new self(
            \sprintf(
                'URSSAF method %s rejected the request (HTTP %d): %s.',
                $operation->methodNumber(),
                $httpStatus,
                $errors->isEmpty() ? 'no error code' : implode(', ', $errors->codes()),
            ),
            $operation,
            $httpStatus,
            $errors,
            $kind,
            $businessError,
        );
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): ApiErrors
    {
        return $this->errors;
    }

    public function hasErrorCode(string $code): bool
    {
        return $this->errors->hasCode($code);
    }

    public function kind(): ErrorKind
    {
        return $this->kind;
    }

    public function businessError(): ?BusinessError
    {
        return $this->businessError;
    }

    public function is(BusinessError $error): bool
    {
        return $this->businessError === $error;
    }

    /**
     * Method 050: a declaration already exists for the employment period (ADR 0003: already declared).
     */
    public function isDuplicateDeclaration(): bool
    {
        return $this->is(BusinessError::DuplicateDeclaration);
    }

    /**
     * Method 010: the e-mail address already belongs to a Cesu account.
     */
    public function isEmailAlreadyKnown(): bool
    {
        return $this->is(BusinessError::EmailAlreadyKnown);
    }

    /**
     * Method 050: the Cesu+ declaration deadline is exceeded (a plain Cesu declaration is still possible).
     */
    public function isCesuPlusDeadlineExceeded(): bool
    {
        return $this->is(BusinessError::CesuPlusDeadlineExceeded);
    }

    /**
     * Methods 050 and 060: the platform holds no mandate for the employer.
     */
    public function isMandateMissing(): bool
    {
        return $this->kind === ErrorKind::MandateMissing;
    }

    /**
     * Methods 030 and 031: another platform already holds the mandate.
     */
    public function isMandateHeldElsewhere(): bool
    {
        return $this->kind === ErrorKind::MandateHeldElsewhere;
    }

    /**
     * Methods 030 and 031: the employer account is unknown.
     */
    public function isAccountUnknown(): bool
    {
        return $this->kind === ErrorKind::AccountUnknown;
    }

    public function isInvalidParameter(): bool
    {
        return $this->kind === ErrorKind::InvalidParameter;
    }
}
