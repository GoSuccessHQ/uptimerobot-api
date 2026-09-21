<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Docs\DocBlock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The docs generator reads the reference pages from the generated docblocks.
 */
#[CoversClass(DocBlock::class)]
final class DocBlockTest extends TestCase
{
    public function testReadsTheSummaryTheEndpointAndTheTags(): void
    {
        $doc = DocBlock::parse(<<<'PHP'
            /**
             * Delete a tag.
             *
             * Monitors keep their other tags.
             *
             * `DELETE /tags/{id}`
             *
             * @param int $id The tag, see {@see \GoSuccess\UptimeRobot\Model\Tag}.
             *
             * @deprecated
             */
            PHP);

        self::assertSame('Delete a tag.', $doc->summary);
        self::assertSame(['Monitors keep their other tags.'], $doc->paragraphs);
        self::assertSame('DELETE /tags/{id}', $doc->endpoint);
        self::assertSame(['id' => ['type' => 'int', 'description' => 'The tag, see `Tag`.']], $doc->params);
        self::assertNull($doc->return);
        self::assertTrue($doc->deprecated);
    }

    public function testReadsTypesThatContainSpaces(): void
    {
        // The generator writes maps and objects as array<array-key, T>.
        $doc = DocBlock::parse(<<<'PHP'
            /**
             * Set the labels.
             *
             * @param int                           $id     The widget.
             * @param array<array-key, string>|null $labels Labels to set.
             * @param array{name: string, ids: list<int>} $filter
             *
             * @return array<array-key, array<array-key, mixed>>
             */
            PHP);

        self::assertSame([
            'id' => ['type' => 'int', 'description' => 'The widget.'],
            'labels' => ['type' => 'array<array-key, string>|null', 'description' => 'Labels to set.'],
            'filter' => ['type' => 'array{name: string, ids: list<int>}', 'description' => ''],
        ], $doc->params);
        self::assertSame('array<array-key, array<array-key, mixed>>', $doc->return);
    }
}
