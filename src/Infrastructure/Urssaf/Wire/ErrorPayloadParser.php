<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiError;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Urssaf\ApiErrors;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Parses the error payload shapes of the API (api-reference §1.3, §5 point 19): a single `{code,message,description}`
 * object (the documented shape), a list of them, or an envelope `{"errors": [...]}` (tolerated). Codes are normalised
 * (stray spaces removed); messages and descriptions are kept raw, a missing description stays null.
 */
final class ErrorPayloadParser
{
    /**
     * Lenient: an unreadable body yields no error (used for HTTP error responses, which may be anything).
     */
    public function parseBody(string $body): ApiErrors
    {
        try {
            return $this->parsePayload(JsonDecoder::decode($body));
        } catch (UnexpectedUrssafResponse) {
            return ApiErrors::none();
        }
    }

    /**
     * @throws UnexpectedUrssafResponse when the structure is neither an object, a list of objects nor an envelope
     */
    public function parsePayload(Payload $payload): ApiErrors
    {
        if ($payload->isList()) {
            return $this->parseEntries($payload->entries());
        }

        if ($payload->string('code') !== null) {
            return $this->parseEntries([$payload]);
        }

        return $this->parseEntries($payload->items('errors'));
    }

    /**
     * Entries without a code are ignored.
     *
     * @param list<Payload> $entries
     */
    public function parseEntries(array $entries): ApiErrors
    {
        $errors = [];
        foreach ($entries as $entry) {
            $code = ApiErrorCode::normalise($entry->string('code') ?? '');
            if ($code === '') {
                continue;
            }

            $errors[] = ApiError::of($code, $entry->string('message') ?? '', $entry->string('description'));
        }

        return ApiErrors::of(...$errors);
    }
}
