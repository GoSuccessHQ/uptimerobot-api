<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

/**
 * Configuration of one resource class.
 */
final readonly class ResourceConfig
{
    /**
     * @param string                      $property    Property name on the API client, e.g. "monitors".
     * @param string                      $class       Resource class name, e.g. "MonitorResource".
     * @param string                      $description One-line description for the docblocks.
     * @param bool                        $handwritten Whether hand-written methods are mixed in from
     *                                                 the trait Handwritten\<Name>Operations.
     * @param array<string, MethodConfig> $methods     Keyed by method name.
     */
    public function __construct(
        public string $property,
        public string $class,
        public string $description,
        public bool $handwritten,
        public array $methods,
    ) {}

    /**
     * Name of the trait holding the hand-written methods.
     */
    public function traitName(): string
    {
        return preg_replace('/Resource$/', '', $this->class) . 'Operations';
    }

    public static function fromArray(string $property, ConfigReader $reader): self
    {
        $methods = [];
        // Parameter names shared by all methods, e.g. "id" => "monitorId".
        $parameters = $reader->stringMapAt('parameters');

        foreach ($reader->map('methods') as $name => $method) {
            $methods[(string) $name] = MethodConfig::fromArray((string) $name, $reader->nested($method, "methods.{$name}"), $parameters);
        }

        $instance = new self(
            property: $property,
            class: $reader->string('class'),
            description: $reader->string('description'),
            handwritten: $reader->bool('handwritten'),
            methods: $methods,
        );

        $reader->assertNoUnknownKeys();

        return $instance;
    }
}
