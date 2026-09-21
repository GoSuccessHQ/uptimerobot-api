<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools;

use GoSuccess\UptimeRobot\Tools\SpecSnapshot;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SpecSnapshot::class)]
final class SpecSnapshotTest extends TestCase
{
    public function testKeepsEmptyMappingsAndEmptySequencesApart(): void
    {
        $json = SpecSnapshot::fromYaml("status: {}\nrequired: []\n");

        self::assertSame("{\n    \"status\": {},\n    \"required\": []\n}\n", $json);
    }

    public function testKeepsScalarsAsTheYamlTypesThem(): void
    {
        $yaml = <<<'YAML'
            version: '3.0'
            openapi: 3.0.0
            date: '2026-01-01'
            count: 42
            ratio: 1.0
            big: 9007199254740991
            flag: true
            none: null
            word: yes
            text: Größe – "quoted" /path
            YAML;

        $decoded = json_decode(SpecSnapshot::fromYaml($yaml), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame([
            'version' => '3.0',
            'openapi' => '3.0.0',
            'date' => '2026-01-01',
            'count' => 42,
            'ratio' => 1.0,
            'big' => 9007199254740991,
            'flag' => true,
            'none' => null,
            'word' => 'yes',
            'text' => 'Größe – "quoted" /path',
        ], $decoded);
    }

    public function testWritesReadableJson(): void
    {
        $json = SpecSnapshot::fromYaml("paths:\n  /monitors:\n    get:\n      summary: Größe\n      x: 1.0\n");

        self::assertSame(<<<'JSON'
            {
                "paths": {
                    "/monitors": {
                        "get": {
                            "summary": "Größe",
                            "x": 1.0
                        }
                    }
                }
            }

            JSON, $json);
    }

    public function testKeepsNumericKeysAsStrings(): void
    {
        $json = SpecSnapshot::fromYaml("responses:\n  '200':\n    description: OK\n  201:\n    description: Created\n");

        self::assertStringContainsString('"200": {', $json);
        self::assertStringContainsString('"201": {', $json);
    }

    public function testRejectsUnquotedTimestamps(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unquoted timestamp at /example');

        SpecSnapshot::fromYaml("example: 2026-01-01\n");
    }

    #[DataProvider('integersBeyondTheIntRange')]
    public function testRejectsIntegersBeyondTheIntRange(string $yaml, string $message): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        SpecSnapshot::fromYaml($yaml);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function integersBeyondTheIntRange(): iterable
    {
        $string = 'is beyond the int range or has leading zeros, so it would be read as a string';

        yield 'block' => ["maximum: 123456789012345678901\n", "Unquoted integer 123456789012345678901 at /maximum: it {$string}"];
        yield 'negative' => ["minimum: -12345678901234567890\n", 'Unquoted integer -12345678901234567890 at /minimum'];
        yield 'flow sequence' => ["enum: [1, 12345678901234567890]\n", 'Unquoted integer 12345678901234567890 at /enum/1'];
        yield 'flow mapping' => ["x: {max: 99999999999999999999}\n", 'Unquoted integer 99999999999999999999 at /x/max'];
        yield 'quoted once, unquoted once' => ["enum: ['12345678901234567890', 12345678901234567890]\n", 'Unquoted integer 12345678901234567890 at one of /enum/0, /enum/1'];
        yield 'underscores' => ["x: 1_000_000_000_000_000_000_0\n", 'Unquoted integer 10000000000000000000 at /x'];
        yield 'plus sign' => ["x: +12345678901234567890\n", 'Unquoted integer 12345678901234567890 at /x'];
        yield 'leading zeros' => ["x: 007\n", 'Unquoted integer 007 at /x'];
        yield 'hexadecimal' => ["x: [0x7FFFFFFFFFFFFFFFF]\n", 'Unquoted integer 0x7FFFFFFFFFFFFFFFF at /x/0: it is beyond the int range, so it would be read as an imprecise float'];
        yield 'octal' => ["x: 0o777777777777777777777777\n", 'Unquoted integer 0o777777777777777777777777 at /x'];
    }

    public function testKeepsLongIntegersThatAreQuotedFitOrAreText(): void
    {
        $yaml = <<<'YAML'
            quoted: '12345678901234567890'
            double: "12345678901234567890"
            tagged: !!str 12345678901234567890
            padded: '007'
            fits: 1000000000000000000
            hex: 0x10
            text: |
              id: 1234567890123456789012
            folded: >
              see 0x7FFFFFFFFFFFFFFFF
            end: true
            YAML;

        $decoded = json_decode(SpecSnapshot::fromYaml($yaml), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame([
            'quoted' => '12345678901234567890',
            'double' => '12345678901234567890',
            'tagged' => '12345678901234567890',
            'padded' => '007',
            'fits' => 1000000000000000000,
            'hex' => 16,
            'text' => "id: 1234567890123456789012\n",
            'folded' => "see 0x7FFFFFFFFFFFFFFFF\n",
            'end' => true,
        ], $decoded);
    }

    #[DataProvider('evaluatedKeys')]
    public function testRejectsMappingKeysTheParserEvaluates(string $yaml, string $location): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Evaluated mapping key at {$location}");

        SpecSnapshot::fromYaml($yaml);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function evaluatedKeys(): iterable
    {
        yield 'date' => ["2026-01-01: released\n", '/1767225600'];
        yield 'nested date' => ["versions:\n  2026-01-01: released\n", '/versions/1767225600'];
        yield 'date in a sequence' => ["versions:\n  - 2026-01-01: released\n", '/versions/0/1767225600'];
        yield 'hexadecimal' => ["codes:\n  0x10: sixteen\n", '/codes/16'];
        yield 'underscores' => ["codes:\n  1_000: thousand\n", '/codes/1000'];
    }

    public function testRejectsNonFiniteNumbers(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Non-finite number at /maximum');

        SpecSnapshot::fromYaml("maximum: .inf\n");
    }

    public function testRejectsInvalidYaml(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not valid YAML');

        SpecSnapshot::fromYaml("a: 1\na: 2\n");
    }

    public function testRejectsDocumentsThatAreNoMapping(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a YAML mapping');

        SpecSnapshot::fromYaml("- a\n- b\n");
    }
}
