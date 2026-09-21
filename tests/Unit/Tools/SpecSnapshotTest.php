<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools;

use GoSuccess\UptimeRobot\Tools\SpecSnapshot;
use PHPUnit\Framework\Attributes\CoversClass;
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

    public function testRejectsIntegersBeyondTheIntRange(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('integer beyond the int range');

        SpecSnapshot::fromYaml("maximum: 123456789012345678901\n");
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
