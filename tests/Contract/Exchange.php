<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use JsonException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\OnlineAccountState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Status\SepaMandateState;
use TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Exception\ApiErrorCode;

/**
 * The only thing ever kept from a wire exchange: its shape. Never a request body (the token request carries the
 * secret), never an Authorization header, never a query string, never a free-text response value. Enumerated values are
 * copied only from closed sets, anything else is reduced to its length.
 *
 * One value object and one extractor per whitelisted field of the wire, hence the size and the 13 parts of the
 * constructor (the public constructor is the factory, as in src).
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final readonly class Exchange
{
    public const EMPTY = 'empty';
    public const OBJECT = 'object';
    public const LIST = 'list';
    public const SCALAR = 'scalar';
    public const NOT_JSON = 'not_json';

    private const CESU_SEGMENT = '/^[0-9A-Za-z]{14}$/D';
    private const RETRY_AFTER_SECONDS = '/^\d{1,9}$/D';
    private const RETRY_AFTER_DATE = '/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/D';
    private const CODE_SHAPE = '/^[A-Z_]{3,40}$/D';
    private const CESU_PLUS_SPELLINGS = ['active', 'bloque', 'desactive', 'activé', 'bloqué', 'désactivé'];
    private const TYPED_KEYS = [
        'idDeclaration',
        'inscrit',
        'mandatPresent',
        'etatAICI',
        'motifBlocageCesuPlus',
        'motifblocageCesuPlus',
        'numeroCesu',
        'expires_in',
    ];

    /**
     * @param list<string>                                                 $headerNames     lower-cased response header names
     * @param list<string>                                                 $bodyKeys
     * @param array<string, string>                                        $enumValues
     * @param array<string, string>                                        $jsonTypes
     * @param null|array{length: int, trimmedLength: int, charset: string} $numeroCesuShape
     *
     * @mago-expect lint:excessive-parameter-list
     */
    public function __construct(
        public string $method,
        public string $host,
        public string $pathTemplate,
        public int $status,
        public array $headerNames,
        public ?string $retryAfter,
        public string $bodyShape,
        public array $bodyKeys,
        public array $enumValues,
        public array $jsonTypes,
        public bool $hasMoreThanTwoDecimals,
        public bool $motifIsStringNull,
        public ?array $numeroCesuShape,
    ) {}

    public static function from(RequestInterface $request, ResponseInterface $response): self
    {
        $uri = $request->getUri();
        $segments = array_map(
            static fn(string $segment): string => 1 === preg_match(self::CESU_SEGMENT, $segment) ? '{numeroCesu}' : $segment,
            explode('/', $uri->getPath()),
        );

        $headerNames = array_map(strtolower(...), array_map(strval(...), array_keys($response->getHeaders())));
        sort($headerNames);

        $body = $response->getBody();
        $text = (string) $body;
        if ($body->isSeekable()) {
            $body->rewind();
        }
        $shape = self::analyse($text);

        return new self(
            $request->getMethod(),
            $uri->getHost(),
            implode('/', $segments),
            $response->getStatusCode(),
            $headerNames,
            self::retryAfter($response->getHeaderLine('Retry-After')),
            $shape['shape'],
            $shape['keys'],
            $shape['enums'],
            $shape['types'],
            $shape['decimals'],
            $shape['motifIsStringNull'],
            $shape['numeroCesuShape'],
        );
    }

    /**
     * Codes of an error payload (400/429 bodies), only when they look like catalogue codes.
     *
     * @return list<string>
     */
    public function codes(): array
    {
        $code = $this->enumValues['code'] ?? null;

        return null === $code || str_starts_with($code, 'other(') ? [] : [$code];
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    private static function retryAfter(string $raw): ?string
    {
        if ('' === $raw) {
            return null;
        }

        return 1 === preg_match(self::RETRY_AFTER_SECONDS, $raw) || 1 === preg_match(self::RETRY_AFTER_DATE, $raw)
            ? $raw
            : 'unparseable';
    }

    /**
     * @return array{shape: string, keys: list<string>, enums: array<string, string>, types: array<string, string>, decimals: bool, motifIsStringNull: bool, numeroCesuShape: null|array{length: int, trimmedLength: int, charset: string}}
     */
    private static function analyse(string $text): array
    {
        $none = ['keys' => [], 'enums' => [], 'types' => [], 'decimals' => false, 'motifIsStringNull' => false, 'numeroCesuShape' => null];
        if ('' === trim($text)) {
            return ['shape' => self::EMPTY] + $none;
        }

        try {
            // @mago-expect analysis:mixed-assignment Decoded JSON is untyped: it is narrowed to an array right below.
            $decoded = json_decode($text, true, 64, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['shape' => self::NOT_JSON] + $none;
        }

        if (!\is_array($decoded)) {
            return ['shape' => self::SCALAR] + $none;
        }

        $isList = array_is_list($decoded);
        // @mago-expect analysis:mixed-assignment The first element of a decoded list is untyped: it is narrowed right below.
        $object = $isList ? ($decoded[0] ?? []) : $decoded;
        if (!\is_array($object) || array_is_list($object) && [] !== $object) {
            $object = [];
        }
        $keys = array_map(strval(...), array_keys($object));
        sort($keys);

        return [
            'shape' => $isList ? self::LIST : self::OBJECT,
            'keys' => $keys,
            'enums' => self::enumValues($object),
            'types' => self::jsonTypes($object),
            'decimals' => self::hasMoreThanTwoDecimals($decoded),
            'motifIsStringNull' => 'null' === ($object['motifBlocageCesuPlus'] ?? $object['motifblocageCesuPlus'] ?? null),
            'numeroCesuShape' => self::numeroCesuShape($object),
        ];
    }

    /**
     * @param array<array-key, mixed> $object
     *
     * @return array<string, string>
     */
    private static function enumValues(array $object): array
    {
        $closed = [
            'etatCesuPlus' => self::CESU_PLUS_SPELLINGS,
            'etatCompteEnLigne' => [...array_map(static fn(OnlineAccountState $s): string => $s->value, OnlineAccountState::cases()), 'absent'],
            'etatMandatSepa' => [...array_map(static fn(SepaMandateState $s): string => $s->value, SepaMandateState::cases()), 'absent'],
            'token_type' => ['Bearer', 'bearer'],
        ];
        $enums = [];
        foreach ($closed as $key => $allowed) {
            // @mago-expect analysis:mixed-assignment A decoded JSON value is untyped: it is narrowed to a string right below.
            $value = $object[$key] ?? null;
            if (!\is_string($value)) {
                continue;
            }

            $enums[$key] = \in_array($value, $allowed, true) ? $value : \sprintf('other(length=%d)', mb_strlen($value));
        }

        if (\is_string($object['code'] ?? null)) {
            $code = $object['code'];
            $enums['code'] = ApiErrorCode::fromCode($code) instanceof ApiErrorCode || 1 === preg_match(self::CODE_SHAPE, $code)
                ? $code
                : \sprintf('other(length=%d)', mb_strlen($code));
        }

        return $enums;
    }

    /**
     * @param array<array-key, mixed> $object
     *
     * @return array<string, string>
     */
    private static function jsonTypes(array $object): array
    {
        $types = [];
        foreach (self::TYPED_KEYS as $key) {
            if (!\array_key_exists($key, $object)) {
                continue;
            }

            $types[$key] = match (true) {
                \is_string($object[$key]) => 'string',
                \is_int($object[$key]) => 'integer',
                \is_float($object[$key]) => 'float',
                \is_bool($object[$key]) => 'bool',
                null === $object[$key] => 'null',
                \is_array($object[$key]) && array_is_list($object[$key]) => 'array',
                default => 'object',
            };
        }

        return $types;
    }

    /**
     * @param array<array-key, mixed> $object
     *
     * @return null|array{length: int, trimmedLength: int, charset: string}
     */
    private static function numeroCesuShape(array $object): ?array
    {
        // @mago-expect analysis:mixed-assignment A decoded JSON value is untyped: it is narrowed to a string right below.
        $value = $object['numeroCesu'] ?? null;
        if (!\is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return [
            'length' => mb_strlen($value),
            'trimmedLength' => mb_strlen($trimmed),
            'charset' => match (true) {
                1 === preg_match('/^\d+$/D', $trimmed) => 'digits',
                1 === preg_match('/^[0-9A-Z]+$/D', $trimmed) => 'alnum_upper',
                default => 'other',
            },
        ];
    }

    private static function hasMoreThanTwoDecimals(mixed $value): bool
    {
        if (\is_float($value)) {
            return abs(round($value, 2) - $value) > 1e-9;
        }
        if (\is_array($value)) {
            // @mago-expect analysis:mixed-assignment A decoded JSON value is untyped: the walk is recursive.
            foreach ($value as $item) {
                if (self::hasMoreThanTwoDecimals($item)) {
                    return true;
                }
            }
        }

        return false;
    }
}
