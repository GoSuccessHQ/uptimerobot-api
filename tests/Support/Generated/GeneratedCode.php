<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support\Generated;

use GoSuccess\UptimeRobot\Http\HttpClient;
use GoSuccess\UptimeRobot\Tests\Support\Generator\KitchenSink;
use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use LogicException;

/**
 * Generated code the spec-driven tests check: the real client and the
 * synthetic kitchen sink, which uses the features the real configuration does
 * not (yet).
 */
final class GeneratedCode
{
    /**
     * @return array<string, Analysis>
     */
    public static function all(): array
    {
        return [
            'uptimerobot' => GeneratedApi::analysis(),
            'kitchen-sink' => KitchenSink::analysis(),
        ];
    }

    public static function analysis(string $name): Analysis
    {
        return self::all()[$name] ?? throw new LogicException("Unknown generated code {$name}.");
    }

    /**
     * A client of the generated code that sends its requests to the given transport.
     */
    public static function client(Analysis $analysis, HttpClient $http): object
    {
        $class = $analysis->config->fqcn('', $analysis->config->client);

        return new $class(...[$analysis->config->credential => 'secret', 'httpClient' => $http]);
    }
}
