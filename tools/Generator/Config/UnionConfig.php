<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

use RuntimeException;

/**
 * How to generate a `oneOf` of object schemas that a discriminator property
 * with a single value per variant tells apart.
 *
 * The generator emits an interface and one model per variant. The
 * discriminator is not a constructor parameter of the variants: each has it
 * as a constant and sends it with every payload. A union that is read from
 * responses also gets a fallback model for discriminator values this client
 * does not know yet, so a new variant never breaks a response.
 */
final readonly class UnionConfig
{
    /**
     * @param string                    $path          Location of the `oneOf`.
     * @param string                    $interface     Name of the interface the variants implement.
     * @param string                    $discriminator The property that tells the variants apart.
     * @param array<int|string, string> $variants      Discriminator value => model class name.
     * @param string|null               $envelope      For variants shaped `{"type", "<envelope>": {...}}`:
     *                                                 the property whose properties become the variant's own.
     * @param string|null               $fallback      Model for unknown discriminator values; required
     *                                                 when the union is read from responses.
     * @param string|null               $description   Docblock of the interface.
     */
    public function __construct(
        public string $path,
        public string $interface,
        public string $discriminator,
        public array $variants,
        public ?string $envelope,
        public ?string $fallback,
        public ?string $description,
    ) {}

    public static function fromArray(string $path, ConfigReader $reader): self
    {
        $variants = [];

        foreach ($reader->map('variants') as $value => $class) {
            if (!\is_string($class)) {
                throw new RuntimeException("unions.{$path}: variants must map discriminator values to class names.");
            }

            $variants[$value] = $class;
        }

        if ($variants === []) {
            throw new RuntimeException("unions.{$path}: name the class of every variant in \"variants\".");
        }

        $instance = new self(
            path: $path,
            interface: $reader->string('interface'),
            discriminator: $reader->string('discriminator'),
            variants: $variants,
            envelope: $reader->optionalString('envelope'),
            fallback: $reader->optionalString('fallback'),
            description: $reader->optionalString('description'),
        );

        $reader->assertNoUnknownKeys();

        return $instance;
    }
}
