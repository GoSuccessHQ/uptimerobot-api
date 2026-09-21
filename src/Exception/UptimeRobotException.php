<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Exception;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by this library, so
 * callers can catch all of them with a single `catch (UptimeRobotException $e)`.
 */
interface UptimeRobotException extends Throwable {}
