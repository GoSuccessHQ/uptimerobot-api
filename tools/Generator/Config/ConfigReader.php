<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Config;

use RuntimeException;

/**
 * Strict reader for configuration arrays: wrong types and unknown keys fail
 * loudly instead of being ignored.
 */
final class ConfigReader
{
    /** @var array<string, true> */
    private array $read = [];

    /**
     * @param array<array-key, mixed> $data
     */
    public function __construct(private readonly array $data, private readonly string $context) {}

    public function string(string $key, ?string $default = null): string
    {
        $this->read[$key] = true;
        $value = $this->data[$key] ?? $default;

        if (!\is_string($value)) {
            throw new RuntimeException("{$this->context}: \"{$key}\" must be a string.");
        }

        return $value;
    }

    public function optionalString(string $key): ?string
    {
        $this->read[$key] = true;
        $value = $this->data[$key] ?? null;

        if ($value !== null && !\is_string($value)) {
            throw new RuntimeException("{$this->context}: \"{$key}\" must be a string.");
        }

        return $value;
    }

    public function optionalInt(string $key): ?int
    {
        $this->read[$key] = true;
        $value = $this->data[$key] ?? null;

        if ($value !== null && !\is_int($value)) {
            throw new RuntimeException("{$this->context}: \"{$key}\" must be an integer.");
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $this->read[$key] = true;
        $value = $this->data[$key] ?? $default;

        if (!\is_bool($value)) {
            throw new RuntimeException("{$this->context}: \"{$key}\" must be a boolean.");
        }

        return $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function map(string $key): array
    {
        $this->read[$key] = true;
        $value = $this->data[$key] ?? [];

        if (!\is_array($value)) {
            throw new RuntimeException("{$this->context}: \"{$key}\" must be an array.");
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    public function stringList(string $key): array
    {
        $list = [];

        foreach ($this->map($key) as $item) {
            if (!\is_string($item)) {
                throw new RuntimeException("{$this->context}: \"{$key}\" must only contain strings.");
            }

            $list[] = $item;
        }

        return $list;
    }

    /**
     * @return array<string, string>
     */
    public function stringMapAt(string $key): array
    {
        return new self($this->map($key), "{$this->context}.{$key}")->stringMap();
    }

    /**
     * @return array<string, string|false>
     */
    public function schemaMap(string $key): array
    {
        $map = [];

        foreach ($this->map($key) as $name => $value) {
            if (!\is_string($value) && $value !== false) {
                throw new RuntimeException("{$this->context}: \"{$key}.{$name}\" must be a class name or false.");
            }

            $map[(string) $name] = $value;
        }

        return $map;
    }

    /**
     * The whole array as a string => string map.
     *
     * @return array<string, string>
     */
    public function stringMap(): array
    {
        $map = [];

        foreach ($this->data as $key => $value) {
            if (!\is_string($value)) {
                throw new RuntimeException("{$this->context}: \"{$key}\" must be a string.");
            }

            $map[(string) $key] = $value;
        }

        return $map;
    }

    public function nested(mixed $value, string $context): self
    {
        if (!\is_array($value)) {
            throw new RuntimeException("{$this->context}: \"{$context}\" must be an array.");
        }

        return new self($value, "{$this->context} {$context}");
    }

    public function assertNoUnknownKeys(): void
    {
        $unknown = array_diff(array_map(strval(...), array_keys($this->data)), array_keys($this->read));

        if ($unknown !== []) {
            throw new RuntimeException("{$this->context}: unknown key(s) " . implode(', ', $unknown) . '.');
        }
    }
}
