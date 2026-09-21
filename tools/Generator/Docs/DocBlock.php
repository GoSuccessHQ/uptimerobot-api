<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Docs;

/**
 * The parts of a method docblock the reference pages need.
 */
final readonly class DocBlock
{
    /**
     * A PHPDoc type, which may contain spaces inside its brackets, e.g.
     * `array<array-key, string>|null` or `array{id: int}`.
     */
    private const string TYPE = '(?<type>(?:[^\s<{(]|(?<angle><(?:[^<>]|(?&angle))*>)|(?<brace>\{(?:[^{}]|(?&brace))*\})|(?<paren>\((?:[^()]|(?&paren))*\)))+)';

    /**
     * @param list<string>                                           $paragraphs
     * @param array<string, array{type: string, description: string}> $params
     */
    public function __construct(
        public string $summary,
        public array $paragraphs,
        public ?string $endpoint,
        public array $params,
        public ?string $return,
        public bool $deprecated,
    ) {}

    public static function parse(string $comment): self
    {
        $lines = [];

        foreach (explode("\n", $comment) as $line) {
            // Strip the comment markers: a leading "/**" or "*", a trailing "*/".
            $line = (string) preg_replace(['~^\s*(/\*\*|\*/|\*)\s?~', '~\s*\*/$~'], '', rtrim($line));
            $lines[] = rtrim($line);
        }

        $text = [];
        $tags = [];
        $current = null;

        foreach ($lines as $line) {
            if (str_starts_with(ltrim($line), '@')) {
                $tags[] = ltrim($line);
                $current = \count($tags) - 1;

                continue;
            }

            if ($current !== null && $line !== '') {
                $tags[$current] .= ' ' . trim($line);

                continue;
            }

            $current = null;
            $text[] = $line;
        }

        $paragraphs = array_values(array_filter(
            array_map(static fn(string $paragraph): string => self::inline(trim((string) preg_replace('/\s*\n\s*/', ' ', $paragraph))), explode("\n\n", implode("\n", $text))),
            static fn(string $paragraph): bool => $paragraph !== '',
        ));

        $endpoint = null;

        foreach ($paragraphs as $index => $paragraph) {
            if (preg_match('~^`((?:GET|POST|PUT|PATCH|DELETE) [^`]+)`$~', $paragraph, $match)) {
                $endpoint = $match[1];
                unset($paragraphs[$index]);
            }
        }

        $paragraphs = array_values($paragraphs);
        $summary = array_shift($paragraphs) ?? '';
        $params = [];
        $return = null;
        $deprecated = false;

        foreach ($tags as $tag) {
            if (preg_match('/^@param\s+' . self::TYPE . '\s+\$(?<name>\w+)\s*(?<description>.*)$/s', $tag, $match)) {
                $params[$match['name']] = ['type' => $match['type'], 'description' => self::inline(trim($match['description']))];
            } elseif (preg_match('/^@return\s+' . self::TYPE . '/', $tag, $match)) {
                $return = $match['type'];
            } elseif (str_starts_with($tag, '@deprecated')) {
                $deprecated = true;
            }
        }

        return new self($summary, $paragraphs, $endpoint, $params, $return, $deprecated);
    }

    /**
     * Turn inline tags such as `{@see \Foo\Bar}` into code spans with the short
     * name, since Markdown has no use for them.
     */
    private static function inline(string $text): string
    {
        return preg_replace_callback(
            '/\{@(?:see|link)\s+([^\s}]+)[^}]*\}/',
            static function (array $match): string {
                $separator = strrpos($match[1], '\\');
                $name = $separator === false ? $match[1] : substr($match[1], $separator + 1);

                return "`{$name}`";
            },
            $text,
        ) ?? $text;
    }
}
