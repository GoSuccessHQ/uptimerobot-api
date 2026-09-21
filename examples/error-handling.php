<?php

declare(strict_types=1);

/**
 * Typed exceptions for API errors, with the error codes and messages the API
 * reports, and the arguments the client rejects before sending anything.
 * Read-only: the requests ask for things that do not exist or are invalid.
 *
 *   UPTIMEROBOT_API_KEY=your-api-key php examples/error-handling.php
 */

use GoSuccess\UptimeRobot\Exception\ApiException;
use GoSuccess\UptimeRobot\Exception\AuthenticationException;
use GoSuccess\UptimeRobot\Exception\BadRequestException;
use GoSuccess\UptimeRobot\Exception\ForbiddenException;
use GoSuccess\UptimeRobot\Exception\NotFoundException;
use GoSuccess\UptimeRobot\Exception\UptimeRobotException;
use GoSuccess\UptimeRobot\UptimeRobot;

/** @var UptimeRobot $uptimeRobot */
$uptimeRobot = require __DIR__ . '/bootstrap.php';

// A monitor that does not exist: HTTP 404 with the code 000-004.
try {
    $uptimeRobot->monitors->get(999999999);
} catch (NotFoundException $e) {
    printf("Not found: %s\n", $e->getMessage());
    printf("  status %d, error code %s\n", $e->statusCode, $e->errorCode ?? '-');
} catch (ApiException $e) {
    // Any other HTTP error status.
    printf("HTTP %d: %s\n", $e->statusCode, $e->getMessage());
} catch (UptimeRobotException $e) {
    // Network failures, unexpected responses, ...
    printf("%s: %s\n", $e::class, $e->getMessage());
}

// A request the validator rejects: HTTP 400 without a code. The validator
// reports a list of messages, which the exception message joins with "; ".
try {
    $uptimeRobot->incidents->list(monitorId: 0);
} catch (BadRequestException $e) {
    printf("\nBad request: %s\n", $e->getMessage());
    printf("  raw body: %s\n", $e->responseBody);
}

// A feature the plan lacks: HTTP 403 with the code 000-003, before the API
// looks at the incident. With the feature, the unknown incident is a 404.
try {
    $uptimeRobot->incidentComments->list('999999999');
} catch (ForbiddenException $e) {
    printf("\nForbidden: %s\n", $e->getMessage());
    echo $e->errorCode === '000-003' ? "  the plan lacks incident comments\n" : "  code {$e->errorCode}\n";
} catch (NotFoundException $e) {
    printf("\nNot found: %s\n", $e->getMessage());
}

// An invalid API key: HTTP 401 with the code 003-005.
try {
    new UptimeRobot('not-a-valid-key')->user->me();
} catch (AuthenticationException $e) {
    printf("\nAuthentication failed: %s\n", $e->getMessage());
}

// Arguments the API would reject or silently ignore raise PHP's
// InvalidArgumentException before any request is sent: here a bulk operation
// that selects no monitors.
try {
    $uptimeRobot->bulkMonitors->pause();
} catch (InvalidArgumentException $e) {
    printf("\nNot sent: %s\n", $e->getMessage());
}
