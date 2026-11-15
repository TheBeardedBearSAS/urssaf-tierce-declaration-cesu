<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use SensitiveParameter;
use TheBeardedBear\UrssafTierceDeclarationCesu\Application\Port\Credentials;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Employer\Employer;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Exception\InvalidValue;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CalendarDate;
use TheBeardedBear\UrssafTierceDeclarationCesu\Domain\Shared\CesuNumber;

/**
 * The URSSAF_SANDBOX_* variables, read once and validated lazily: a missing value never throws, a configured but wrong
 * value throws a SandboxMisconfigured naming the variable only.
 *
 * One accessor per variable, hence the method count and the complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 */
final readonly class SandboxEnvironment
{
    public const CLIENT_ID = 'URSSAF_SANDBOX_CLIENT_ID';
    // @mago-expect lint:no-literal-password The constant is the name of the environment variable, not a secret.
    public const CLIENT_SECRET = 'URSSAF_SANDBOX_CLIENT_SECRET';
    public const BASE_URI = 'URSSAF_SANDBOX_BASE_URI';
    public const TOKEN_URI = 'URSSAF_SANDBOX_TOKEN_URI';
    public const EMPLOYER_CESU_NUMBER = 'URSSAF_SANDBOX_EMPLOYER_CESU_NUMBER';
    public const EMPLOYER_BIRTH_DATE = 'URSSAF_SANDBOX_EMPLOYER_BIRTH_DATE';
    public const EMPLOYEE_CESU_NUMBER = 'URSSAF_SANDBOX_EMPLOYEE_CESU_NUMBER';
    public const ALLOW_WRITES = 'URSSAF_SANDBOX_ALLOW_WRITES';
    public const PRODUCTION_HOST = 'api.urssaf.fr';

    /**
     * @param array<string, string> $values the raw variables, by name
     */
    private function __construct(
        #[SensitiveParameter]
        private array $values,
    ) {}

    public static function fromProcess(): self
    {
        $values = [];
        foreach (
            [
                self::CLIENT_ID,
                self::CLIENT_SECRET,
                self::BASE_URI,
                self::TOKEN_URI,
                self::EMPLOYER_CESU_NUMBER,
                self::EMPLOYER_BIRTH_DATE,
                self::EMPLOYEE_CESU_NUMBER,
                self::ALLOW_WRITES,
            ] as $name
        ) {
            $value = self::env($name);
            if (null !== $value) {
                $values[$name] = $value;
            }
        }

        return new self($values);
    }

    /**
     * @param array<string, string> $values
     */
    public static function fromArray(#[SensitiveParameter] array $values): self
    {
        return new self($values);
    }

    /**
     * Trimmed value of a process variable, null when unset or blank.
     */
    public static function env(string $name): ?string
    {
        $value = getenv($name);

        return \is_string($value) && '' !== trim($value) ? trim($value) : null;
    }

    public function hasCredentials(): bool
    {
        return \array_key_exists(self::CLIENT_ID, $this->values) && \array_key_exists(self::CLIENT_SECRET, $this->values);
    }

    public function credentials(): Credentials
    {
        return Credentials::of($this->values[self::CLIENT_ID] ?? '', $this->values[self::CLIENT_SECRET] ?? '');
    }

    /**
     * @throws SandboxMisconfigured when the host is the production host
     */
    public function baseUri(): ?string
    {
        $baseUri = $this->values[self::BASE_URI] ?? null;
        if (null === $baseUri) {
            return null;
        }

        $host = parse_url($baseUri, \PHP_URL_HOST);
        if (\is_string($host) && self::PRODUCTION_HOST === strtolower($host)) {
            throw SandboxMisconfigured::variable(self::BASE_URI, 'targets the production host, which is refused', null);
        }

        return $baseUri;
    }

    public function tokenUri(): ?string
    {
        return $this->values[self::TOKEN_URI] ?? null;
    }

    /**
     * @throws SandboxMisconfigured
     */
    public function employer(): ?Employer
    {
        $number = $this->cesuNumber(self::EMPLOYER_CESU_NUMBER);
        $birthDate = $this->values[self::EMPLOYER_BIRTH_DATE] ?? null;
        if (!$number instanceof CesuNumber) {
            return null;
        }
        if (null === $birthDate) {
            throw SandboxMisconfigured::variable(self::EMPLOYER_BIRTH_DATE, 'is required with ' . self::EMPLOYER_CESU_NUMBER, null);
        }

        try {
            return Employer::of($number, CalendarDate::fromString($birthDate));
        } catch (InvalidValue $e) {
            throw SandboxMisconfigured::variable(self::EMPLOYER_BIRTH_DATE, 'is not a YYYY-MM-DD date', $e);
        }
    }

    /**
     * @throws SandboxMisconfigured
     */
    public function employeeCesuNumber(): ?CesuNumber
    {
        return $this->cesuNumber(self::EMPLOYEE_CESU_NUMBER);
    }

    public function writesAllowed(): bool
    {
        return '1' === ($this->values[self::ALLOW_WRITES] ?? null);
    }

    /**
     * @throws SandboxMisconfigured
     */
    private function cesuNumber(string $variable): ?CesuNumber
    {
        $value = $this->values[$variable] ?? null;
        if (null === $value) {
            return null;
        }

        try {
            return CesuNumber::fromString($value);
        } catch (InvalidValue $e) {
            throw SandboxMisconfigured::variable($variable, 'is not a 14-character Cesu number', $e);
        }
    }
}
