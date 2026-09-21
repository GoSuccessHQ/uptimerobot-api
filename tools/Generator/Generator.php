<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ResourceDefinition;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\ClientWriter;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\CodeFile;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\EnumWriter;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\ModelWriter;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\ResourceWriter;
use GoSuccess\UptimeRobot\Tools\Generator\Writer\UnionWriter;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Generates the code of an API from its specification snapshot and its
 * configuration.
 */
final class Generator
{
    /**
     * @param string $root Project root: the snapshot is read from resources/specs/
     *                     and the code is written below the configured directory.
     */
    public function __construct(
        private readonly ApiConfig $config,
        private readonly string $root,
    ) {}

    public static function fromFile(string $configFile): self
    {
        return new self(ApiConfig::load($configFile), \dirname(__DIR__, 2));
    }

    /**
     * Build the definitions of everything the API needs, without writing files.
     */
    public function analyze(): Analysis
    {
        $config = $this->config;

        return self::analyzeSpec($config, Spec::load($config->spec, "{$this->root}/resources/specs/{$config->spec}.json"));
    }

    /**
     * Analyze a loaded specification, e.g. a synthetic one in tests.
     */
    public static function analyzeSpec(ApiConfig $config, Spec $spec): Analysis
    {
        $spec = $spec->patched($config->additionalSchemas, $config->additionalProperties);
        self::checkNames($spec, $config);
        $registry = new Registry($spec, $config);
        $builder = new ResourceBuilder($registry);

        $resources = [];
        // Lower-cased class => client property; one class file would overwrite the other.
        $classes = [];

        foreach ($config->resources as $resourceConfig) {
            $key = strtolower($resourceConfig->class);

            if (isset($classes[$key])) {
                throw new RuntimeException("resources.{$classes[$key]} and resources.{$resourceConfig->property} both use the class {$resourceConfig->class}.");
            }

            $classes[$key] = $resourceConfig->property;
            $resources[] = $builder->build($resourceConfig);
        }

        foreach ($config->extraModels as $schema) {
            $registry->markUsage(new PhpType(PhpType::MODEL, $registry->requireModel($schema)), false);
        }

        $problems = [...self::coverageProblems($spec, $config, $resources), ...$registry->problems()];

        if ($problems !== []) {
            throw new RuntimeException("The configuration does not match the specification:\n  " . implode("\n  ", $problems));
        }

        return new Analysis($config, $registry, $resources, $builder->notes);
    }

    public function run(): string
    {
        $analysis = $this->analyze();
        $config = $analysis->config;
        $registry = $analysis->registry;
        $resources = $analysis->resources;

        foreach ($resources as $resource) {
            $trait = $this->pathOf("{$config->namespace}\\Resource\\Handwritten\\{$resource->config->traitName()}");

            if ($resource->config->handwritten && !is_file($trait)) {
                throw new RuntimeException("{$resource->config->class} mixes in hand-written methods, but {$trait} does not exist.");
            }
        }

        $files = $this->render($analysis);
        $written = $this->write($files);
        $deleted = $this->deleteStale($files);

        $models = \count(array_filter($registry->models, static fn($model): bool => $model->request || $model->response));
        $methods = array_sum(array_map(static fn(ResourceDefinition $resource): int => \count($resource->methods), $resources));
        $handwritten = array_sum(array_map(static fn(ResourceDefinition $resource): int => \count($resource->handwritten), $resources));
        $report = \sprintf(
            "[%s] %d enums, %d models, %d unions, %d resources (%d generated methods, %d hand-written), %d files written, %d stale deleted\n",
            $config->name,
            \count($registry->enums),
            $models,
            \count($registry->unions),
            \count($resources),
            $methods,
            $handwritten,
            $written,
            $deleted,
        );

        foreach (array_unique([...$registry->enumBuilder->notes, ...$analysis->notes]) as $note) {
            $report .= "  note: {$note}\n";
        }

        return $report;
    }

    /**
     * The generated files: path => content.
     *
     * @return array<string, string>
     */
    public function render(Analysis $analysis): array
    {
        $config = $analysis->config;
        $registry = $analysis->registry;
        $source = "resources/specs/{$config->spec}.json";

        $files = [];
        $modelWriter = new ModelWriter($registry);
        $enumWriter = new EnumWriter();
        $unionWriter = new UnionWriter();
        $resourceWriter = new ResourceWriter($config, $registry);

        foreach ($registry->enums as $enum) {
            $files[$this->pathOf($enum->class)] = $enumWriter->render($enum, $source);
        }

        foreach ($registry->models as $model) {
            if ($model->request || $model->response) {
                $files[$this->pathOf($model->class)] = $modelWriter->render($model, $source);
            }
        }

        foreach ($registry->unions as $union) {
            if (!$union->request && !$union->response) {
                continue;
            }

            $files[$this->pathOf($union->interface)] = $unionWriter->renderInterface($union, $source);

            if ($union->fallback !== null) {
                $files[$this->pathOf($union->fallback)] = $unionWriter->renderFallback($union, $source);
            }
        }

        foreach ($analysis->resources as $resource) {
            $files[$this->pathOf($resource->class)] = $resourceWriter->render($resource, $source);
        }

        $files[$this->pathOf($config->fqcn('', $config->client))] = new ClientWriter($config)->render($analysis->resources, $source);

        return $files;
    }

    /**
     * Schema names in the configuration must exist, or a typo would silently
     * change nothing. Locations start with a schema or an operation id.
     */
    private static function checkNames(Spec $spec, ApiConfig $config): void
    {
        $names = array_map(static fn(string $name): string => (string) preg_replace('/[.<\[{].*$/s', '', $name), [
            ...array_keys($config->schemas),
            ...array_keys($config->properties),
            ...$config->extraModels,
            ...array_keys($config->unions),
        ]);
        $unknown = array_unique(array_filter($names, static fn(string $name): bool => !$spec->hasSchema($name) && !$spec->hasOperation($name)));

        if ($unknown !== []) {
            throw new RuntimeException('Unknown schemas or operations in the configuration: ' . implode(', ', $unknown) . '.');
        }
    }

    /**
     * Every operation must be implemented exactly once or ignored with a reason.
     *
     * @param list<ResourceDefinition> $resources
     *
     * @return list<string>
     */
    private static function coverageProblems(Spec $spec, ApiConfig $config, array $resources): array
    {
        $generated = [];
        $problems = [];

        foreach ($resources as $resource) {
            foreach ($resource->methods as $method) {
                $id = $method->operation->id;
                $where = "{$resource->config->class}::{$method->config->name}";

                if (isset($generated[$id])) {
                    $problems[] = "implemented twice: {$id} ({$generated[$id]} and {$where})";
                }

                $generated[$id] = $where;
            }
        }

        // Several hand-written methods may share one operation; a generated
        // method must be alone.
        $implemented = $generated;

        foreach ($resources as $resource) {
            foreach ($resource->handwritten as $method) {
                $id = $method->operation->id;

                if (isset($generated[$id])) {
                    $problems[] = "both generated ({$generated[$id]}) and hand-written: {$id}";
                }

                $implemented[$id] = "{$resource->config->class}::{$method->config->name}";
            }
        }

        foreach (array_keys($spec->operations()) as $id) {
            $ignored = isset($config->ignored[$id]);

            if (!isset($implemented[$id]) && !$ignored) {
                $problems[] = "not implemented: {$id}";
            }

            if (isset($implemented[$id]) && $ignored) {
                $problems[] = "implemented but also ignored: {$id}";
            }
        }

        foreach ($config->ignored as $id => $reason) {
            if (!$spec->hasOperation($id)) {
                $problems[] = "ignored operation does not exist: {$id}";
            }

            if (trim($reason) === '') {
                $problems[] = "ignored without a reason: {$id}";
            }
        }

        return $problems === [] ? [] : ["Coverage check failed:\n    " . implode("\n    ", $problems)];
    }

    private function pathOf(string $class): string
    {
        $prefix = "{$this->config->namespace}\\";

        if (!str_starts_with($class, $prefix)) {
            throw new RuntimeException("{$class} is outside the namespace {$this->config->namespace}.");
        }

        return "{$this->directory()}/" . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';
    }

    private function directory(): string
    {
        return "{$this->root}/{$this->config->directory}";
    }

    /**
     * Write the files whose content changed. A file that exists but was not
     * generated is never overwritten: the runtime shares the directories with
     * the generated code, e.g. src/Model/Cast.php next to the models.
     *
     * @param array<string, string> $files
     */
    private function write(array $files): int
    {
        foreach (array_keys($files) as $path) {
            if (is_file($path) && !CodeFile::isGenerated((string) file_get_contents($path))) {
                throw new RuntimeException("Refusing to overwrite the hand-written {$path}; rename the generated class in the configuration.");
            }
        }

        $written = 0;

        foreach ($files as $path => $content) {
            $directory = \dirname($path);

            if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
                throw new RuntimeException("Unable to create {$directory}.");
            }

            if (!is_file($path) || file_get_contents($path) !== $content) {
                file_put_contents($path, $content);
                ++$written;
            }
        }

        return $written;
    }

    /**
     * Delete generated files that are no longer produced. Only files that
     * start with the generated header are considered, so hand-written code in
     * the same directories is never touched.
     *
     * @param array<string, string> $files
     */
    private function deleteStale(array $files): int
    {
        $directory = $this->directory();

        if (!is_dir($directory)) {
            return 0;
        }

        $deleted = 0;
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo) {
                continue;
            }

            $path = $file->getPathname();

            if ($file->getExtension() !== 'php' || isset($files[$path])) {
                continue;
            }

            if (CodeFile::isGenerated((string) file_get_contents($path))) {
                unlink($path);
                ++$deleted;
            }
        }

        return $deleted;
    }
}
