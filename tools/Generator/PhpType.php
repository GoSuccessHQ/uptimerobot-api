<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

use LogicException;

/**
 * The PHP type a schema maps to.
 */
final readonly class PhpType
{
    public const string STRING = 'string';
    public const string INT = 'int';
    public const string FLOAT = 'float';
    public const string BOOL = 'bool';
    public const string DATE = 'date';
    public const string ENUM = 'enum';
    public const string MODEL = 'model';
    /** One of several models, told apart by a discriminator property; the class is their interface. */
    public const string UNION = 'union';
    public const string LIST = 'list';
    public const string MAP = 'map';
    /** An untyped JSON object or array. */
    public const string OBJECT = 'object';
    public const string MIXED = 'mixed';

    /**
     * @param string|null $class   Fully qualified class name of an enum, a model or a union's interface.
     * @param string|null $backing Backing type of an enum: 'int' or 'string'.
     * @param PhpType|null $item   Element type of a list or map.
     */
    public function __construct(
        public string $kind,
        public ?string $class = null,
        public ?string $backing = null,
        public ?PhpType $item = null,
    ) {}

    public static function scalar(string $kind): self
    {
        return new self($kind);
    }

    public static function listOf(self $item): self
    {
        return new self(self::LIST, item: $item);
    }

    public static function mapOf(self $item): self
    {
        return new self(self::MAP, item: $item);
    }

    public function isScalar(): bool
    {
        return \in_array($this->kind, [self::STRING, self::INT, self::FLOAT, self::BOOL], true);
    }

    public function isCollection(): bool
    {
        return $this->kind === self::LIST || $this->kind === self::MAP;
    }

    /**
     * The native type declaration, e.g. `string`, `array`, `Monitor`.
     *
     * @param callable(string): string $alias Maps a fully qualified class name to its short name.
     */
    public function native(callable $alias, bool $forWriting = false): string
    {
        return match ($this->kind) {
            self::STRING, self::INT, self::FLOAT, self::BOOL => $this->kind,
            self::DATE => $alias($forWriting ? 'DateTimeInterface' : 'DateTimeImmutable'),
            self::ENUM, self::MODEL, self::UNION => $alias($this->classOrFail()),
            self::LIST, self::MAP, self::OBJECT => 'array',
            self::MIXED => 'mixed',
            default => throw new LogicException("Unknown kind {$this->kind}."),
        };
    }

    /**
     * The PHPDoc type, e.g. `list<Tag>` or `array<array-key, string>`.
     *
     * @param callable(string): string $alias
     */
    public function doc(callable $alias, bool $forWriting = false): string
    {
        return match ($this->kind) {
            self::LIST => 'list<' . $this->itemOrFail()->doc($alias, $forWriting) . '>',
            self::MAP => 'array<array-key, ' . $this->itemOrFail()->doc($alias, $forWriting) . '>',
            self::OBJECT => 'array<array-key, mixed>',
            default => $this->native($alias, $forWriting),
        };
    }

    /**
     * Whether the PHPDoc type carries more information than the native type.
     */
    public function needsDoc(): bool
    {
        return $this->isCollection() || $this->kind === self::OBJECT;
    }

    public function classOrFail(): string
    {
        return $this->class ?? throw new LogicException("Type {$this->kind} has no class.");
    }

    public function itemOrFail(): self
    {
        return $this->item ?? throw new LogicException("Type {$this->kind} has no item type.");
    }

    /**
     * Fully qualified classes referenced by this type (for imports).
     *
     * @return list<string>
     */
    public function classes(bool $forWriting = false): array
    {
        return match ($this->kind) {
            self::DATE => [$forWriting ? 'DateTimeInterface' : 'DateTimeImmutable'],
            self::ENUM, self::MODEL, self::UNION => [$this->classOrFail()],
            self::LIST, self::MAP => $this->itemOrFail()->classes($forWriting),
            default => [],
        };
    }
}
