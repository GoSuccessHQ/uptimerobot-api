<?php

declare(strict_types=1);

/**
 * Refresh the committed snapshot of the UptimeRobot API v3 specification.
 *
 * UptimeRobot publishes the specification as YAML only, at the URL below; the
 * API reference at https://uptimerobot.com/api/v3/ renders the same file. It is
 * stored as pretty-printed JSON under resources/specs/, so that generation
 * works offline and deterministically from a committed file, a refresh shows
 * up as a readable diff, and only this script needs a YAML parser
 * (symfony/yaml is a development dependency; the runtime has none).
 *
 * UptimeRobot changes the specification without raising `info.version`, so a
 * scheduled workflow (.github/workflows/spec-drift.yml) runs this script and
 * reports when the snapshot is out of date. Run `composer generate` after a
 * refresh.
 *
 * Usage:
 *   php tools/fetch-specs.php
 */

use GoSuccess\UptimeRobot\Tools\ResponseHeaders;
use GoSuccess\UptimeRobot\Tools\SpecSnapshot;

require __DIR__ . '/../vendor/autoload.php';

const SPEC_URL = 'https://cdn.uptimerobot.com/api/openapi.yaml';
const SNAPSHOT = __DIR__ . '/../resources/specs/uptimerobot.json';

$context = stream_context_create(['http' => [
    'timeout' => 30,
    'user_agent' => 'gosuccess/uptimerobot-api tools/fetch-specs.php',
    'ignore_errors' => true,
]]);

$yaml = file_get_contents(SPEC_URL, false, $context);
// Redirects are followed, so this is the status of the response that was returned.
$response = ResponseHeaders::parse(http_get_last_response_headers() ?? []);
$status = $response->status;

if ($yaml === false || $status !== 200) {
    fwrite(STDERR, 'Failed to download ' . SPEC_URL . ($status !== 0 ? " (HTTP {$status})" : '') . "\n");

    exit(1);
}

$lastModified = $response->lastModified ?? 'unknown';

try {
    $json = SpecSnapshot::fromYaml($yaml);
} catch (RuntimeException $e) {
    fwrite(STDERR, "{$e->getMessage()}\n");

    exit(1);
}

$directory = dirname(SNAPSHOT);

if (!is_dir($directory) && !mkdir($directory, 0o775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Cannot create {$directory}\n");

    exit(1);
}

$changed = !is_file(SNAPSHOT) || file_get_contents(SNAPSHOT) !== $json;
file_put_contents(SNAPSHOT, $json);

echo 'wrote resources/specs/uptimerobot.json (' . ($changed ? 'changed' : 'unchanged') . ", Last-Modified: {$lastModified})\n";
