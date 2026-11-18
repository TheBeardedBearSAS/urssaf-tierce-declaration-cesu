<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\Http;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Operation;

/**
 * Description of one API call, independent from the HTTP library (ADR 0002).
 *
 * Queries carry NIR and names (021), bodies carry IBAN, NIR and birth dates (ADR 0007): the constructor and the
 * factories mark them sensitive and {@see self::__debugInfo()} never shows their values.
 *
 * @internal
 */
final readonly class ApiRequest
{
    /**
     * @param array<string, string> $query
     */
    private function __construct(
        private Operation $operation,
        private string $method,
        private string $path,
        #[SensitiveParameter]
        private array $query,
        #[SensitiveParameter]
        private ?string $jsonBody,
    ) {}

    /**
     * @param array<string, string> $query
     */
    public static function get(Operation $operation, string $path, #[SensitiveParameter] array $query): self
    {
        return new self($operation, 'GET', $path, $query, null);
    }

    /**
     * @param array<string, string> $query
     * @param string|null           $jsonBody null means no body (030, 060)
     */
    public static function post(
        Operation $operation,
        string $path,
        #[SensitiveParameter]
        array $query,
        #[SensitiveParameter]
        ?string $jsonBody,
    ): self {
        return new self($operation, 'POST', $path, $query, $jsonBody);
    }

    /**
     * @param array<string, string> $query
     */
    public static function delete(Operation $operation, string $path, #[SensitiveParameter] array $query): self
    {
        return new self($operation, 'DELETE', $path, $query, null);
    }

    public function operation(): Operation
    {
        return $this->operation;
    }

    public function method(): string
    {
        return $this->method;
    }

    /**
     * Relative to {@see \TheBeardedBear\UrssafTierceDeclarationCesu\Infrastructure\Urssaf\UrssafEndpoints::API_BASE_PATH}.
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * @return array<string, string>
     */
    public function query(): array
    {
        return $this->query;
    }

    public function jsonBody(): ?string
    {
        return $this->jsonBody;
    }

    /**
     * @return array{operation: string, method: string, path: string, query: list<int|string>, jsonBody: string|null}
     */
    public function __debugInfo(): array
    {
        return [
            'operation' => $this->operation->methodNumber(),
            'method' => $this->method,
            'path' => $this->path,
            'query' => array_keys($this->query),
            'jsonBody' => $this->jsonBody === null ? null : '***',
        ];
    }
}
