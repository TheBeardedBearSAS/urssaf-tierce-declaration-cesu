<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Wire;

use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\UnexpectedUrssafResponse;

/**
 * Typed, defensive view over a decoded JSON document. This is the only place where untyped wire data is read:
 * every accessor either returns a well-typed value or throws {@see UnexpectedUrssafResponse}. Exception reasons name
 * the field, never its value (ADR 0007).
 *
 * One accessor per JSON shape read from URSSAF, hence the method count and complexity (lot 2 design §5.2).
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class Payload
{
    /**
     * @param array<array-key, array<array-key, mixed>|scalar|null> $data
     */
    private function __construct(private array $data) {}

    /**
     * @throws UnexpectedUrssafResponse when the decoded JSON is neither an object nor an array
     */
    public static function fromDecoded(mixed $decoded): self
    {
        if (!\is_array($decoded)) {
            throw UnexpectedUrssafResponse::invalidPayload('body is neither a JSON object nor a JSON array');
        }

        return self::wrap($decoded);
    }

    /**
     * No data at all: the empty body of a 2xx answer (ADR 0002).
     */
    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param array<array-key, mixed> $data decoded JSON object or array
     */
    private static function wrap(array $data): self
    {
        /** @var array<array-key, array<array-key, mixed>|scalar|null> $typed */
        $typed = $data;

        return new self($typed);
    }

    /**
     * True for an empty body, `{}` and `[]`.
     */
    public function isEmpty(): bool
    {
        return $this->data === [];
    }

    public function isList(): bool
    {
        return array_is_list($this->data);
    }

    /**
     * Lenient string value of a key: null when absent, null or of another type. Used only to read error bodies.
     */
    public function string(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        return \is_string($value) ? $value : null;
    }

    /**
     * @throws UnexpectedUrssafResponse when present but not a string
     */
    public function optionalString(string $key): ?string
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_string($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a string', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a non-empty string
     */
    public function requireString(string $key): string
    {
        $value = $this->string($key) ?? '';

        if ($value === '') {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or empty', $key));
        }

        return $value;
    }

    /**
     * An identifier sent as a non-empty string or as an integer (returned as its decimal string).
     *
     * @return non-empty-string
     *
     * @throws UnexpectedUrssafResponse when the key is absent or its value is neither
     */
    public function requireIdentifier(string $key): string
    {
        $value = $this->data[$key] ?? null;
        $identifier = \is_int($value) ? (string) $value : $this->string($key) ?? '';

        if ($identifier === '') {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not an identifier', $key));
        }

        return $identifier;
    }

    /**
     * @throws UnexpectedUrssafResponse when present but not a boolean
     */
    public function optionalBool(string $key): ?bool
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_bool($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a boolean', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a boolean
     */
    public function requireBool(string $key): bool
    {
        $value = $this->data[$key] ?? null;

        if (!\is_bool($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not a boolean', $key));
        }

        return $value;
    }

    /**
     * JSON number of a key. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but not a number
     */
    public function number(string $key): int|float|null
    {
        $value = $this->data[$key] ?? null;

        if ($value === null || \is_int($value) || \is_float($value)) {
            return $value;
        }

        throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not a number', $key));
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not a number
     */
    public function requireNumber(string $key): int|float
    {
        $value = $this->data[$key] ?? null;

        if (!\is_int($value) && !\is_float($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not a number', $key));
        }

        return $value;
    }

    /**
     * Nested JSON object. Null when absent.
     *
     * @throws UnexpectedUrssafResponse when present but not an object
     */
    public function object(string $key): ?self
    {
        $value = $this->data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!\is_array($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is not an object', $key));
        }

        return self::wrap($value);
    }

    /**
     * @throws UnexpectedUrssafResponse when the key is absent or its value is not an object
     */
    public function requireObject(string $key): self
    {
        $value = $this->data[$key] ?? null;

        if (!\is_array($value)) {
            throw UnexpectedUrssafResponse::invalidPayload(\sprintf('field "%s" is missing or not an object', $key));
        }

        return self::wrap($value);
    }

    /**
     * Nested list of objects. Empty when absent.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when present but not a list of objects
     */
    public function items(string $key): array
    {
        return $this->object($key)?->entries() ?? [];
    }

    /**
     * Elements of a top-level JSON array, each of which must be an object.
     *
     * @return list<self>
     *
     * @throws UnexpectedUrssafResponse when this payload is not a list of objects
     */
    public function entries(): array
    {
        if (!array_is_list($this->data)) {
            throw UnexpectedUrssafResponse::invalidPayload('expected a JSON array');
        }

        return array_map(
            static fn(array|bool|float|int|string|null $entry): self => \is_array($entry)
                ? self::wrap($entry)
                : throw UnexpectedUrssafResponse::invalidPayload('expected a JSON array of objects'),
            $this->data,
        );
    }
}
