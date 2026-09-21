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
 * - An unquoted timestamp is a date in YAML. Symfony's parser turns it into a
 *   date object, or into a Unix timestamp where it is a mapping key, and JSON
 *   has no date type, so its original spelling cannot be kept; the conversion
 *   fails instead of guessing. Other mapping keys the parser evaluates
 *   (`0x10: …` becomes "16") fail as well.
 * - An unquoted integer beyond PHP's int range would silently become a
 *   string (a hexadecimal or octal one an imprecise float), and `.inf`/`.nan`
 *   have no JSON representation; all of these fail too.
 *
 * The parser does not report how a scalar was spelled, so these checks
 * compare the parsed document with the YAML source.
 */
final class SpecSnapshot
{
    public const int JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR;

    private const int YAML_FLAGS = Yaml::PARSE_OBJECT_FOR_MAP | Yaml::PARSE_DATETIME | Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE;

    /**
     * A string or key spelled like a decimal integer.
     */
    private const string INTEGER = '/^-?\d+$/';

    /**
     * 2^63: every float at least this large, positive or negative, lies
     * beyond the int range.
     */
    private const float INT_RANGE = 9.223372036854775808E18;

    /**
     * Convert a YAML document into pretty-printed JSON, ending with a newline.
     */
    public static function fromYaml(string $yaml): string
    {
        try {
            $document = Yaml::parse($yaml, self::YAML_FLAGS);
        } catch (ParseException $e) {
            throw new RuntimeException("The specification is not valid YAML: {$e->getMessage()}", 0, $e);
        }

        if (!$document instanceof stdClass) {
            throw new RuntimeException('The specification is not a YAML mapping.');
        }

        $integerStrings = [];
        $largeFloats = [];
        $integerKeys = [];
        self::check($document, '', $integerStrings, $largeFloats, $integerKeys);

        self::checkIntegerStrings($yaml, $integerStrings);
        self::checkRadixIntegers($yaml, $largeFloats);
        self::checkKeys($yaml, $integerKeys);

        return json_encode($document, self::JSON_FLAGS) . "\n";
    }

    /**
     * Reject what JSON cannot represent and collect what checkIntegerStrings(),
     * checkRadixIntegers() and checkKeys() compare with the source.
     *
     * @param array<array-key, list<string>> $integerStrings String values spelled like integers => their locations.
     * @param list<array{float, string}>     $largeFloats    Floats beyond the int range and their locations.
     * @param array<array-key, string>       $integerKeys    Mapping keys spelled like integers => a location.
     */
    private static function check(mixed $node, string $path, array &$integerStrings, array &$largeFloats, array &$integerKeys): void
    {
        $location = $path === '' ? 'the document root' : $path;

        if ($node instanceof DateTimeInterface) {
            throw new RuntimeException("Unquoted timestamp at {$location}: YAML reads it as a date, whose spelling JSON cannot keep. Handle it in tools/fetch-specs.php.");
        }

        if (\is_float($node) && !is_finite($node)) {
            throw new RuntimeException("Non-finite number at {$location}, which JSON cannot represent.");
        }

        if (\is_float($node) && abs($node) >= self::INT_RANGE) {
            $largeFloats[] = [$node, $location];
        }

        if (\is_string($node) && preg_match(self::INTEGER, $node) === 1) {
            $integerStrings[$node][] = $location;
        }

        if ($node instanceof stdClass) {
            foreach (get_object_vars($node) as $key => $value) {
                $key = (string) $key;

                if (preg_match(self::INTEGER, $key) === 1) {
                    $integerKeys[$key] ??= "{$path}/{$key}";
                }

                self::check($value, "{$path}/{$key}", $integerStrings, $largeFloats, $integerKeys);
            }

            return;
        }

        if (\is_array($node)) {
            foreach ($node as $index => $value) {
                self::check($value, "{$path}/{$index}", $integerStrings, $largeFloats, $integerKeys);
            }

            return;
        }

        if (\is_object($node)) {
            throw new RuntimeException('Unexpected ' . $node::class . " at {$location}.");
        }
    }

    /**
     * The parser reads an unquoted integer beyond the int range, or one with
     * leading zeros, as a string (without its "+" and "_"), so the JSON would
     * have a string where the YAML has a number. A string spelled like an
     * integer is therefore accepted only as often as the YAML quotes it or
     * tags it as a string.
     *
     * @param array<array-key, list<string>> $integerStrings
     */
    private static function checkIntegerStrings(string $yaml, array $integerStrings): void
    {
        foreach ($integerStrings as $value => $locations) {
            $digits = preg_quote((string) $value, '/');
            $quoted = preg_match_all("/'{$digits}'|\"{$digits}\"|!!str\\s+{$digits}(?![\\d_])/", $yaml);

            if (\count($locations) > $quoted) {
                $where = \count($locations) === 1 ? $locations[0] : 'one of ' . implode(', ', $locations);

                throw new RuntimeException("Unquoted integer {$value} at {$where}: it is beyond the int range or has leading zeros, so it would be read as a string. Handle it in tools/fetch-specs.php.");
            }
        }
    }

    /**
     * The parser reads an unquoted hexadecimal or octal integer beyond the int
     * range as a float, which loses digits.
     *
     * @param list<array{float, string}> $largeFloats
     */
    private static function checkRadixIntegers(string $yaml, array $largeFloats): void
    {
        if ($largeFloats === [] || preg_match_all('/(?<![\w.])(?:0x[0-9a-f][0-9a-f_]*|0o[0-7][0-7_]*)(?![\w.])/i', $yaml, $matches) === 0) {
            return;
        }

        foreach (array_unique($matches[0]) as $literal) {
            // How the parser reads the literal on its own.
            $value = Yaml::parse($literal);

            foreach ($largeFloats as [$float, $location]) {
                if ($value === $float) {
                    throw new RuntimeException("Unquoted integer {$literal} at {$location}: it is beyond the int range, so it would be read as an imprecise float. Handle it in tools/fetch-specs.php.");
                }
            }
        }
    }

    /**
     * The parser evaluates the keys of block mappings: an unquoted date becomes
     * a Unix timestamp, "0x10" becomes 16 and "1_000" becomes 1000. A key
     * spelled like an integer must therefore occur like that in the YAML.
     *
     * @param array<array-key, string> $integerKeys
     */
    private static function checkKeys(string $yaml, array $integerKeys): void
    {
        foreach ($integerKeys as $key => $location) {
            $digits = preg_quote((string) $key, '/');

            if (preg_match("/(?:^|[\\s{,])(['\"]?){$digits}\\1[ \\t]*:(?:\\s|$)/m", $yaml) !== 1) {
                throw new RuntimeException("Evaluated mapping key at {$location}: the YAML spells it differently, e.g. as an unquoted date, which the parser turns into a Unix timestamp. Handle it in tools/fetch-specs.php.");
            }
        }
    }
}
