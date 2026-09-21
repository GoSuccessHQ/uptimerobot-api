<?php

declare(strict_types=1);

/**
 * Shared setup of the examples: loads the autoloader and reads the API key
 * from the UPTIMEROBOT_API_KEY environment variable.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/<script>.php
 */

require __DIR__ . '/../vendor/autoload.php';

$apiKey = getenv('UPTIMEROBOT_API_KEY');

if (!is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "Set UPTIMEROBOT_API_KEY to run the examples.\n");

    exit(1);
}

return $apiKey;
