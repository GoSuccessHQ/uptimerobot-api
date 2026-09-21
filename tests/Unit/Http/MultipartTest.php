<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Http;

use DateTimeImmutable;
use GoSuccess\UptimeRobot\Http\FileUpload;
use GoSuccess\UptimeRobot\Http\Method;
use GoSuccess\UptimeRobot\Http\Multipart;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(Multipart::class)]
#[CoversClass(FileUpload::class)]
final class MultipartTest extends TestCase
{
    public function testEncodesFieldsTheWayTheApiDocumentsThem(): void
    {
        $form = Multipart::encode([
            'friendlyName' => 'Status',
            'hideUrlLinks' => true,
            'ratio' => 0.5,
            'sort' => Method::Get,
            'since' => new DateTimeImmutable('2026-09-18T14:30:00+02:00'),
            'monitorIds' => [1, null, 2],
            'tagIds' => [],
            'password' => null,
        ], boundary: 'b0undary');

        self::assertSame('multipart/form-data; boundary=b0undary', $form->contentType);
        self::assertSame(
            "--b0undary\r\nContent-Disposition: form-data; name=\"friendlyName\"\r\n\r\nStatus\r\n"
            . "--b0undary\r\nContent-Disposition: form-data; name=\"hideUrlLinks\"\r\n\r\ntrue\r\n"
            . "--b0undary\r\nContent-Disposition: form-data; name=\"ratio\"\r\n\r\n0.5\r\n"
            . "--b0undary\r\nContent-Disposition: form-data; name=\"sort\"\r\n\r\nGET\r\n"
            . "--b0undary\r\nContent-Disposition: form-data; name=\"since\"\r\n\r\n2026-09-18T12:30:00.000Z\r\n"
            . "--b0undary\r\nContent-Disposition: form-data; name=\"monitorIds[]\"\r\n\r\n1\r\n"
            . "--b0undary\r\nContent-Disposition: form-data; name=\"monitorIds[]\"\r\n\r\n2\r\n"
            // An empty list is a single empty field, as the API documents.
            . "--b0undary\r\nContent-Disposition: form-data; name=\"tagIds[]\"\r\n\r\n\r\n"
            . "--b0undary--\r\n",
            $form->body,
        );
    }

    public function testEncodesFiles(): void
    {
        $form = Multipart::encode(['name' => 'x'], ['logo' => new FileUpload('logo.png', "\x89PNG\r\n", 'image/png')], 'b');

        self::assertSame(
            "--b\r\nContent-Disposition: form-data; name=\"name\"\r\n\r\nx\r\n"
            . "--b\r\nContent-Disposition: form-data; name=\"logo\"; filename=\"logo.png\"\r\nContent-Type: image/png\r\n\r\n\x89PNG\r\n\r\n"
            . "--b--\r\n",
            $form->body,
        );
    }

    public function testEscapesNamesSoTheyCannotBreakOutOfTheHeader(): void
    {
        $form = Multipart::encode([], ['icon' => new FileUpload("a\"b\r\nX-Evil: 1.png", 'data')], 'b');

        self::assertStringContainsString('filename="a%22b%0D%0AX-Evil: 1.png"', $form->body);
        self::assertStringContainsString("Content-Type: application/octet-stream\r\n", $form->body);
    }

    public function testPicksARandomBoundaryThatDoesNotOccurInTheContent(): void
    {
        $first = Multipart::encode(['a' => '1']);
        $second = Multipart::encode(['a' => '1']);

        self::assertMatchesRegularExpression('~^multipart/form-data; boundary=uptimerobot-[0-9a-f]{32}$~', $first->contentType);
        self::assertNotSame($first->contentType, $second->contentType);
    }

    public function testEncodesAnEmptyForm(): void
    {
        self::assertSame("--b--\r\n", Multipart::encode([], [], 'b')->body);
    }

    /**
     * @param array<string, mixed> $fields
     */
    #[DataProvider('unsupportedFields')]
    public function testRejectsValuesAFormCannotExpress(array $fields): void
    {
        $this->expectException(InvalidArgumentException::class);

        Multipart::encode($fields);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function unsupportedFields(): iterable
    {
        yield 'object as array' => [['customSettings' => ['theme' => 'dark']]];
        yield 'object' => [['customSettings' => new stdClass()]];
        yield 'nested list' => [['monitorIds' => [[1, 2]]]];
        yield 'object in list' => [['monitorIds' => [new stdClass()]]];
    }

    public function testRejectsFilesThatAreNoFileUploads(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The file logo must be a FileUpload, got string.');

        /** @phpstan-ignore argument.type (deliberately wrong at runtime) */
        Multipart::encode([], ['logo' => '/path/to/logo.png']);
    }

    #[DataProvider('invalidBoundaries')]
    public function testRejectsAnInvalidBoundary(string $boundary): void
    {
        $this->expectException(InvalidArgumentException::class);

        Multipart::encode([], [], $boundary);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidBoundaries(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('b', 71)];
        yield 'quote' => ['b"'];
        yield 'trailing line break' => ["b\n"];
    }

    public function testRejectsAFixedBoundaryThatOccursInTheContent(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Multipart::encode(['a' => 'x--b'], [], 'b');
    }

    public function testReadsFilesAndGuessesTheirContentType(): void
    {
        $directory = sys_get_temp_dir() . '/uptimerobot-api-test-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $path = "{$directory}/Logo.PNG";
        file_put_contents($path, 'image-bytes');

        try {
            $file = FileUpload::fromPath($path);
            self::assertSame('Logo.PNG', $file->filename);
            self::assertSame('image-bytes', $file->contents);
            self::assertSame('image/png', $file->contentType);

            $renamed = FileUpload::fromPath($path, 'image/webp', 'icon.webp');
            self::assertSame('icon.webp', $renamed->filename);
            self::assertSame('image/webp', $renamed->contentType);

            rename($path, "{$directory}/logo.unknown");
            self::assertSame('application/octet-stream', FileUpload::fromPath("{$directory}/logo.unknown")->contentType);
        } finally {
            @unlink($path);
            @unlink("{$directory}/logo.unknown");
            rmdir($directory);
        }
    }

    public function testRejectsFilesThatCannotBeRead(): void
    {
        $this->expectException(RuntimeException::class);

        // A directory would otherwise be read as an empty file.
        FileUpload::fromPath(sys_get_temp_dir());
    }

    public function testRejectsAMissingFile(): void
    {
        $this->expectException(RuntimeException::class);

        FileUpload::fromPath('/does/not/exist.png');
    }

    #[DataProvider('invalidFiles')]
    public function testRejectsInvalidFileAttributes(string $filename, string $contentType): void
    {
        $this->expectException(InvalidArgumentException::class);

        new FileUpload($filename, 'data', $contentType);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidFiles(): iterable
    {
        yield 'empty file name' => [' ', 'image/png'];
        yield 'content type without subtype' => ['logo.png', 'image'];
        yield 'content type with a line break' => ['logo.png', "image/png\r\nX-Evil: 1"];
        yield 'content type with a trailing line break' => ['logo.png', "image/png\n"];
    }

    public function testAcceptsContentTypesWithParameters(): void
    {
        self::assertSame('image/svg+xml; charset=utf-8', new FileUpload('logo.svg', '<svg/>', 'image/svg+xml; charset=utf-8')->contentType);
    }

    public function testKeepsTheContentsOutOfDumps(): void
    {
        $dump = print_r(new FileUpload('logo.png', 'secret-bytes', 'image/png'), true);

        self::assertStringNotContainsString('secret-bytes', $dump);
        self::assertStringContainsString('12', $dump);
    }
}
