<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\BusinessError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ErrorKind;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\UrssafRequestRejected;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * Translates the URSSAF error codes and texts into the port concepts, so that the application never compares wire
 * strings (ADR 0000 §5, ADR 0005). An unknown code or text degrades to {@see ErrorKind::Unknown} / null, never to a
 * wrong meaning.
 */
final class ErrorClassifier
{
    /**
     * Text normalisation of ADR 0005, in this order: typographic apostrophe to `'`, doubled apostrophes to one,
     * every run of whitespace (U+00A0 and U+202F included) to one space, trim, lower case.
     */
    public static function normalise(string $text): string
    {
        $text = str_replace("\u{2019}", "'", $text);
        $text = self::replace("/'{2,}/u", "'", $text);
        $text = self::replace('/[\s\x{00A0}\x{202F}]+/u', ' ', $text);

        return mb_strtolower(trim($text));
    }

    /**
     * Kind of the first error; {@see ErrorKind::Unknown} when there is none or its code is not in the catalogue.
     */
    public static function errorKind(ApiErrors $errors): ErrorKind
    {
        $first = $errors->first();
        if (!$first instanceof ApiError) {
            return ErrorKind::Unknown;
        }

        return ApiErrorCode::fromCode($first->code())?->errorKind() ?? ErrorKind::Unknown;
    }

    /**
     * First `ERR_METIER` error whose description is known for the operation; null otherwise.
     */
    public static function businessError(Operation $operation, ApiErrors $errors): ?BusinessError
    {
        foreach ($errors->all() as $error) {
            $description = $error->description();
            if (ApiErrorCode::fromCode($error->code()) !== ApiErrorCode::Business || $description === null) {
                continue;
            }

            $businessError = self::classifyDescription($operation, $description);
            if ($businessError instanceof BusinessError) {
                return $businessError;
            }
        }

        return null;
    }

    /**
     * Matches one description against the catalogue of the operation; null when unknown.
     */
    public static function classifyDescription(Operation $operation, string $description): ?BusinessError
    {
        $normalised = self::normalise($description);
        foreach (BusinessError::cases() as $case) {
            if (
                \in_array($operation, BusinessErrorCatalogue::operations($case), true)
                && preg_match(BusinessErrorCatalogue::pattern($case), $normalised) === 1
            ) {
                return $case;
            }
        }

        return null;
    }

    /**
     * HTTP 400 turned into the classified exception: the only entry point of the gateway.
     */
    public static function rejection(Operation $operation, int $httpStatus, ApiErrors $errors): UrssafRequestRejected
    {
        return UrssafRequestRejected::withErrors(
            $operation,
            $httpStatus,
            $errors,
            self::errorKind($errors),
            self::businessError($operation, $errors),
        );
    }

    /**
     * A subject that is not valid UTF-8 cannot be matched: it is returned unchanged.
     */
    private static function replace(string $pattern, string $replacement, string $subject): string
    {
        return preg_replace($pattern, $replacement, $subject) ?? $subject;
    }
}
