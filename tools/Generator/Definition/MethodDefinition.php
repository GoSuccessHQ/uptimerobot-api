<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

use GoSuccess\UptimeRobot\Tools\Generator\Config\MethodConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Config\PaginationConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Operation;
use GoSuccess\UptimeRobot\Tools\Generator\PhpType;

/**
 * A resource method to generate.
 */
final class MethodDefinition
{
    /**
     * Parameters in signature order.
     *
     * @var list<ParameterDefinition>
     */
    public array $parameters = [];

    /** The return type; null means void. For paginated methods: the item type. */
    public ?PhpType $returns = null;

    /** Whether a successful response may come without a body. */
    public bool $nullable = false;

    /** Envelope property holding the payload. */
    public ?string $unwrap = null;

    /**
     * Whether the request carries an empty JSON object: the operation accepts
     * a bare `{"type": "object"}` body, which the API requires to be sent as
     * `{}` with `Content-Type: application/json`.
     */
    public bool $emptyBody = false;

    public ?PaginationConfig $pagination = null;

    public function __construct(
        public readonly MethodConfig $config,
        public readonly Operation $operation,
    ) {}

    /**
     * @return list<ParameterDefinition>
     */
    public function parametersIn(string $location): array
    {
        return array_values(array_filter($this->parameters, static fn(ParameterDefinition $parameter): bool => $parameter->location === $location));
    }

    public function payload(): ?ParameterDefinition
    {
        return $this->parametersIn(ParameterDefinition::PAYLOAD)[0] ?? null;
    }

    /**
     * The cursor parameter of a paginated method.
     */
    public function cursor(): ?ParameterDefinition
    {
        foreach ($this->parametersIn(ParameterDefinition::QUERY) as $parameter) {
            if ($this->pagination !== null && $parameter->specName === $this->pagination->cursor) {
                return $parameter;
            }
        }

        return null;
    }
}
