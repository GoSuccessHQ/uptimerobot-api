<?php

declare(strict_types=1);

namespace GoSuccess\UptimeRobot\Tests\Support\Generator;

use GoSuccess\UptimeRobot\Tools\Generator\Analysis;
use LogicException;

/**
 * A synthetic specification in the style of UptimeRobot's that uses every
 * feature of the generator, with its configuration. The generated code is
 * checked by the same spec-driven tests as the real client, and by the
 * generator's own tests.
 */
final class KitchenSink
{
    private static ?Analysis $analysis = null;

    /**
     * The generated and loaded code, built once per test run.
     */
    public static function analysis(): Analysis
    {
        return self::$analysis ??= GeneratorFixture::generate(self::spec(), self::config());
    }

    /**
     * Fully qualified name of a generated class, e.g. class('Model\\Widget').
     *
     * @return class-string
     */
    public static function class(string $relative): string
    {
        $class = self::analysis()->config->namespace . '\\' . $relative;

        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            throw new LogicException("{$class} was not generated.");
        }

        return $class;
    }

    /**
     * @return array<string, mixed>
     */
    public static function spec(): array
    {
        $json = static fn(array $schema): array => ['content' => ['application/json' => ['schema' => $schema]]];
        $ref = static fn(string $name): array => ['$ref' => "#/components/schemas/{$name}"];
        $id = ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'number']];

        return [
            'paths' => [
                '/widgets' => [
                    'get' => [
                        'operationId' => 'WidgetsController_list',
                        'summary' => 'List widgets',
                        'parameters' => [
                            // "*/" would end the docblock.
                            ['name' => 'tag', 'in' => 'query', 'description' => "Tags such as\n\"eu/*/web\".", 'schema' => ['type' => 'array', 'items' => ['type' => 'string']]],
                            ['name' => 'limit', 'in' => 'query', 'description' => 'Widgets per page (1-100).', 'schema' => ['type' => 'number']],
                            ['name' => 'status', 'in' => 'query', 'description' => 'Comma-separated statuses.', 'schema' => ['type' => 'string']],
                            ['name' => 'group_id', 'in' => 'query', 'schema' => ['type' => 'number']],
                            ['name' => 'cursor', 'in' => 'query', 'schema' => ['type' => 'string']],
                        ],
                        'responses' => ['200' => $json($ref('WidgetPaginationDto'))],
                    ],
                    'post' => [
                        'operationId' => 'WidgetsController_create',
                        'summary' => 'Create a widget',
                        'requestBody' => ['required' => true, ...$json([
                            'oneOf' => [$ref('CreateHttpWidgetDto'), $ref('CreatePingWidgetDto')],
                            'discriminator' => [
                                'propertyName' => 'type',
                                'mapping' => ['HTTP' => '#/components/schemas/CreateHttpWidgetDto', 'PING' => '#/components/schemas/CreatePingWidgetDto'],
                            ],
                        ])],
                        'responses' => ['201' => $json($ref('WidgetDto'))],
                    ],
                ],
                '/widgets/recent' => [
                    'get' => [
                        'operationId' => 'WidgetsController_recent',
                        'responses' => ['200' => $json(['type' => 'array', 'items' => $ref('WidgetDto')])],
                    ],
                ],
                '/widgets/{id}' => [
                    'get' => [
                        'operationId' => 'WidgetsController_get',
                        'parameters' => [$id],
                        'responses' => ['200' => $json($ref('WidgetDto'))],
                    ],
                    'patch' => [
                        'operationId' => 'WidgetsController_update',
                        'parameters' => [$id],
                        'requestBody' => ['required' => true, ...$json($ref('UpdateWidgetDto'))],
                        'responses' => ['200' => $json($ref('WidgetDto'))],
                    ],
                    'delete' => [
                        'operationId' => 'WidgetsController_delete',
                        'parameters' => [$id],
                        'responses' => ['204' => ['description' => 'Deleted']],
                    ],
                ],
                '/widgets/{id}/pause' => [
                    'post' => [
                        'operationId' => 'WidgetsController_pause',
                        'description' => 'Optional empty body. Send Content-Type: application/json.',
                        'parameters' => [$id],
                        'requestBody' => ['required' => false, ...$json(['type' => 'object'])],
                        'responses' => ['201' => ['description' => '']],
                    ],
                ],
                '/widgets/{id}/archive' => [
                    'post' => [
                        'operationId' => 'WidgetsController_archive',
                        'parameters' => [$id],
                        'responses' => ['200' => ['description' => '']],
                    ],
                ],
                '/widgets/{id}/ping' => [
                    // Phased out by the API: callers are warned at runtime.
                    'post' => [
                        'operationId' => 'WidgetsController_ping',
                        'summary' => 'Ping a widget',
                        'deprecated' => true,
                        'parameters' => [$id],
                        'responses' => ['204' => ['description' => '']],
                    ],
                ],
                '/widgets/{id}/restore' => [
                    // Declares no body, but the API insists on application/json.
                    'post' => [
                        'operationId' => 'WidgetsController_restore',
                        'parameters' => [$id],
                        'responses' => ['200' => ['description' => '']],
                    ],
                ],
                '/widgets/{id}/notes' => [
                    'post' => [
                        'operationId' => 'WidgetsController_addNote',
                        'parameters' => [$id],
                        'requestBody' => ['required' => true, ...$json($ref('CreateNoteDto'))],
                        'responses' => ['201' => ['description' => '']],
                    ],
                ],
                '/widgets/{id}/labels' => [
                    'put' => [
                        'operationId' => 'WidgetsController_setLabels',
                        'parameters' => [$id],
                        'requestBody' => ['required' => false, ...$json(['type' => 'object', 'additionalProperties' => ['type' => 'string']])],
                        'responses' => ['200' => ['description' => '']],
                    ],
                ],
                '/widgets/{id}/alerts' => [
                    'get' => [
                        'operationId' => 'WidgetsController_alerts',
                        'parameters' => [$id],
                        'responses' => ['200' => $json($ref('SentAlertsDto'))],
                    ],
                ],
                '/hooks' => [
                    'post' => [
                        'operationId' => 'HooksController_create',
                        'requestBody' => ['required' => true, ...$json(['oneOf' => [$ref('CreateSlackHookDto'), $ref('CreateTelegramHookDto')]])],
                        'responses' => ['201' => ['description' => '']],
                    ],
                ],
                '/events' => [
                    'get' => [
                        'operationId' => 'EventsController_list',
                        'responses' => ['200' => $json($ref('EventLogDto'))],
                    ],
                ],
                '/tags' => [
                    'get' => [
                        'operationId' => 'TagsController_list',
                        'parameters' => [['name' => 'cursor', 'in' => 'query', 'schema' => ['type' => 'number']]],
                        'responses' => ['200' => $json($ref('TagPaginationDto'))],
                    ],
                ],
                '/settings' => [
                    'get' => [
                        'operationId' => 'SettingsController_get',
                        'responses' => ['200' => $json($ref('SettingsDto'))],
                    ],
                    'patch' => [
                        'operationId' => 'SettingsController_update',
                        'requestBody' => ['required' => true, ...$json($ref('SettingsDto'))],
                        'responses' => ['200' => $json($ref('SettingsDto'))],
                    ],
                ],
                '/pages' => [
                    'post' => [
                        'operationId' => 'PagesController_create',
                        'requestBody' => ['required' => true, 'content' => [
                            'application/json' => ['schema' => $ref('CreatePageDto')],
                            'multipart/form-data' => ['schema' => $ref('CreatePageDto')],
                        ]],
                        'responses' => ['201' => ['description' => '']],
                    ],
                ],
            ],
            'components' => ['schemas' => [
                'WidgetDto' => [
                    'description' => 'WidgetPublic',
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'number'],
                        'friendlyName' => ['type' => 'string'],
                        // Erased by zod, like MonitorDto.status.
                        'status' => [],
                        // zod's Date | string.
                        'createdAt' => ['oneOf' => [[], ['type' => 'string']]],
                        'kind' => ['type' => 'string', 'enum' => ['BIG_ONE', 'small_one'], 'nullable' => true],
                        'score' => ['type' => 'number'],
                        'checks' => ['type' => 'integer'],
                        'meta' => ['description' => 'Anything.'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'labels' => ['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'nullable' => true],
                        'regions' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['na', 'eu']]],
                        'owner' => [
                            'type' => 'object',
                            'properties' => ['id' => ['type' => 'number'], 'IPv6' => ['type' => 'string'], 'MANUAL_SELECTED' => ['type' => 'boolean']],
                        ],
                    ],
                    'required' => ['id', 'friendlyName', 'status', 'kind', 'score', 'tags'],
                ],
                'WidgetPaginationDto' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'array', 'items' => $ref('WidgetDto')],
                        'nextLink' => ['type' => 'string', 'nullable' => true],
                    ],
                    'required' => ['data', 'nextLink'],
                ],
                'CreateHttpWidgetDto' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['HTTP']],
                        'friendlyName' => ['type' => 'string', 'description' => 'Name of the widget.'],
                        'url' => ['type' => 'string'],
                        'interval' => ['type' => 'number'],
                        'kind' => ['type' => 'string', 'enum' => ['BIG_ONE', 'small_one']],
                    ],
                    'required' => ['type', 'friendlyName', 'url'],
                ],
                'CreatePingWidgetDto' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['PING']],
                        'friendlyName' => ['type' => 'string'],
                        'host' => ['type' => 'string'],
                        // Required here, but not by the API (see 'optionalProperties').
                        'region' => ['type' => 'string'],
                    ],
                    'required' => ['type', 'friendlyName', 'region'],
                ],
                'UpdateWidgetDto' => [
                    'type' => 'object',
                    'properties' => [
                        'friendlyName' => ['type' => 'string'],
                        'kind' => ['type' => 'string', 'enum' => ['BIG_ONE', 'small_one'], 'nullable' => true],
                        'startsAt' => ['type' => 'string', 'format' => 'date-time'],
                        'groupIds' => ['type' => 'array', 'items' => ['type' => 'number']],
                        'headers' => ['type' => 'object'],
                        'retry' => $ref('RetryPolicyDto'),
                        'fallbacks' => ['type' => 'array', 'items' => $ref('RetryPolicyDto')],
                        // null means "remove", unlike an empty value.
                        'aliases' => ['type' => 'array', 'items' => ['type' => 'string'], 'nullable' => true],
                        'overrides' => ['type' => 'array', 'items' => $ref('RetryPolicyDto'), 'nullable' => true],
                        'weights' => ['type' => 'object', 'additionalProperties' => ['type' => 'number'], 'nullable' => true],
                    ],
                ],
                // Sent by hand-written code only.
                'BulkWidgetUpdateDto' => [
                    'type' => 'object',
                    'properties' => [
                        'interval' => ['type' => 'number'],
                        'retry' => $ref('RetryPolicyDto'),
                    ],
                ],
                'RetryPolicyDto' => [
                    'type' => 'object',
                    'properties' => [
                        'backoff' => ['type' => 'string'],
                        'onTimeout' => ['type' => 'boolean'],
                    ],
                ],
                'CreateNoteDto' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => 'The note.'],
                        'publishedAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                        'kind' => ['type' => 'string', 'enum' => ['BIG_ONE', 'small_one']],
                    ],
                    'required' => ['content', 'publishedAt'],
                ],
                'CreateSlackHookDto' => [
                    'type' => 'object',
                    'properties' => ['type' => ['type' => 'string', 'enum' => ['Slack']], 'data' => $ref('SlackHookDataDto')],
                    'required' => ['type', 'data'],
                ],
                'SlackHookDataDto' => [
                    'type' => 'object',
                    'properties' => ['webhookURL' => ['type' => 'string'], 'channel' => ['type' => 'string']],
                    'required' => ['webhookURL'],
                ],
                'CreateTelegramHookDto' => [
                    'type' => 'object',
                    'properties' => ['type' => ['type' => 'string', 'enum' => ['Telegram']], 'data' => $ref('TelegramHookDataDto')],
                    'required' => ['type', 'data'],
                ],
                'TelegramHookDataDto' => [
                    'type' => 'object',
                    'properties' => ['friendlyName' => ['type' => 'string']],
                ],
                'EventLogDto' => [
                    'type' => 'object',
                    'properties' => ['data' => ['type' => 'array', 'items' => ['oneOf' => [
                        [
                            'type' => 'object',
                            'properties' => ['type' => ['type' => 'string', 'enum' => ['CREATED']], 'at' => ['oneOf' => [[], ['type' => 'string']]]],
                            'required' => ['type', 'at'],
                        ],
                        [
                            'type' => 'object',
                            'properties' => ['type' => ['type' => 'string', 'enum' => ['DELETED']], 'reason' => ['type' => 'string', 'nullable' => true]],
                            'required' => ['type'],
                        ],
                    ]]]],
                    'required' => ['data'],
                ],
                'SentAlertsDto' => [
                    'type' => 'object',
                    'properties' => ['data' => ['type' => 'array', 'items' => [
                        'type' => 'object',
                        'properties' => ['id' => ['type' => 'string'], 'channel' => ['type' => 'string']],
                    ]]],
                ],
                'TagPaginationDto' => [
                    'type' => 'object',
                    'properties' => [
                        'data' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['id' => ['type' => 'number'], 'name' => ['type' => 'string']]]],
                        'nextCursorId' => ['type' => 'number', 'nullable' => true],
                    ],
                ],
                'SettingsDto' => [
                    'type' => 'object',
                    'properties' => [
                        'enabled' => ['type' => 'boolean'],
                        'threshold' => ['type' => 'number'],
                        // Values with descriptions, one of them phased out.
                        'window' => ['oneOf' => [
                            ['type' => 'string', 'enum' => ['SHORT'], 'description' => 'Five minutes.'],
                            ['type' => 'string', 'enum' => ['LONG'], 'description' => 'An hour.'],
                            ['type' => 'string', 'enum' => ['WHOLE_DAY'], 'deprecated' => true, 'description' => "Use LONG; it's accepted through 2026-10-10."],
                        ]],
                        // Any JSON value, null included.
                        'fallback' => ['description' => 'Anything.'],
                    ],
                ],
                'CreatePageDto' => [
                    'type' => 'object',
                    'properties' => [
                        'friendlyName' => ['type' => 'string'],
                        'logo' => ['type' => 'string', 'format' => 'binary'],
                    ],
                    'required' => ['friendlyName'],
                ],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'naming' => ['stripSuffixes' => ['Dto']],
            'schemas' => [
                'WidgetDto.owner' => 'WidgetOwner',
                'UpdateWidgetDto' => 'WidgetUpdate',
                'SentAlertsDto.data[]' => 'SentAlert',
                'TagPaginationDto.data[]' => 'Tag',
            ],
            'enums' => [
                'WidgetDto.kind' => 'WidgetKind',
                'CreateHttpWidgetDto.kind' => 'WidgetKind',
                'UpdateWidgetDto.kind' => 'WidgetKind',
                'CreateNoteDto.kind' => 'WidgetKind',
                'WidgetDto.status' => 'WidgetStatus',
                'WidgetsController_list.status[]' => 'WidgetStatus',
                'WidgetDto.regions[]' => 'Region',
                'SettingsDto.window' => 'SettingsWindow',
            ],
            'enumCases' => [
                'Region' => ['na' => 'NorthAmerica', 'eu' => 'Europe'],
            ],
            'types' => [
                'WidgetDto.status' => ['type' => 'string', 'enum' => ['UP', 'LOOKS_DOWN', 'PAUSED']],
                'WidgetDto.createdAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                'EventLogDto.data[]<CREATED>.at' => ['type' => 'string', 'format' => 'date-time'],
                'WidgetsController_list.status' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => ['UP', 'LOOKS_DOWN', 'PAUSED']]],
            ],
            'integers' => ['*.id', '*Ids[]', 'WidgetsController_list.limit', 'WidgetsController_list.group_id', 'TagsController_list.cursor', 'CreateHttpWidgetDto.interval', 'SettingsDto.threshold', 'UpdateWidgetDto.weights{}', 'BulkWidgetUpdateDto.interval'],
            'floats' => ['WidgetDto.score'],
            'mixed' => ['WidgetDto.meta', 'SettingsDto.fallback'],
            'additions' => [
                'properties' => ['WidgetDto.apiKey' => ['type' => 'string', 'description' => 'Sent, but not documented.']],
            ],
            'excludedProperties' => ['CreatePageDto.logo'],
            'optionalProperties' => ['CreatePingWidgetDto.region'],
            'extraRequestModels' => ['BulkWidgetUpdateDto'],
            'commaSeparated' => ['WidgetsController_list.status'],
            // The specification describes the query string, the method takes a list.
            'parameterDescriptions' => ['WidgetsController_list.status' => 'The statuses to filter by; a widget matches if it has any of them.'],
            'unions' => [
                'WidgetsController_create.body' => [
                    'interface' => 'WidgetCreate',
                    'discriminator' => 'type',
                    'variants' => ['HTTP' => 'HttpWidgetCreate', 'PING' => 'PingWidgetCreate'],
                ],
                'HooksController_create.body' => [
                    'interface' => 'HookCreate',
                    'discriminator' => 'type',
                    'envelope' => 'data',
                    'variants' => ['Slack' => 'SlackHookCreate', 'Telegram' => 'TelegramHookCreate'],
                ],
                'EventLogDto.data[]' => [
                    'interface' => 'Event',
                    'discriminator' => 'type',
                    'variants' => ['CREATED' => 'CreatedEvent', 'DELETED' => 'DeletedEvent'],
                    'fallback' => 'UnknownEvent',
                    'description' => 'An entry of the event log.',
                ],
            ],
            'pagination' => [
                'nextLink' => ['cursor' => 'cursor', 'size' => 'limit', 'items' => 'data', 'next' => 'nextLink', 'factory' => 'Pagination\\Cursor::fromNextLink'],
                'nextCursorId' => ['cursor' => 'cursor', 'items' => 'data', 'next' => 'nextCursorId', 'factory' => 'Pagination\\Cursor::fromNextCursorId'],
            ],
            'resources' => [
                'widgets' => [
                    'class' => 'WidgetResource',
                    'description' => 'Widgets.',
                    'methods' => [
                        'list' => ['operation' => 'WidgetsController_list', 'pagination' => 'nextLink', 'all' => 'all'],
                        'recent' => ['operation' => 'WidgetsController_recent'],
                        'get' => ['operation' => 'WidgetsController_get'],
                        'create' => ['operation' => 'WidgetsController_create', 'parameters' => ['@body' => 'widget']],
                        'update' => ['operation' => 'WidgetsController_update', 'parameters' => ['@body' => 'changes']],
                        'delete' => ['operation' => 'WidgetsController_delete'],
                        'pause' => ['operation' => 'WidgetsController_pause'],
                        'archive' => ['operation' => 'WidgetsController_archive'],
                        'ping' => ['operation' => 'WidgetsController_ping'],
                        'restore' => ['operation' => 'WidgetsController_restore', 'body' => 'empty'],
                        'addNote' => ['operation' => 'WidgetsController_addNote', 'flatten' => true],
                        'setLabels' => ['operation' => 'WidgetsController_setLabels'],
                        'alerts' => ['operation' => 'WidgetsController_alerts', 'unwrap' => 'data'],
                    ],
                ],
                'hooks' => [
                    'class' => 'HookResource',
                    'description' => 'Hooks.',
                    'methods' => [
                        'create' => ['operation' => 'HooksController_create', 'parameters' => ['@body' => 'hook']],
                    ],
                ],
                'events' => [
                    'class' => 'EventResource',
                    'description' => 'Events.',
                    'methods' => [
                        'list' => ['operation' => 'EventsController_list', 'unwrap' => 'data'],
                    ],
                ],
                'tags' => [
                    'class' => 'TagResource',
                    'description' => 'Tags.',
                    'methods' => [
                        'list' => ['operation' => 'TagsController_list', 'pagination' => 'nextCursorId', 'all' => 'all'],
                    ],
                ],
                'settings' => [
                    'class' => 'SettingsResource',
                    'description' => 'Settings.',
                    'methods' => [
                        'get' => ['operation' => 'SettingsController_get'],
                        'update' => ['operation' => 'SettingsController_update', 'parameters' => ['@body' => 'settings']],
                    ],
                ],
                'pages' => [
                    'class' => 'PageResource',
                    'description' => 'Pages.',
                    'methods' => [
                        'create' => ['operation' => 'PagesController_create', 'parameters' => ['@body' => 'page']],
                    ],
                ],
            ],
        ];
    }
}
