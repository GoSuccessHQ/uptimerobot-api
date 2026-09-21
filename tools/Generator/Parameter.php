<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

/**
 * A path, query or header parameter of an operation.
 */
final class Parameter
{
    public readonly string $name;

    public readonly string $in;

    public readonly bool $required;

    public readonly Schema $schema;

    /**
     * @param array<array-key, mixed> $node
     */
    public function __construct(Spec $spec, public readonly array $node)
    {
        $this->name = \is_string($node['name'] ?? null) ? $node['name'] : '';
        $this->in = \is_string($node['in'] ?? null) ? $node['in'] : 'query';
        $this->required = ($node['required'] ?? false) === true || $this->in === 'path';
        $this->schema = new Schema($spec, \is_array($node['schema'] ?? null) ? $node['schema'] : []);
    }

    /**
     * Whether a list is sent as one comma-separated value (`style: form`,
     * `explode: false`) rather than as repeated keys, OpenAPI's default.
     */
    public function isCommaSeparated(): bool
    {
        $style = $this->node['style'] ?? 'form';

        return $style === 'form' && ($this->node['explode'] ?? true) === false;
    }

    public function description(): ?string
    {
        $description = $this->node['description'] ?? null;

        return \is_string($description) && trim($description) !== '' ? $description : $this->schema->description();
    }
}
