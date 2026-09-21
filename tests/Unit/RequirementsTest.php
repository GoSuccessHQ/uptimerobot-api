<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit;

use PhpToken;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionFunction;
use SplFileInfo;

/**
 * The runtime may only use what every PHP 8.4 has and the extensions that
 * composer.json requires. A call into another extension works in development,
 * where the extension or a polyfill of a dev dependency is loaded, but fails
 * with an Error on a server without it.
 */
#[CoversNothing]
final class RequirementsTest extends TestCase
{
    /**
     * The extensions that are always part of PHP 8.4: they cannot be disabled.
     */
    private const array ALWAYS_AVAILABLE = ['core', 'date', 'hash', 'json', 'pcre', 'random', 'reflection', 'spl', 'standard'];

    /**
     * Tokens after which a name followed by "(" is no call of a global function.
     */
    private const array NOT_A_FUNCTION_CALL = [\T_OBJECT_OPERATOR, \T_NULLSAFE_OBJECT_OPERATOR, \T_DOUBLE_COLON, \T_FUNCTION, \T_NEW, \T_CONST];

    public function testTheRuntimeUsesOnlyCorePhpAndTheRequiredExtensions(): void
    {
        $allowed = [...self::ALWAYS_AVAILABLE, ...self::requiredExtensions()];
        $constants = self::extensionConstants();
        $violations = [];

        foreach (self::sourceFiles() as $path => $code) {
            foreach (self::usedNames($code) as [$name, $isCall]) {
                $extension = match (true) {
                    $isCall && \function_exists($name) => self::functionExtension($name),
                    !$isCall => $constants[$name] ?? null,
                    default => null,
                };

                if ($extension !== null && !\in_array($extension, $allowed, true)) {
                    $violations[] = "{$path}: {$name} ({$extension})";
                }
            }

            foreach (self::importedClasses($code) as $class) {
                if (!class_exists($class, false) && !interface_exists($class, false) && !enum_exists($class, false)) {
                    continue;
                }

                $reflection = new ReflectionClass($class);
                $extension = $reflection->isInternal() ? strtolower((string) $reflection->getExtensionName()) : null;

                if ($extension !== null && !\in_array($extension, $allowed, true)) {
                    $violations[] = "{$path}: {$class} ({$extension})";
                }
            }
        }

        self::assertSame([], $violations, 'The runtime uses extensions that composer.json does not require.');
    }

    /**
     * @return list<string> The lower-case names of the `ext-*` requirements.
     */
    private static function requiredExtensions(): array
    {
        $composer = json_decode((string) file_get_contents(\dirname(__DIR__, 2) . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertIsArray($composer['require'] ?? null);

        $extensions = [];

        foreach (array_keys($composer['require']) as $package) {
            if (str_starts_with((string) $package, 'ext-')) {
                $extensions[] = strtolower(substr((string) $package, 4));
            }
        }

        return $extensions;
    }

    /**
     * The extension of a function, or "a polyfill" for one written in PHP,
     * which stands in for an extension that is not loaded.
     */
    private static function functionExtension(string $name): string
    {
        $reflection = new ReflectionFunction($name);

        return $reflection->isInternal() ? strtolower((string) $reflection->getExtensionName()) : 'a polyfill';
    }

    /**
     * @return array<string, string> Lower-case extension by constant name.
     */
    private static function extensionConstants(): array
    {
        $constants = [];

        foreach (get_defined_constants(true) as $extension => $names) {
            if ($extension === 'user') {
                continue;
            }

            foreach (array_keys($names) as $name) {
                $constants[$name] = strtolower($extension);
            }
        }

        return $constants;
    }

    /**
     * @return iterable<string, string> Code by path relative to the repository.
     */
    private static function sourceFiles(): iterable
    {
        $root = \dirname(__DIR__, 2);
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/src", RecursiveDirectoryIterator::SKIP_DOTS));

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                yield substr($file->getPathname(), \strlen($root) + 1) => (string) file_get_contents($file->getPathname());
            }
        }
    }

    /**
     * The global names the code refers to, without a leading backslash, and
     * whether each is called: functions and constants, among other names.
     *
     * @return list<array{string, bool}>
     */
    private static function usedNames(string $code): array
    {
        $tokens = array_values(array_filter(PhpToken::tokenize($code), static fn(PhpToken $token): bool => !$token->isIgnorable()));
        $names = [];

        foreach ($tokens as $index => $token) {
            if (!$token->is([\T_STRING, \T_NAME_FULLY_QUALIFIED])) {
                continue;
            }

            $previous = $tokens[$index - 1] ?? null;

            if ($previous !== null && $previous->is(self::NOT_A_FUNCTION_CALL)) {
                continue;
            }

            $names[] = [ltrim($token->text, '\\'), ($tokens[$index + 1] ?? null)?->text === '('];
        }

        return $names;
    }

    /**
     * @return list<string> The classes imported with a `use` statement.
     */
    private static function importedClasses(string $code): array
    {
        preg_match_all('/^use ([\w\\\\]+)(?: as \w+)?;$/m', $code, $matches);

        return $matches[1];
    }
}
