<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Definition;

/**
 * A `oneOf` of models told apart by a discriminator property: an interface,
 * the variants implementing it and, for responses, a fallback model.
 */
final class UnionDefinition
{
    /** Whether the union is sent in a request payload. */
    public bool $request = false;

    /** Whether the union is read from a response. */
    public bool $response = false;

    /**
     * @param string                    $interface     Fully qualified name of the interface.
     * @param string                    $source        Location of the `oneOf`.
     * @param string                    $discriminator JSON name of the discriminator property.
     * @param 'int'|'string'            $backing       Type of the discriminator values.
     * @param array<int|string, string> $variants      Discriminator value => fully qualified model class.
     * @param string|null               $envelope      Property the variants' own properties are nested in.
     * @param string|null               $fallback      Fully qualified class of the fallback model.
     */
    public function __construct(
        public readonly string $interface,
        public readonly string $source,
        public readonly string $discriminator,
        public readonly string $backing,
        public readonly array $variants,
        public readonly ?string $envelope,
        public readonly ?string $fallback,
        public readonly ?string $description,
    ) {}

    /**
     * A comparable fingerprint, to check that two locations sharing an
     * interface describe the same union.
     */
    public function signature(): string
    {
        $variants = array_map(static fn(int|string $value, string $class): string => "{$value}={$class}", array_keys($this->variants), $this->variants);

        return "{$this->discriminator}|{$this->envelope}|{$this->fallback}|" . implode(',', $variants);
    }
}
