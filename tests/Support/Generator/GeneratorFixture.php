<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support\Generator;

use FilesystemIterator;
use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Generator;
use GoSuccess\UptimeRobot\Tools\Generator\Spec;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Runs the generator on a small synthetic specification and configuration.
 *
 * generate() writes the code into a temporary directory under a namespace of
 * its own, loads every class and removes the directory again, so tests can
 * call the generated code.
 */
final class GeneratorFixture
{
    private static int $counter = 0;

    /**
     * Analyze without writing anything.
     *
     * @param array<string, mixed> $spec   An OpenAPI document; "openapi" is added.
     * @param array<string, mixed> $config Configuration; title, spec, namespace, client
     *                                     and baseUri default to fixture values.
     */
    public static function analyze(array $spec, array $config): Analysis
    {
        return Generator::analyzeSpec(self::config($config, 'GoSuccess\\UptimeRobot\\Tests\\Fixture\\Unused'), Spec::fromDocument('fixture', ['openapi' => '3.0.0', ...$spec]));
    }

    /**
     * Generate the code, load it and return the analysis. The generated
     * classes live in the namespace self::namespaceOf($analysis).
     *
     * @param array<string, mixed> $spec
     * @param array<string, mixed> $config
     * @param array<string, string> $extraFiles Files to create below the source
     *                                         directory before generating, e.g. traits.
     */
    public static function generate(array $spec, array $config, array $extraFiles = []): Analysis
    {
        $root = self::temporaryDirectory();

        try {
            return self::generateIn($root, $spec, $config, $extraFiles, true);
        } finally {
            self::remove($root);
        }
    }

    /**
     * Generate into a directory that is kept, e.g. to inspect the files.
     *
     * @param array<string, mixed>  $spec
     * @param array<string, mixed>  $config
     * @param array<string, string> $extraFiles
     */
    public static function generateIn(string $root, array $spec, array $config, array $extraFiles = [], bool $load = false): Analysis
    {
        $namespace = \is_string($config['namespace'] ?? null) ? $config['namespace'] : 'GoSuccess\\UptimeRobot\\Tests\\Fixture\\Generated' . ++self::$counter . '_' . bin2hex(random_bytes(4));
        $apiConfig = self::config($config, $namespace);

        if (!is_dir("{$root}/resources/specs") && !mkdir("{$root}/resources/specs", 0o775, true)) {
            throw new RuntimeException("Cannot create {$root}/resources/specs.");
        }

        file_put_contents("{$root}/resources/specs/fixture.json", json_encode(['openapi' => '3.0.0', ...$spec], \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));

        foreach ($extraFiles as $path => $content) {
            $file = "{$root}/{$apiConfig->directory}/{$path}";

            if (!is_dir(\dirname($file))) {
                mkdir(\dirname($file), 0o775, true);
            }

            file_put_contents($file, $content);
        }

        $generator = new Generator($apiConfig, $root);
        $generator->run();
        $analysis = $generator->analyze();

        if ($load) {
            self::load($root, $apiConfig);
        }

        return $analysis;
    }

    public static function namespaceOf(Analysis $analysis): string
    {
        return $analysis->config->namespace;
    }

    public static function temporaryDirectory(): string
    {
        $root = sys_get_temp_dir() . '/uptimerobot-generator-' . bin2hex(random_bytes(6));

        if (!mkdir($root, 0o775, true)) {
            throw new RuntimeException("Cannot create {$root}.");
        }

        return $root;
    }

    public static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }

    /**
     * @param array<string, mixed> $config
     */
    private static function config(array $config, string $namespace): ApiConfig
    {
        return ApiConfig::fromArray('fixture', [
            'title' => 'Fixture API',
            'spec' => 'fixture',
            'client' => 'FixtureClient',
            'baseUri' => 'https://api.example.com/v3',
            ...$config,
            'namespace' => $namespace,
        ]);
    }

    /**
     * Load every generated class while the files exist.
     */
    private static function load(string $root, ApiConfig $config): void
    {
        $directory = "{$root}/{$config->directory}";
        $prefix = "{$config->namespace}\\";

        $autoload = static function (string $class) use ($directory, $prefix): void {
            if (str_starts_with($class, $prefix)) {
                $file = "{$directory}/" . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

                if (is_file($file)) {
                    require $file;
                }
            }
        };

        spl_autoload_register($autoload);

        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                    $class = $prefix . str_replace('/', '\\', substr($file->getPathname(), \strlen($directory) + 1, -4));

                    if (!class_exists($class) && !interface_exists($class) && !enum_exists($class) && !trait_exists($class)) {
                        throw new RuntimeException("{$file->getPathname()} does not declare {$class}.");
                    }
                }
            }
        } finally {
            spl_autoload_unregister($autoload);
        }
    }
}
