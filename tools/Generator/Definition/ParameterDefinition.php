<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

use GoSuccess\UptimeRobot\Tools\Generator\PhpType;

/**
 * One parameter of a resource method.
 */
final readonly class ParameterDefinition
{
    public const string PATH = 'path';
    public const string QUERY = 'query';
    /** A property of a flattened request body. */
    public const string BODY = 'body';
    /** The request body model itself. */
    public const string PAYLOAD = 'payload';
    /** A body property that repeats a path parameter and is filled from it. */
    public const string BOUND = 'bound';

    /**
     * @param string      $specName       Name in the specification (query key, path placeholder or body key).
     * @param string|null $default        PHP code of the default value; null for required parameters.
     * @param bool        $commaSeparated Whether a list query parameter is sent as one comma-separated value.
     */
    public function __construct(
        public string $specName,
        public string $phpName,
        public PhpType $type,
        public string $location,
        public bool $nullable,
        public ?string $default,
        public ?string $description,
        public bool $commaSeparated = false,
    ) {}

    public function isRequired(): bool
    {
        return $this->default === null;
    }
}
