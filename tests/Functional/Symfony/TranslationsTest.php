<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * The shipped translations cover every violation code of the package, and only them (reference TranslationsTest;
 * lot 6 design §8.3).
 *
 * The codes are found by scanning the sources: literal codes, then the codes composed from a subject and a reason
 * ("<subject>.<reason>"), which is how {@see InvalidValue} and the violation collector build them.
 *
 * One test per translation invariant and one scanner step per way of composing a code, hence the method count and the
 * complexity.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class TranslationsTest extends TestCase
{
    /** Locales shipped by the bundle. */
    private const LOCALES = ['en', 'fr'];

    /**
     * Doctrine repositories, scanned file by file: they hold the "<aggregate>.concurrent_modification" codes (lot 7). Not
     * the whole directory: DoctrineWorkflowStatistics holds DQL field literals ("tracking.stage", "status.state") that
     * the literal scan would take for codes; the duplicate codes of the repositories are the in-memory ones.
     */
    private const SCANNED_FILES = [
        'src/Bridge/Doctrine/Repository/DoctrineDeclarationRecordRepository.php',
        'src/Bridge/Doctrine/Repository/DoctrineEmployerAccountRepository.php',
        'src/Bridge/Doctrine/Repository/DoctrineMandateRepository.php',
    ];

    /** Directories scanned for codes, relative to the package root. */
    private const SCANNED = [
        'src/Domain',
        'src/Application',
        'src/Infrastructure/Persistence',
        'src/Infrastructure/Credentials',
    ];

    /** Codes that are technical (configuration, wire format, programming errors) and never shown to an end user. */
    private const NOT_TRANSLATED = [
        'access_token.negative_margin',
        'api_error.empty_code',
        'credentials.client_id_empty',
        'credentials.client_secret_empty',
        'outcome_unknown.not_reached',
        'validation.failed',
        'violation.invalid_code',
        'violation.invalid_path',
        'workflow_policy.default_page_size_above_max',
        'workflow_policy.invalid_limit',
        'workflow_policy.invalid_max_submission_attempts',
        'workflow_policy.invalid_page_size',
    ];

    /**
     * Files that give a variable subject to an InvalidValue factory: each one needs an entry
     * of {@see self::dynamicSubjects()}. A new one fails the test until it is mapped.
     */
    private const DYNAMIC_SUBJECT_FILES = [
        'src/Domain/Declaration/Coefficient.php',
        'src/Domain/Declaration/WorkedHours.php',
        'src/Domain/Employer/Address/AddressText.php',
        'src/Domain/Employer/Identity/CivilName.php',
        'src/Domain/Employer/Identity/PersonName.php',
        'src/Domain/Shared/Decimal.php',
        'src/Domain/Shared/HalfDays.php',
        'src/Domain/Shared/Money.php',
        'src/Domain/Validation/ViolationCollector.php',
    ];

    private const CODE = '[a-z][a-z0-9_]*';

    /**
     * @return iterable<string, array{string}>
     */
    public static function locales(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('locales')]
    public function testEveryViolationCodeHasAMessage(string $locale): void
    {
        $missing = array_values(array_diff(self::codesInSources(), self::NOT_TRANSLATED, array_keys(self::messages($locale))));

        static::assertSame([], $missing, 'Violation codes without a message in "' . $locale . '".');
    }

    #[DataProvider('locales')]
    public function testNoMessageWithoutCode(string $locale): void
    {
        $orphans = array_values(array_diff(array_keys(self::messages($locale)), self::codesInSources()));

        static::assertSame([], $orphans, 'Messages of "' . $locale . '" for a code that no source produces.');
    }

    public function testTheTechnicalCodesAreProducedByTheSources(): void
    {
        static::assertSame([], array_values(array_diff(self::NOT_TRANSLATED, self::codesInSources())));
    }

    #[DataProvider('locales')]
    public function testEveryMessageIsAFilledSentence(string $locale): void
    {
        foreach (self::messages($locale) as $code => $message) {
            static::assertMatchesRegularExpression('/^' . self::CODE . '(\.' . self::CODE . ')+$/', $code);
            static::assertMatchesRegularExpression('/^\p{Lu}.+\.$/u', $message, $code);
        }
    }

    #[DataProvider('locales')]
    public function testEveryLocaleHasTheKeysOfTheEnglishFileInTheSameOrder(string $locale): void
    {
        static::assertSame(array_keys(self::messages('en')), array_keys(self::messages($locale)));
    }

    #[DataProvider('locales')]
    public function testTheKeysAreSorted(string $locale): void
    {
        $keys = array_keys(self::messages($locale));
        $sorted = $keys;
        sort($sorted, SORT_STRING);

        static::assertSame($sorted, $keys);
    }

    public function testTheSuffixMapCoversEveryFactory(): void
    {
        $map = self::suffixMap();

        static::assertSame(
            [
                'empty' => '.empty',
                'invalidCharacters' => '.invalid_characters',
                'invalidFormat' => '.invalid_format',
                'negative' => '.negative',
                'notMultipleOf' => '.not_multiple_of',
                'notPositive' => '.not_positive',
                'outOfRange' => '.out_of_range',
                'tooLarge' => '.too_large',
                'tooLong' => '.too_long',
                'tooManyDecimals' => '.too_many_decimals',
                'unknown' => '.unknown',
            ],
            self::sorted($map),
        );
        static::assertCount(11, $map);
    }

    public function testTheDynamicSubjectFilesAreKnown(): void
    {
        $found = [];
        foreach (self::sources() as $path => $content) {
            if (preg_match('/InvalidValue::(?!because\b)\w+\(\s*\$/', $content) !== 1) {
                continue;
            }
            $found[] = $path;
        }
        sort($found);

        static::assertSame(self::DYNAMIC_SUBJECT_FILES, $found);
    }

    public function testTheFrameworkTranslatorLoadsTheDomain(): void
    {
        $app = Harness::boot();
        $translator = $app->privateService('translator', TranslatorInterface::class);

        static::assertSame(
            'La période d\'emploi ne doit pas commencer après aujourd\'hui.',
            $translator->trans('employment_period.starts_after_today', [], 'urssaf_tierce_declaration_cesu', 'fr'),
        );
        static::assertSame(
            'The employment period must not start after today.',
            $translator->trans('employment_period.starts_after_today', [], 'urssaf_tierce_declaration_cesu', 'en'),
        );
    }

    /**
     * @param array<string, string> $map
     *
     * @return array<string, string>
     */
    private static function sorted(array $map): array
    {
        ksort($map);

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private static function messages(string $locale): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped; every entry is checked below.
        $parsed = Yaml::parseFile(self::root() . '/src/Bridge/Symfony/Resources/translations/urssaf_tierce_declaration_cesu.' . $locale . '.yaml');
        static::assertIsArray($parsed);
        $messages = [];
        foreach ($parsed as $code => $message) { // @mago-expect analysis:mixed-assignment narrowed by the assertion below.
            static::assertIsString($message);
            $messages[(string) $code] = $message;
        }

        return $messages;
    }

    private static function root(): string
    {
        return \dirname(__DIR__, 3);
    }

    /**
     * The PHP sources of the scanned directories, by path relative to the package root.
     *
     * @return array<string, string>
     */
    private static function sources(): array
    {
        $sources = [];
        foreach (self::SCANNED as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::root() . '/' . $directory, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($files as $file) {
                Assert::assertInstanceOf(SplFileInfo::class, $file);
                if ($file->getExtension() === 'php') {
                    $sources[substr($file->getPathname(), \strlen(self::root()) + 1)] = (string) file_get_contents($file->getPathname());
                }
            }
        }
        foreach (self::SCANNED_FILES as $file) {
            $sources[$file] = (string) file_get_contents(self::root() . '/' . $file);
        }
        ksort($sources);

        return $sources;
    }

    /**
     * The reason suffix of every InvalidValue factory taking a subject, read from InvalidValue itself:
     * "empty" => ".empty", "tooLong" => ".too_long"...
     *
     * @return array<string, string>
     */
    private static function suffixMap(): array
    {
        $content = (string) file_get_contents(self::root() . '/src/Domain/Exception/InvalidValue.php');
        $map = [];
        foreach (\array_slice(explode('public static function ', $content), 1) as $method) {
            $name = [];
            if (preg_match('/^(\w+)\(string \$subject/', $method, $name) !== 1) {
                continue;
            }
            $suffix = [];
            Assert::assertSame(1, preg_match('/\$subject \. \'(\.[a-z_]+)\'/', $method, $suffix), 'No suffix in InvalidValue::' . $name[1]);
            $map[$name[1]] = $suffix[1];
        }

        return $map;
    }

    /**
     * The scalar constants of a file, `const NAME = 'value';`.
     *
     * @return array<string, string>
     */
    private static function constants(string $content): array
    {
        $matches = [];
        preg_match_all('/const ([A-Z_]+) = \'(' . self::CODE . ')\';/', $content, $matches, PREG_SET_ORDER);
        $constants = [];
        foreach ($matches as $match) {
            $constants[$match[1] ?? ''] = $match[2] ?? '';
        }

        return $constants;
    }

    /**
     * @param array<string, string> $constants
     */
    private static function resolve(string $literal, string $constant, array $constants, string $path): string
    {
        if ($literal !== '') {
            return $literal;
        }
        Assert::assertArrayHasKey($constant, $constants, 'Unresolved constant ' . $constant . ' in ' . $path);

        return $constants[$constant];
    }

    /**
     * @return list<string>
     */
    private static function codesInSources(): array
    {
        $suffixes = self::suffixMap();
        $codes = [];
        foreach (self::sources() as $path => $content) {
            $constants = self::constants($content);
            array_push(
                $codes,
                ...self::literalCodes($content),
                ...self::subjectCodes($path, $content, $constants, $suffixes),
                ...self::collectorCodes($content),
                ...self::prefixedCodes($path, $content, $constants),
            );
        }
        array_push($codes, ...self::dynamicCodes($suffixes));
        $codes = array_values(array_unique($codes));
        sort($codes, SORT_STRING);

        return $codes;
    }

    /**
     * 1. The literal codes.
     *
     * @return list<string>
     */
    private static function literalCodes(string $content): array
    {
        $matches = [];
        preg_match_all('/\'(' . self::CODE . '(?:\.' . self::CODE . ')+)\'/', $content, $matches);

        return array_values($matches[1]);
    }

    /**
     * 3. The codes composed from a literal or constant subject by a factory of a known reason.
     *
     * @param array<string, string> $constants
     * @param array<string, string> $suffixes
     *
     * @return list<string>
     */
    private static function subjectCodes(string $path, string $content, array $constants, array $suffixes): array
    {
        $codes = [];
        $matches = [];
        preg_match_all('/InvalidValue::(\w+)\(\s*(?:\'(' . self::CODE . ')\'|self::([A-Z_]+))/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!\array_key_exists($match[1] ?? '', $suffixes)) {
                continue;
            }
            $codes[] = self::resolve($match[2] ?? '', $match[3] ?? '', $constants, $path) . $suffixes[$match[1]];
        }
        foreach (['RecordNotFound::withIdentifier' => '.not_found', 'InvalidStateTransition::between' => '.invalid_state_transition'] as $call => $suffix) {
            preg_match_all('/' . preg_quote($call, '/') . '\(\s*(?:\'(' . self::CODE . ')\'|self::([A-Z_]+))/', $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $codes[] = self::resolve($match[1] ?? '', $match[2] ?? '', $constants, $path) . $suffix;
            }
        }

        return $codes;
    }

    /**
     * 3. The codes of the violation collector: "<subject>.required" and "<subject>.out_of_range".
     *
     * @return list<string>
     */
    private static function collectorCodes(string $content): array
    {
        $codes = [];
        $matches = [];
        $argument = '(?:\'[^\']*\'|"[^"]*"|\$[\w\->]+|\\\\?sprintf\([^()]*\))';
        preg_match_all('/->(required|requiredInteger|optionalInteger|requiredBool)\(\s*' . $argument . '\s*,\s*\'(' . self::CODE . ')\'/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] !== 'optionalInteger') {
                $codes[] = $match[2] . '.required';
            }
            if ($match[1] === 'requiredInteger' || $match[1] === 'optionalInteger') {
                $codes[] = $match[2] . '.out_of_range';
            }
        }

        return $codes;
    }

    /**
     * 3b. The codes composed from a constant prefix and a literal suffix.
     *
     * @param array<string, string> $constants
     *
     * @return list<string>
     */
    private static function prefixedCodes(string $path, string $content, array $constants): array
    {
        $codes = [];
        $matches = [];
        preg_match_all('/self::([A-Z_]+) \. \'(\.' . self::CODE . ')\'/', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $codes[] = self::resolve('', $match[1] ?? '', $constants, $path) . ($match[2] ?? '');
        }

        return $codes;
    }

    /**
     * 4. The codes of the files that give a variable subject to a factory: the subjects of their callers, with the
     * reasons of the factories they call.
     *
     * @param array<string, string> $suffixes
     *
     * @return list<string>
     */
    private static function dynamicCodes(array $suffixes): array
    {
        $sources = self::sources();
        $codes = [];
        foreach (self::DYNAMIC_SUBJECT_FILES as $path) {
            $content = $sources[$path];
            $matches = [];
            preg_match_all('/InvalidValue::(?!because\b)(\w+)\(\s*\$/', $content, $matches);
            $reasons = [];
            foreach ($matches[1] as $factory) {
                $reasons[] = $suffixes[$factory];
            }
            foreach (self::dynamicSubjects($path, $sources) as $subject) {
                foreach ($reasons as $reason) {
                    $codes[] = $subject . $reason;
                }
            }
        }

        return $codes;
    }

    /**
     * The subjects given by the callers of a dynamic-subject file.
     *
     * @param array<string, string> $sources
     *
     * @return list<string>
     */
    private static function dynamicSubjects(string $file, array $sources): array
    {
        $pattern = match ($file) {
            'src/Domain/Employer/Identity/PersonName.php', 'src/Domain/Employer/Identity/CivilName.php', 'src/Domain/Employer/Address/AddressText.php' => ['own', '/self::create\([^;]*?\'(' . self::CODE . ')\'/'],
            'src/Domain/Declaration/WorkedHours.php' => ['own', '/self::assertRange\([^;]*?\'(' . self::CODE . ')\'/'],
            'src/Domain/Shared/Money.php', 'src/Domain/Shared/HalfDays.php' => ['all', '/->assertAtMost\([^;]*?\'(' . self::CODE . ')\'\s*,?\s*\)/'],
            'src/Domain/Declaration/Coefficient.php' => ['all', '/->assertBetween\([^;]*?\'(' . self::CODE . ')\'\s*,?\s*\)/'],
            'src/Domain/Shared/Decimal.php' => ['decimal', '/Decimal::parse\([^,]+,\s*(?:\'(' . self::CODE . ')\'|self::([A-Z_]+))/'],
            // The subjects of the collector callers are the codes of step 3 (its factory calls take no literal subject).
            default => ['none', ''],
        };
        $subjects = [];
        $files = $pattern[0] === 'own' ? [$file => $sources[$file]] : $sources;
        if ($pattern[0] === 'none') {
            return [];
        }
        foreach ($files as $path => $content) {
            $matches = [];
            preg_match_all($pattern[1], $content, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $subjects[] = $pattern[0] === 'decimal' ? self::resolve($match[1] ?? '', $match[2] ?? '', self::constants($content), $path) : ($match[1] ?? '');
            }
        }

        return array_values(array_unique($subjects));
    }
}
