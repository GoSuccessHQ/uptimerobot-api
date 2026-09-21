<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

/**
 * Typed read access to one OpenAPI schema node.
 */
final class Schema
{
    /**
     * @param array<array-key, mixed> $node
     * @param string|null             $name Component name, for schemas under components/schemas.
     */
    public function __construct(
        public readonly Spec $spec,
        public readonly array $node,
        public readonly ?string $name = null,
    ) {}

    /**
     * The component this node references directly, if it is a `$ref`.
     */
    public function refName(): ?string
    {
        $ref = $this->node['$ref'] ?? null;

        return \is_string($ref) ? $this->spec->refTarget($ref) : null;
    }

    /**
     * Follow `$ref`s and the single-element `oneOf`/`allOf` wrappers that
     * NestJS emits around described references, and return the schema they
     * point to. Nullability and description of the wrapper are kept.
     */
    public function resolve(): self
    {
        $current = $this;
        $nullable = false;
        $description = null;

        for ($depth = 0; $depth < 10; ++$depth) {
            $nullable = $nullable || $current->isNullable();
            $description ??= $current->description();

            $ref = $current->refName();

            if ($ref !== null) {
                $current = $current->spec->schema($ref);

                continue;
            }

            $wrapped = $current->singleWrapped();

            if ($wrapped === null) {
                break;
            }

            $current = $wrapped;
        }

        if ($current !== $this && ($nullable || $description !== null)) {
            return $current->with(['nullable' => $nullable || $current->isNullable(), 'description' => $current->description() ?? $description]);
        }

        return $current;
    }

    /**
     * The component name the node resolves to, following single wrappers.
     */
    public function resolvedName(): ?string
    {
        $current = $this;

        for ($depth = 0; $depth < 10; ++$depth) {
            $ref = $current->refName();

            if ($ref !== null) {
                return $ref;
            }

            $wrapped = $current->singleWrapped();

            if ($wrapped === null) {
                return $current->name;
            }

            $current = $wrapped;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $changes
     */
    public function with(array $changes): self
    {
        return new self($this->spec, [...$this->node, ...$changes], $this->name);
    }

    public function type(): ?string
    {
        $type = $this->node['type'] ?? null;

        return \is_string($type) ? $type : null;
    }

    public function format(): ?string
    {
        return $this->string('format');
    }

    public function isNullable(): bool
    {
        return ($this->node['nullable'] ?? false) === true;
    }

    public function isDeprecated(): bool
    {
        return ($this->node['deprecated'] ?? false) === true;
    }

    public function isReadOnly(): bool
    {
        return ($this->node['readOnly'] ?? false) === true;
    }

    public function description(): ?string
    {
        return $this->string('description');
    }

    public function title(): ?string
    {
        return $this->string('title');
    }

    public function string(string $key): ?string
    {
        $value = $this->node[$key] ?? null;

        return \is_string($value) && trim($value) !== '' ? $value : null;
    }

    /**
     * @return list<int|string>|null
     */
    public function enum(): ?array
    {
        $values = $this->node['enum'] ?? null;

        if (!\is_array($values)) {
            return null;
        }

        $result = [];

        foreach ($values as $value) {
            if (\is_int($value) || \is_string($value)) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * A list-of-strings vendor extension such as `x-enumNames`.
     *
     * @return list<string>|null
     */
    public function stringList(string $key): ?array
    {
        $values = $this->node[$key] ?? null;

        if (!\is_array($values)) {
            return null;
        }

        return array_values(array_filter($values, is_string(...)));
    }

    /**
     * Whether this is an enum: either a plain `enum` or a `oneOf` of
     * single-value schemas with a `title` each.
     */
    public function isEnum(): bool
    {
        if ($this->enum() !== null) {
            return true;
        }

        $variants = $this->variants('oneOf');

        if (\count($variants) < 2) {
            return false;
        }

        foreach ($variants as $variant) {
            if (\count($variant->enum() ?? []) !== 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * Properties, including those merged in through `allOf`.
     *
     * @return array<string, self>
     */
    public function properties(): array
    {
        $properties = [];

        foreach ($this->variants('allOf') as $part) {
            $properties = [...$properties, ...$part->resolve()->properties()];
        }

        $own = $this->node['properties'] ?? null;

        if (\is_array($own)) {
            foreach ($own as $name => $node) {
                if (\is_array($node)) {
                    $properties[(string) $name] = new self($this->spec, $node);
                }
            }
        }

        return $properties;
    }

    /**
     * @return list<string>
     */
    public function required(): array
    {
        $required = $this->stringList('required') ?? [];

        foreach ($this->variants('allOf') as $part) {
            $required = [...$required, ...$part->resolve()->required()];
        }

        return array_values(array_unique($required));
    }

    public function items(): ?self
    {
        $items = $this->node['items'] ?? null;

        return \is_array($items) ? new self($this->spec, $items) : null;
    }

    /**
     * The value schema of a map, or null if this is not a map. `true` and `{}`
     * both mean "any value".
     */
    public function additionalProperties(): ?self
    {
        $additional = $this->node['additionalProperties'] ?? null;

        return match (true) {
            $additional === true => new self($this->spec, []),
            \is_array($additional) => new self($this->spec, $additional),
            default => null,
        };
    }

    public function isObject(): bool
    {
        return $this->type() === 'object' || isset($this->node['properties']) || $this->variants('allOf') !== [];
    }

    /**
     * Whether this is `{"type": "object"}` and nothing else that describes
     * content: no properties, no values, no composition.
     */
    public function isBareObject(): bool
    {
        return $this->type() === 'object'
            && $this->properties() === []
            && !\array_key_exists('additionalProperties', $this->node)
            && $this->variants('oneOf') === []
            && $this->variants('anyOf') === [];
    }

    /**
     * Whether this is a file upload (`format: binary`).
     */
    public function isBinary(): bool
    {
        return $this->type() === 'string' && $this->format() === 'binary';
    }

    /**
     * The `discriminator` keyword of a `oneOf`: the property name and the
     * explicit mapping from value to reference, if any.
     *
     * @return array{property: string, mapping: array<string, string>}|null
     */
    public function discriminator(): ?array
    {
        $discriminator = $this->node['discriminator'] ?? null;

        if (!\is_array($discriminator) || !\is_string($discriminator['propertyName'] ?? null)) {
            return null;
        }

        $mapping = [];

        foreach (\is_array($discriminator['mapping'] ?? null) ? $discriminator['mapping'] : [] as $value => $ref) {
            if (\is_string($ref)) {
                $mapping[(string) $value] = $ref;
            }
        }

        return ['property' => $discriminator['propertyName'], 'mapping' => $mapping];
    }

    /**
     * @return list<self>
     */
    public function variants(string $keyword): array
    {
        $variants = $this->node[$keyword] ?? null;

        if (!\is_array($variants)) {
            return [];
        }

        $result = [];

        foreach ($variants as $variant) {
            if (\is_array($variant)) {
                $result[] = new self($this->spec, $variant);
            }
        }

        return $result;
    }

    private function singleWrapped(): ?self
    {
        foreach (['oneOf', 'allOf', 'anyOf'] as $keyword) {
            $variants = $this->variants($keyword);

            if (\count($variants) === 1 && !isset($this->node['properties'])) {
                return $variants[0];
            }
        }

        return null;
    }
}
