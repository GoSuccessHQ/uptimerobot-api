<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Unit\Tools;

use GoSuccess\UptimeRobot\Tests\Support\PhpExtensions;
use PhpCsFixer\ConfigInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * php-cs-fixer must format the code the same way on every PHP that can run
 * "composer check" and "composer generate".
 */
#[CoversNothing]
final class CodingStandardTest extends TestCase
{
    /**
     * native_constant_invocation qualifies the constants the running PHP
     * defines and, being strict, unqualifies all others. A constant of an
     * extension that a PHP may lack, e.g. SIGALRM of ext-pcntl, which the
     * php:8.4-cli image and Windows lack, would therefore gain or lose its
     * backslash with the PHP, unless the rule excludes it.
     */
    public function testQualifiesConstantsTheSameWayOnEveryPhp(): void
    {
        $config = require \dirname(__DIR__, 3) . '/.php-cs-fixer.php';
        self::assertInstanceOf(ConfigInterface::class, $config);
        $rule = $config->getRules()['native_constant_invocation'] ?? null;
        self::assertIsArray($rule);
        $excluded = $rule['exclude'] ?? [];
        self::assertIsArray($excluded);

        $installed = PhpExtensions::readJson('vendor/composer/installed.json');
        $root = PhpExtensions::readJson('composer.json');
        // Composer refuses to install the packages without these.
        $guaranteed = [...PhpExtensions::ALWAYS_AVAILABLE, ...PhpExtensions::requiredBy($root), ...PhpExtensions::requiredBy($root, 'require-dev')];

        foreach (\is_array($installed['packages'] ?? null) ? $installed['packages'] : [] as $package) {
            $guaranteed = [...$guaranteed, ...(\is_array($package) ? PhpExtensions::requiredBy($package) : [])];
        }

        $constants = PhpExtensions::ofConstants();
        $unstable = [];

        foreach (PhpExtensions::sourceFiles(['src', 'tests', 'examples', 'tools']) as $path => $code) {
            foreach (PhpExtensions::namesUsedIn($code) as [$name, $isCall]) {
                $extension = $isCall ? null : ($constants[$name] ?? null);

                if ($extension !== null && !\in_array($extension, $guaranteed, true) && !\in_array($name, $excluded, true)) {
                    $unstable["{$path}: {$name} ({$extension})"] = true;
                }
            }
        }

        self::assertSame([], array_keys($unstable), 'Add these constants of optional extensions to the exclude list of native_constant_invocation.');
    }
}
