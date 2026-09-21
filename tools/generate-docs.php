<?php

declare(strict_types=1);

/**
 * Generate the reference pages under docs/ from the client and resource classes.
 *
 * Usage:
 *   php tools/generate-docs.php
 *
 * Run it after tools/generate.php. Pages that are no longer produced are
 * deleted; docs/ contains generated pages only.
 */

use GoSuccess\UptimeRobot\Tools\Generator\Docs\DocsGenerator;
use GoSuccess\UptimeRobot\Tools\Generator\Docs\DocTarget;
use GoSuccess\UptimeRobot\Tools\Generator\Generator;

require __DIR__ . '/../vendor/autoload.php';

$analysis = Generator::fromFile(__DIR__ . '/config/uptimerobot.php')->analyze();
$config = $analysis->config;
$targets = [];

foreach ($analysis->resources as $resource) {
    $methods = [];

    foreach ($resource->config->methods as $name => $method) {
        $methods[] = $name;

        if ($method->all !== null) {
            $methods[] = $method->all;
        }
    }

    $targets[] = new DocTarget($resource->config->property, $resource->class, $resource->config->description, $methods);
}

// Examples pass a parameter typed with a union's interface as its first variant.
$implementations = [];

foreach ($analysis->registry->unions as $union) {
    $implementations[$union->interface] = array_values($union->variants)[0];
}

$client = $config->fqcn('', $config->client);
$setup = "\$uptimeRobot = new {$config->client}('your-api-key');";

$docs = dirname(__DIR__) . '/docs';
$files = new DocsGenerator($config->title, $client, '$uptimeRobot', $setup, $targets, $implementations)->render();
$written = 0;

foreach ($files as $path => $content) {
    $target = "{$docs}/{$path}";

    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0o775, true);
    }

    if (!is_file($target) || file_get_contents($target) !== $content) {
        file_put_contents($target, $content);
        ++$written;
    }
}

$deleted = 0;

if (is_dir($docs)) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docs, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'md' && !isset($files[substr($file->getPathname(), strlen($docs) + 1)])) {
            unlink($file->getPathname());
            ++$deleted;
        }
    }
}

echo count($files) . " pages, {$written} written, {$deleted} deleted\n";
