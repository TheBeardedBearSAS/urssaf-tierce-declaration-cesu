<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Contract;

use TheBeardedBear\UrssafTierceDeclarationCesu\SpecVersion;

/**
 * What the sandbox answered for each open point of docs/api-reference.md section 5, written to
 * var/contract/sandbox-report.json. Values come from Exchange (whitelisted) or from SDK enums, booleans and integers:
 * never a Cesu number, a birth date, a name, an e-mail, an IBAN, a token, a message or a query string.
 */
final class SandboxReport
{
    public const OBSERVED = 'observed';
    public const NOT_OBSERVED = 'not_observed';
    public const NOT_RUN = 'not_run';

    public const POINTS = [
        '5.1-etatCesuPlus',
        '5.2-motifBlocage',
        '5.6-accord',
        '5.9-020-employer-only',
        '5.14-token-host',
        '5.15-headers',
        '5.15-429',
        '5.16-010-mandate',
        '5.17-030-repost',
        '5.17-031-cancel-absent',
        '5.18-020-unknown',
        '5.18-021-unknown',
        '5.19-400-shape',
        '5.23-employer-cesu-format',
        '5.23-employee-cesu-format',
        '5.31-020-absent-flags',
        '5.32-estimate-decimals',
        '5.33-idDeclaration-type',
        '5.34-021-numeroCesu-padding',
        '050-duplicate',
        '070-aici',
    ];

    private const RANKS = [self::NOT_RUN => 0, self::NOT_OBSERVED => 1, self::OBSERVED => 2];

    /** @var null|array<string, array{point: string, state: string, value: mixed, test: string}> */
    private static ?array $points = null;
    private static ?string $path = null;
    private static string $tokenHost = 'unknown';
    private static string $baseUriHost = 'unknown';
    private static bool $writesAllowed = false;

    /**
     * Records an observation; a stronger state replaces a weaker one (observed > not_observed > not_run), whatever the
     * (random) order of the tests.
     */
    public static function record(string $point, string $state, mixed $value, string $test): void
    {
        $points = self::points();
        $current = $points[$point] ?? null;
        if (null !== $current && self::RANKS[$state] < self::RANKS[$current['state']]) {
            return;
        }

        self::$points = [...$points, $point => ['point' => $point, 'state' => $state, 'value' => $value, 'test' => $test]];
    }

    public static function describeRun(string $baseUriHost, string $tokenHost, bool $writesAllowed): void
    {
        self::$baseUriHost = $baseUriHost;
        self::$tokenHost = $tokenHost;
        self::$writesAllowed = $writesAllowed;
    }

    /**
     * @return array<string, array{point: string, state: string, value: mixed, test: string}>
     */
    public static function points(): array
    {
        if (null === self::$points) {
            self::$points = [];
            foreach (self::POINTS as $point) {
                self::$points[$point] = ['point' => $point, 'state' => self::NOT_RUN, 'value' => null, 'test' => ''];
            }
        }

        return self::$points;
    }

    /**
     * Rewrites the file with everything recorded so far by the process (the first flush replaces any previous run's file).
     */
    public static function flush(): void
    {
        $path = self::path();
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        $points = [];
        foreach (self::points() as $id => $entry) {
            $points[$id] = ['state' => $entry['state'], 'value' => $entry['value'], 'test' => $entry['test']];
        }

        $content = json_encode([
            'schema' => 1,
            'generatedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            'specVersion' => SpecVersion::CURRENT,
            'baseUriHost' => self::$baseUriHost,
            'tokenHost' => self::$tokenHost,
            'writesAllowed' => self::$writesAllowed,
            'points' => $points,
        ], \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);

        file_put_contents($path, $content . "\n");
    }

    public static function usePath(?string $path): void
    {
        self::$path = $path;
    }

    /**
     * Empties the report and returns what it held, for the offline tests of the report itself to put back afterwards
     * (they must not wipe the observations of live tests of the same process).
     *
     * @return array{points: null|array<string, array{point: string, state: string, value: mixed, test: string}>, path: null|string, tokenHost: string, baseUriHost: string, writesAllowed: bool}
     */
    public static function reset(): array
    {
        $saved = [
            'points' => self::$points,
            'path' => self::$path,
            'tokenHost' => self::$tokenHost,
            'baseUriHost' => self::$baseUriHost,
            'writesAllowed' => self::$writesAllowed,
        ];
        self::$points = null;
        self::$path = null;
        self::$tokenHost = 'unknown';
        self::$baseUriHost = 'unknown';
        self::$writesAllowed = false;

        return $saved;
    }

    /**
     * @param array{points: null|array<string, array{point: string, state: string, value: mixed, test: string}>, path: null|string, tokenHost: string, baseUriHost: string, writesAllowed: bool} $saved
     */
    public static function restore(array $saved): void
    {
        self::$points = $saved['points'];
        self::$path = $saved['path'];
        self::$tokenHost = $saved['tokenHost'];
        self::$baseUriHost = $saved['baseUriHost'];
        self::$writesAllowed = $saved['writesAllowed'];
    }

    private static function path(): string
    {
        return self::$path ?? \dirname(__DIR__, 2) . '/var/contract/sandbox-report.json';
    }
}
