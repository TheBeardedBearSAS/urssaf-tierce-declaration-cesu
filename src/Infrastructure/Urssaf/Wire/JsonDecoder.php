<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use JsonException;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Decodes an HTTP body into a {@see Payload}. An empty (or blank) body gives an empty payload: 030 and 031 answer an
 * empty 200 (ADR 0002); for the other operations the response mapper then fails on its first required field.
 */
final class JsonDecoder
{
    /**
     * @throws UnexpectedUrssafResponse when the body is not valid JSON or not a JSON object / array
     */
    public static function decode(string $json): Payload
    {
        if (trim($json) === '') {
            return Payload::empty();
        }

        try {
            return Payload::fromDecoded(json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
        } catch (JsonException $exception) {
            throw UnexpectedUrssafResponse::causedBy('body is not valid JSON', $exception);
        }
    }
}
