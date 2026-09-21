<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator\Writer;

use GoSuccess\UptimeRobot\Tools\Generator\Config\ApiConfig;
use GoSuccess\UptimeRobot\Tools\Generator\Definition\ResourceDefinition;

/**
 * Renders the client class.
 *
 * Resources are exposed as properties that are created on first access
 * (property hooks), so constructing a client costs nothing for the resources
 * that are never used.
 */
final class ClientWriter
{
    private const string RUNTIME = ApiConfig::RUNTIME;

    public function __construct(private readonly ApiConfig $config) {}

    /**
     * @param list<ResourceDefinition> $resources
     */
    public function render(array $resources, string $source): string
    {
        $file = new CodeFile($this->config->namespace, $this->config->client);

        $options = $file->alias(self::RUNTIME . '\\ClientOptions');
        $connection = $file->alias(self::RUNTIME . '\\Http\\Connection');
        $curl = $file->alias(self::RUNTIME . '\\Http\\CurlHttpClient');
        $httpClient = $file->alias(self::RUNTIME . '\\Http\\HttpClient');
        $limiter = $file->alias(self::RUNTIME . '\\RateLimit\\RateLimiter');
        $nullLimiter = $file->alias(self::RUNTIME . '\\RateLimit\\NullRateLimiter');
        $status = $file->alias(self::RUNTIME . '\\RateLimit\\RateLimitStatus');
        $sensitive = $file->alias('SensitiveParameter');

        $properties = '';

        foreach ($resources as $resource) {
            $class = $file->alias($resource->class);
            $property = $resource->config->property;
            $properties .= Doc::block([[$resource->config->description]], '    ');
            $properties .= "    public private(set) {$class} \${$property} {\n        get => \$this->{$property} ??= new {$class}(\$this->connection);\n    }\n\n";
        }

        $properties .= Doc::block([
            ['The request quota the most recent response reported (its `x-ratelimit-*`', 'headers), or null before the first response that carried them.'],
        ], '    ');
        $properties .= "    public ?{$status} \$rateLimit {\n        get => \$this->connection->rateLimit;\n    }\n\n";

        $credential = $this->config->credential;
        $doc = Doc::block([
            ["Client for the {$this->config->title} (`{$this->config->baseUri}`)."],
            ['Resources are created on first access, so unused ones cost nothing.'],
        ]);

        $parameters = [
            ['string', "\${$credential}", $this->config->credentialDescription],
            [$options, '$options', 'Timeouts, retries, rate-limit handling and user agent.'],
            ["{$httpClient}|null", '$httpClient', 'Custom transport; defaults to the built-in cURL transport.'],
            [$limiter, '$rateLimiter', 'Client-side throttling; disabled by default.'],
            ['string', '$baseUri', 'Base URI of the API.'],
        ];
        $typeWidth = max(array_map(static fn(array $parameter): int => \strlen($parameter[0]), $parameters));
        $nameWidth = max(array_map(static fn(array $parameter): int => \strlen($parameter[1]), $parameters));
        $constructorDoc = Doc::block([array_map(
            static fn(array $parameter): string => '@param ' . str_pad($parameter[0], $typeWidth) . ' ' . str_pad($parameter[1], $nameWidth) . " {$parameter[2]}",
            $parameters,
        )], '    ');

        $body = "{$doc}final class {$this->config->client}\n{\n"
            . "    public const string DEFAULT_BASE_URI = '{$this->config->baseUri}';\n\n"
            . $properties
            . "    private readonly {$connection} \$connection;\n\n"
            . $constructorDoc
            . "    public function __construct(\n"
            . "        #[{$sensitive}]\n"
            . "        string \${$credential},\n"
            . "        {$options} \$options = new {$options}(),\n"
            . "        ?{$httpClient} \$httpClient = null,\n"
            . "        {$limiter} \$rateLimiter = new {$nullLimiter}(),\n"
            . "        string \$baseUri = self::DEFAULT_BASE_URI,\n"
            . "    ) {\n"
            . "        \$this->connection = new {$connection}(\n"
            . "            \$baseUri,\n"
            . "            \${$credential},\n"
            . "            \$options,\n"
            . "            \$httpClient ?? new {$curl}(\$options->timeout, \$options->connectTimeout),\n"
            . "            \$rateLimiter,\n"
            . "        );\n"
            . "    }\n\n"
            . "    /**\n"
            . "     * Hide the API key from var_dump() and print_r().\n"
            . "     *\n"
            . "     * @return array<string, mixed>\n"
            . "     */\n"
            . "    public function __debugInfo(): array\n"
            . "    {\n"
            . "        return ['baseUri' => \$this->connection->baseUri, '{$credential}' => '********', 'rateLimit' => \$this->rateLimit];\n"
            . "    }\n"
            . "}\n";

        return $file->render($body, $source);
    }
}
