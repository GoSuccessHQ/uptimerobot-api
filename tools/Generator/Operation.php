<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tools\Generator;

/**
 * One operation (path + method) of a specification.
 */
final class Operation
{
    public readonly string $id;

    /** @var list<Parameter> */
    public readonly array $parameters;

    /**
     * @param array<array-key, mixed> $node
     * @param array<array-key, mixed> $sharedParameters Path-level parameters.
     * @param bool                    $ambiguousId      Whether other operations share the operationId,
     *                                                  in which case "METHOD /path" identifies it.
     */
    public function __construct(
        public readonly Spec $spec,
        public readonly string $method,
        public readonly string $path,
        public readonly array $node,
        array $sharedParameters = [],
        bool $ambiguousId = false,
    ) {
        $operationId = self::operationId($node);
        $this->id = $operationId !== null && !$ambiguousId ? $operationId : "{$method} {$path}";

        $parameters = [];

        foreach ([...$sharedParameters, ...(\is_array($node['parameters'] ?? null) ? $node['parameters'] : [])] as $parameter) {
            if (\is_array($parameter)) {
                $parsed = new Parameter($spec, $parameter);
                // Operation-level parameters override path-level ones.
                $parameters["{$parsed->in}:{$parsed->name}"] = $parsed;
            }
        }

        $this->parameters = array_values($parameters);
    }

    /**
     * @param array<array-key, mixed> $node
     */
    public static function operationId(array $node): ?string
    {
        $operationId = $node['operationId'] ?? null;

        return \is_string($operationId) && $operationId !== '' ? $operationId : null;
    }

    public function summary(): ?string
    {
        return $this->string('summary');
    }

    public function description(): ?string
    {
        return $this->string('description');
    }

    public function isDeprecated(): bool
    {
        return ($this->node['deprecated'] ?? false) === true;
    }

    /**
     * @return list<string>
     */
    public function tags(): array
    {
        $tags = $this->node['tags'] ?? null;

        return \is_array($tags) ? array_values(array_filter($tags, is_string(...))) : [];
    }

    /**
     * @return list<Parameter>
     */
    public function parametersIn(string $location): array
    {
        return array_values(array_filter($this->parameters, static fn(Parameter $parameter): bool => $parameter->in === $location));
    }

    /**
     * Path placeholders in the order they appear, e.g. ['pspId', 'id'].
     *
     * @return list<string>
     */
    public function pathPlaceholders(): array
    {
        preg_match_all('/\{([^}]+)\}/', $this->path, $matches);

        return $matches[1];
    }

    /**
     * The JSON request body schema, if the operation accepts one.
     */
    public function requestSchema(): ?Schema
    {
        $content = $this->requestContent();

        foreach (['application/json', 'text/json', 'application/*+json'] as $type) {
            $media = $content[$type] ?? null;

            if (\is_array($media) && \is_array($media['schema'] ?? null)) {
                return new Schema($this->spec, $media['schema']);
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function requestContentTypes(): array
    {
        return array_map(strval(...), array_keys($this->requestContent()));
    }

    public function isRequestBodyRequired(): bool
    {
        $body = $this->node['requestBody'] ?? null;

        return \is_array($body) && ($body['required'] ?? false) === true;
    }

    /**
     * Successful (2xx) responses: status code => JSON schema, or null for a
     * response without a JSON body.
     *
     * @return array<int, Schema|null>
     */
    public function successResponses(): array
    {
        $responses = \is_array($this->node['responses'] ?? null) ? $this->node['responses'] : [];
        $result = [];

        foreach ($responses as $status => $response) {
            $code = (int) $status;

            if ($code < 200 || $code >= 300 || !\is_array($response)) {
                continue;
            }

            $content = \is_array($response['content'] ?? null) ? $response['content'] : [];
            $schema = null;

            foreach (['application/json', 'text/json', 'text/plain', '*/*'] as $type) {
                $media = $content[$type] ?? null;

                if (\is_array($media) && \is_array($media['schema'] ?? null)) {
                    $schema = new Schema($this->spec, $media['schema']);

                    break;
                }
            }

            $result[$code] = $schema;
        }

        ksort($result);

        return $result;
    }

    /**
     * Content types of the successful responses, e.g. to detect binary downloads.
     *
     * @return list<string>
     */
    public function successContentTypes(): array
    {
        $responses = \is_array($this->node['responses'] ?? null) ? $this->node['responses'] : [];
        $types = [];

        foreach ($responses as $status => $response) {
            $code = (int) $status;

            if ($code >= 200 && $code < 300 && \is_array($response) && \is_array($response['content'] ?? null)) {
                foreach (array_keys($response['content']) as $type) {
                    $types[] = (string) $type;
                }
            }
        }

        return array_values(array_unique($types));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function requestContent(): array
    {
        $body = $this->node['requestBody'] ?? null;
        $content = \is_array($body) ? ($body['content'] ?? null) : null;

        return \is_array($content) ? $content : [];
    }

    private function string(string $key): ?string
    {
        $value = $this->node[$key] ?? null;

        return \is_string($value) && trim($value) !== '' ? $value : null;
    }
}
