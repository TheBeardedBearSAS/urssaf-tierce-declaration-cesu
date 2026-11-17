<?php

declare(strict_types=1);

/*
 * This file is part of thebeardedbear/urssaf-tierce-declaration-cesu.
 *
 * Copyright (c) TheBeardedBear and the urssaf-tierce-declaration-cesu contributors
 *
 * SPDX-License-Identifier: MIT
 */

namespace TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Recipe;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;
use TheBeardedBear\UrssafTierceDeclarationCesu\Bridge\Symfony\UrssafTierceDeclarationCesuBundle;
use TheBeardedBear\UrssafTierceDeclarationCesu\Testing\SimulatedCesuGateway;
use TheBeardedBear\UrssafTierceDeclarationCesu\Tests\Functional\Symfony\Support\Harness;

/**
 * The Symfony Flex recipe stays in line with the bundle, and the endpoint generator publishes it.
 */
final class FlexRecipeTest extends TestCase
{
    private const RECIPE = __DIR__ . '/../../../recipe/thebeardedbear/urssaf-tierce-declaration-cesu/0.1';

    public function testTheManifestRegistersTheBundleAndCopiesTheConfiguration(): void
    {
        // @mago-expect analysis:mixed-assignment JSON is untyped: the assertions below narrow it.
        $manifest = json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

        static::assertIsArray($manifest);
        static::assertSame([UrssafTierceDeclarationCesuBundle::class => ['all']], $manifest['bundles']);
        static::assertSame(['config/' => '%CONFIG_DIR%/'], $manifest['copy-from-recipe']);
        static::assertIsArray($manifest['env']);
        static::assertSame(['URSSAF_CESU_ENV' => 'sandbox', 'URSSAF_CESU_CLIENT_ID' => '', 'URSSAF_CESU_CLIENT_SECRET' => ''], array_diff_key($manifest['env'], ['#1' => true]));
    }

    public function testTheCopiedConfigurationBootsTheBundle(): void
    {
        /** @var array{urssaf_tierce_declaration_cesu: array<string, mixed>, 'when@test': array{urssaf_tierce_declaration_cesu: array<string, mixed>}} $config */
        $config = self::recipeConfig();
        $main = $config['urssaf_tierce_declaration_cesu'];
        // @mago-expect lint:no-literal-password Fake credentials of a throw-away test configuration.
        foreach (['URSSAF_CESU_ENV' => 'sandbox', 'URSSAF_CESU_CLIENT_ID' => 'client', 'URSSAF_CESU_CLIENT_SECRET' => 'secret'] as $name => $value) {
            $_SERVER[$name] = $value;
            $_ENV[$name] = $value;
        }
        try {
            // Merged over the test defaults (HTTP client, in-memory persistence, a test tenant).
            $harness = Harness::boot($main);
            $command = $harness->command('urssaf:cesu:check');
            static::assertSame(0, $command->execute(['--skip-remote' => true]));
            static::assertMatchesRegularExpression('/^\| credentials +\| default +\| ok /m', $command->getDisplay());

            static::assertTrue(Harness::boot([...$main, ...$config['when@test']['urssaf_tierce_declaration_cesu']])->has(SimulatedCesuGateway::class));
        } finally {
            unset($_SERVER['URSSAF_CESU_ENV'], $_ENV['URSSAF_CESU_ENV'], $_SERVER['URSSAF_CESU_CLIENT_ID'], $_ENV['URSSAF_CESU_CLIENT_ID'], $_SERVER['URSSAF_CESU_CLIENT_SECRET'], $_ENV['URSSAF_CESU_CLIENT_SECRET']);
        }
    }

    public function testTheEndpointGeneratorPublishesTheRecipe(): void
    {
        $output = \dirname(__DIR__, 3) . '/var/test-flex-endpoint';
        (new Filesystem())->remove($output);
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint', $output, 'https://flex.example.com/urssaf-cesu/']);

        static::assertSame(0, $process->run());

        $index = (string) file_get_contents($output . '/index.json');
        static::assertStringContainsString('"aliases": {}', $index);
        static::assertSame([
            'aliases' => [],
            'recipes' => ['thebeardedbear/urssaf-tierce-declaration-cesu' => ['0.1']],
            'branch' => 'main',
            'is_contrib' => false,
            '_links' => [
                'repository' => 'https://flex.example.com/urssaf-cesu',
                'origin_template' => '{package}:{version}@https://flex.example.com/urssaf-cesu',
                'recipe_template' => 'https://flex.example.com/urssaf-cesu/{package_dotted}.{version}.json',
            ],
        ], json_decode($index, true, 512, JSON_THROW_ON_ERROR));
        /** @var array{manifests: array<string, array{manifest: mixed, files: array<string, array{contents: list<string>, executable: bool}>, ref: string}>} $recipe */
        $recipe = json_decode((string) file_get_contents($output . '/thebeardedbear.urssaf-tierce-declaration-cesu.0.1.json'), true, 512, JSON_THROW_ON_ERROR);
        $manifest = $recipe['manifests']['thebeardedbear/urssaf-tierce-declaration-cesu'];
        static::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', $manifest['ref']);
        static::assertSame(['config/packages/urssaf_tierce_declaration_cesu.yaml'], array_keys($manifest['files']));
        $file = $manifest['files']['config/packages/urssaf_tierce_declaration_cesu.yaml'];
        static::assertFalse($file['executable']);
        static::assertSame((string) file_get_contents(self::RECIPE . '/config/packages/urssaf_tierce_declaration_cesu.yaml'), implode("\n", $file['contents']) . "\n");
        static::assertSame(json_decode((string) file_get_contents(self::RECIPE . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR), $manifest['manifest']);
        (new Filesystem())->remove($output);
    }

    public function testTheGeneratorNeedsItsArguments(): void
    {
        $process = new Process([PHP_BINARY, \dirname(__DIR__, 3) . '/bin/build-flex-endpoint']);

        static::assertSame(2, $process->run());
        static::assertStringContainsString('Usage: bin/build-flex-endpoint <output-dir> <base-url>', $process->getErrorOutput());
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function recipeConfig(): array
    {
        // @mago-expect analysis:mixed-assignment YAML is untyped: the assertion below narrows it.
        $config = Yaml::parseFile(self::RECIPE . '/config/packages/urssaf_tierce_declaration_cesu.yaml');
        static::assertIsArray($config);

        return $config;
    }
}
