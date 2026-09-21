<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools;

use DateTimeInterface;
use RuntimeException;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Converts the YAML specification UptimeRobot publishes into the JSON
 * snapshot the generator reads.
 *
 * The conversion must not change what the YAML says:
 *
 * - Mappings are parsed into objects, so an empty mapping stays `{}` and is
 *   not confused with an empty sequence `[]` (the specification uses `{}` for
 *   schemas whose type was erased).
 * - An unquoted timestamp is a date in YAML. Symfony's parser would turn it
 *   into a Unix timestamp (an int) and JSON has no date type, so its original
 *   spelling cannot be kept; the conversion fails instead of guessing.
 * - An unquoted integer beyond PHP's int range would silently become a
 *   string, and `.inf`/`.nan` have no JSON representation; both fail too.
 */
final class SpecSnapshot
{
    public const int JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR;

    private const int YAML_FLAGS = Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_DATETIME | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;

    /**
     * An unquoted plain scalar of 19 or more digits, the length at which an
     * integer may exceed PHP_INT_MAX.
     */
    private const string LONG_INTEGER = '/^[^#\'"\n]*?(?::|-)\s+[-+]?\d{19,}\s*(?:#.*)?$/m';

    /**
     * Convert a YAML document into pretty-printed JSON, ending with a newline.
     */
    public static function fromYaml(string $yaml): string
    {
        if (preg_match(self::LONG_INTEGER, $yaml, $match) === 1) {
            throw new RuntimeException('The YAML contains an integer beyond the int range, which would be read as a string: ' . trim($match[0]));
        }

        try {
            $document = Yaml::parse($yaml, self::YAML_FLAGS);
        } catch (ParseException $e) {
            throw new RuntimeException("The specification is not valid YAML: {$e->getMessage()}", 0, $e);
        }

        if (!$document instanceof stdClass) {
            throw new RuntimeException('The specification is not a YAML mapping.');
        }

        self::check($document, '');

        return json_encode($document, self::JSON_FLAGS) . "\n";
    }

    private static function check(mixed $node, string $path): void
    {
        $location = $path === '' ? 'the document root' : $path;

        if ($node instanceof DateTimeInterface) {
            throw new RuntimeException("Unquoted timestamp at {$location}: YAML reads it as a date, whose spelling JSON cannot keep. Handle it in tools/fetch-specs.php.");
        }

        if (\is_float($node) && !is_finite($node)) {
            throw new RuntimeException("Non-finite number at {$location}, which JSON cannot represent.");
        }

        if ($node instanceof stdClass) {
            foreach (get_object_vars($node) as $key => $value) {
                self::check($value, "{$path}/{$key}");
            }

            return;
        }

        if (\is_array($node)) {
            foreach ($node as $index => $value) {
                self::check($value, "{$path}/{$index}");
            }

            return;
        }

        if (\is_object($node)) {
            throw new RuntimeException('Unexpected ' . $node::class . " at {$location}.");
        }
    }
}
