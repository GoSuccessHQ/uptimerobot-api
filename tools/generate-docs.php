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
use GoSuccess\UptimeRobot\Tools\Generator\Generator;

require __DIR__ . '/../vendor/autoload.php';

try {
    $files = DocsGenerator::forAnalysis(Generator::fromFile(__DIR__ . '/config/uptimerobot.php')->analyze())->render();
} catch (Throwable $e) {
    fwrite(STDERR, "{$e->getMessage()}\n");

    exit(1);
}

$docs = dirname(__DIR__) . '/docs';
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
