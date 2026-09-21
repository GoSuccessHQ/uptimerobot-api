<?php

declare(strict_types=1);

/**
 * Generator configuration of the UptimeRobot API v3.
 *
 * Every deviation from the specification is documented next to it together
 * with the evidence it is based on. "Verified live" refers to requests against
 * the API with a real account.
 *
 * Naming conventions, to be followed by every resource:
 *
 * - Client properties (resources): monitors, bulkMonitors, stormProtection,
 *   monitorGroups, incidents, incidentComments, statusPages (/psps),
 *   announcements, maintenanceWindows, alertContacts, integrations, tags, user.
 *   Resource classes are the singular noun plus "Resource", e.g.
 *   MonitorResource, StatusPageResource.
 * - Methods: list (one page) and all (lazy paginator), get, create, update,
 *   delete, plus action verbs such as pause, start, reset, pin and unpin.
 * - Models drop the Dto, Schema, RequestDto and ResponseDto suffixes and the
 *   Public prefix of the schema names (automatic, see "naming"). Request models
 *   are named <Entity>Create and <Entity>Update, e.g. StormProtectionUpdate.
 *   Enums are singular nouns prefixed with their domain, e.g.
 *   StormProtectionThresholdType.
 *
 * Keys that address a location in the specification take "Schema.property",
 * "Schema.list[]" (items), "Schema.map{}" (values), "OperationId.parameter",
 * "OperationId.body" or "OperationId.response", and "*" as a wildcard. Every
 * entry must match something the configured operations use, so obsolete
 * entries fail. Each key in short:
 *
 * - schemas:            'UpdateStormProtectionDto' => 'StormProtectionUpdate'
 *                       (also inline objects: 'SimpleTagPaginationDto.data[]' => 'Tag')
 * - properties:         'MonitorDto.checkSSLErrors' => 'checkSslErrors'  (PHP property names)
 * - enums:              'UpdateStormProtectionDto.thresholdType' => 'StormProtectionThresholdType'
 *                       (every inline enum needs a name; locations with identical
 *                       values may share one; false keeps the plain type)
 * - enumCases:          'Region' => ['na' => 'NorthAmerica', 'eu' => 'Europe', 'as' => 'Asia', 'oc' => 'Oceania']
 * - types:              'UserDto.activeSubscription.expirationDate' => ['type' => 'string', 'format' => 'date-time']
 *                       (replaces the schema at a location; fails once the specification says the same)
 * - integers / floats:  '*.id'  (every "number" must be classified)
 * - mixed:              'MonitorDto.config.apiAssertions.checks[].target'  (a location that holds any JSON value)
 * - excludedProperties: 'CreatePsPDto.logo'  (left out of the model, e.g. a file upload)
 * - nullableProperties: 'MonitorDto.url'  (sent as null against the specification)
 * - commaSeparated:     'MonitorsController_list.status'  (list<T> joined with ",")
 * - unions:             'MonitorsController_create.body' => ['interface' => 'MonitorCreate',
 *                       'discriminator' => 'type', 'variants' => ['HTTP' => 'HttpMonitorCreate', ...],
 *                       optional 'envelope' => 'data' and, for responses, 'fallback' => 'UnknownX']
 * - additions:          schemas and properties the specification lacks
 */
return [
    'title' => 'UptimeRobot API v3',
    'spec' => 'uptimerobot',
    'namespace' => 'GoSuccess\\UptimeRobot',
    'directory' => 'src',
    'client' => 'UptimeRobot',
    'baseUri' => 'https://api.uptimerobot.com/v3',
    'credential' => 'apiKey',
    'credentialDescription' => 'The main API key, or the read-only API key for read access, from the API settings of the UptimeRobot dashboard.',

    // The request DTOs come from NestJS classes ("CreateMonitorGroupDto"), the
    // response DTOs from zod schemas ("MonitorDto"); "Public" marks neither
    // direction ("PublicBulkPauseDto" is sent, "PublicSentAlertsResponseDto" read).
    'naming' => [
        'stripPrefixes' => ['Public'],
        'stripSuffixes' => ['RequestDto', 'ResponseDto', 'Dto', 'Schema'],
    ],

    'schemas' => [
        // Response models
        'UserDto.activeSubscription' => 'Subscription',
        // The specification's own name for it, in the schema description.
        'AllAlertContactDto.alertContacts[]' => 'AllAlertContactItem',
        'SimpleTagPaginationDto.data[]' => 'Tag',
        'StormProtectionSettingsResponseDto.config' => 'StormProtectionConfig',

        // Request models
        'UpdateStormProtectionDto' => 'StormProtectionUpdate',
        'UpdateStormProtectionConfigDto' => 'StormProtectionConfigUpdate',
    ],

    'properties' => [],

    'enums' => [
        // The response copy has x-enumNames (Count, Percentage), the request copy
        // derives the same names from its values.
        'StormProtectionSettingsResponseDto.thresholdType' => 'StormProtectionThresholdType',
        'UpdateStormProtectionDto.thresholdType' => 'StormProtectionThresholdType',
        'AlertContactDto.enableNotificationsFor' => 'NotificationEvent',
    ],

    'enumCases' => [],

    'types' => [
        // The response DTOs erase these types to {}. Verified live on
        // /user/alert-contacts: type is a string ("Email", "ProSms", "Voice",
        // "MobileApp"; the description announces MobileAppIOS/MobileAppAndroid),
        // status a string ("Active", "Paused", "ToMigrate"). Neither value set is
        // documented completely, so both stay strings.
        'AlertContactDto.type' => ['type' => 'string'],
        'AlertContactDto.status' => ['type' => 'string'],
        'AllAlertContactDto.alertContacts[].type' => ['type' => 'string'],
        'AllAlertContactDto.alertContacts[].status' => ['type' => 'string'],
        // Erased to {} as well. The API sends the same strings as the integration
        // DTOs define for this setting (verified live: "UpAndDown"), also for
        // personal contacts, which are created with the numbers 0-3 instead.
        'AlertContactDto.enableNotificationsFor' => ['type' => 'string', 'enum' => ['UpAndDown', 'Down', 'Up', 'None']],
        // A plain string in the specification; an ISO 8601 date in UTC
        // (verified live: "2027-08-18T12:14:17Z").
        'UserDto.activeSubscription.expirationDate' => ['type' => 'string', 'format' => 'date-time'],
    ],

    // The specification types every number of the request DTOs, and most of the
    // response DTOs, as "number".
    'integers' => [
        // IDs: JSON integers in every response (verified live for users, alert
        // contacts and tags). Incident IDs are strings and stay strings.
        '*.id',
        '*Id',
        // The ID of the last tag of the previous page.
        'TagsController_getTags.cursor',
        // Counts and limits (verified live: 9, 10 and 28; the plan's limit 10).
        'UserDto.monitorsCount',
        'UserDto.monitorLimit',
        'UserDto.smsCredits',
        'UserDto.activeSubscription.monitorLimit',
        // Minutes to wait before alerting and between repeated alerts, as whole
        // numbers (verified live: 0).
        'AllAlertContactDto.alertContacts[].threshold',
        'AllAlertContactDto.alertContacts[].recurrence',
        // The response types both as integer (StormProtectionSettingsResponseDto).
        'UpdateStormProtectionDto.thresholdValue',
        'UpdateStormProtectionDto.windowMinutes',
    ],

    'floats' => [],

    'mixed' => [],

    'excludedProperties' => [],

    'nullableProperties' => [],

    'commaSeparated' => [],

    'unions' => [],

    'extraModels' => [],

    'additions' => [
        'schemas' => [],
        'properties' => [],
    ],

    'pagination' => [
        // {"data": [...], "nextLink": "https://api.uptimerobot.com/v3/monitors/?limit=1&cursor=803767164"}:
        // an absolute URI that repeats the filters; its cursor is the ID of the
        // last item, and nextLink is null on the last page (verified live).
        'nextLink' => [
            'cursor' => 'cursor',
            'size' => 'limit',
            'items' => 'data',
            'factory' => 'Pagination\\Cursor::fromNextLink',
        ],
        // {"data": [...], "nextCursorId": 42}; only GET /tags (verified live:
        // {"data":[],"nextCursorId":null}).
        'nextCursorId' => [
            'cursor' => 'cursor',
            'items' => 'data',
            'factory' => 'Pagination\\Cursor::fromNextCursorId',
        ],
    ],

    'resources' => [
        'user' => [
            'class' => 'UserResource',
            'description' => 'The account the API key belongs to: its plan and its alert contacts.',
            'methods' => [
                'me' => ['operation' => 'UserController_getMe'],
                // A bare array, not a page (verified live).
                'alertContacts' => ['operation' => 'UserController_getAlertContacts'],
                'allAlertContacts' => ['operation' => 'UserController_getAllAlertContacts'],
            ],
        ],
        'tags' => [
            'class' => 'TagResource',
            'description' => 'Tags of monitors. They are created by naming them on a monitor.',
            'methods' => [
                'list' => ['operation' => 'TagsController_getTags', 'pagination' => 'nextCursorId', 'all' => 'all'],
                'delete' => ['operation' => 'TagsController_deleteTag'],
            ],
        ],
        'stormProtection' => [
            'class' => 'StormProtectionResource',
            'description' => 'Storm protection: the account-wide grouping of alerts when many monitors go down at once.',
            'methods' => [
                'get' => ['operation' => 'StormProtectionController_get'],
                'update' => ['operation' => 'StormProtectionController_update', 'parameters' => ['@body' => 'changes']],
            ],
        ],
    ],

    'ignored' => [
        'BulkMonitorsController_bulkPause' => 'Pending: implemented resource by resource in the following commits.',
        'BulkMonitorsController_bulkStart' => 'Pending: implemented resource by resource in the following commits.',
        'BulkMonitorsController_bulkUpdate' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_listComments' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_createComment' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_getActivityLog' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_getAlerts' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_updateComment' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_deleteComment' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorGroupsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorGroupsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorGroupsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorGroupsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorGroupsController_delete' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_getUptimeStats' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_getMonitorUptimeStats' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_getMonitorResponseTimeStatsByRegion' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_getMonitorResponseTimeStats' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_delete' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_reset' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_pause' => 'Pending: implemented resource by resource in the following commits.',
        'MonitorsController_start' => 'Pending: implemented resource by resource in the following commits.',
        'PspController_list' => 'Pending: implemented resource by resource in the following commits.',
        'PspController_create' => 'Pending: implemented resource by resource in the following commits.',
        'PspController_get' => 'Pending: implemented resource by resource in the following commits.',
        'PspController_update' => 'Pending: implemented resource by resource in the following commits.',
        'PspController_delete' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_pin' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_unpin' => 'Pending: implemented resource by resource in the following commits.',
        'MaintenanceWindowsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'MaintenanceWindowsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'MaintenanceWindowsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'MaintenanceWindowsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'MaintenanceWindowsController_delete' => 'Pending: implemented resource by resource in the following commits.',
        'IntegrationsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'IntegrationsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'IntegrationsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'IntegrationsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'IntegrationsController_delete' => 'Pending: implemented resource by resource in the following commits.',
        'AlertContactsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'AlertContactsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'AlertContactsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'AlertContactsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'AlertContactsController_delete' => 'Pending: implemented resource by resource in the following commits.',
    ],
];
