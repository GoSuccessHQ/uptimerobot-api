<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

/**
 * A data model to generate.
 */
final class ModelDefinition
{
    /** @var list<PropertyDefinition> */
    public array $properties = [];

    /** Whether the model is sent in a request payload. */
    public bool $request = false;

    /** Whether the model is read from a response. */
    public bool $response = false;

    /** The union this model is a variant of, if any. */
    public ?UnionDefinition $union = null;

    /** The discriminator value of this variant; see $union. */
    public int|string|null $discriminatorValue = null;

    /**
     * For the variant of a union with an envelope: the variant's own schema;
     * $source is then the schema of its envelope property.
     */
    public ?string $variantSource = null;

    /**
     * @param string $class  Fully qualified class name.
     * @param string $source Schema name, or the location of an inline object, e.g. "UserDto.activeSubscription".
     */
    public function __construct(
        public readonly string $class,
        public readonly string $source,
        public readonly ?string $description,
        public readonly bool $deprecated,
    ) {}
}
