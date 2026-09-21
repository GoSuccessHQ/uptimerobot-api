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
 *                       (replaces the schema at a location, but keeps its description, deprecation,
 *                       nullable and readOnly unless the entry states them, e.g. 'nullable' => false;
 *                       fails once the specification says the same; ['$ref' => '#/components/schemas/X']
 *                       types an inline copy of a component through the component)
 * - integers / floats:  '*.id'  (every "number" must be classified)
 * - mixed:              'PublicApiAssertionCheckDto.target'  (a location that holds any JSON value)
 * - excludedProperties: 'CreatePsPDto.logo'  (left out of the model, e.g. a file upload)
 * - nullableProperties: 'MonitorDto.url'  (read as nullable against the specification: sent as
 *                       null, or left out where 0 or an empty object would misstate it)
 * - commaSeparated:     'MonitorsController_list.status'  (list<T> joined with ",")
 * - unions:             'MonitorsController_create.body' => ['interface' => 'MonitorCreate',
 *                       'discriminator' => 'type', 'variants' => ['HTTP' => 'HttpMonitorCreate', ...],
 *                       optional 'envelope' => 'data' and, for responses, 'fallback' => 'UnknownX']
 * - additions:          schemas and properties the specification lacks
 * - extraModels:        'UptimeStatsDto'  (read by hand-written methods)
 * - extraRequestModels: 'PublicBulkUpdateDto'  (sent by hand-written methods)
 */

// Value sets that several locations share; an enum shared by locations needs
// the same values in the same order.
$monitorTypes = ['HTTP', 'KEYWORD', 'PING', 'PORT', 'HEARTBEAT', 'DNS', 'API', 'UDP', 'VISUAL_COMPARISON'];
// The values the validator of the list filter names, in its order.
$monitorStatuses = ['PAUSED', 'STARTED', 'UP', 'LOOKS_DOWN', 'DOWN'];
$monitorStatus = 'The status of the monitor: UP, DOWN, LOOKS_DOWN, PAUSED, or STARTED, which a new or restarted monitor reports until its first check (verified live).';
$httpMethods = ['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'QUERY'];
$date = ['type' => 'string', 'format' => 'date-time'];

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

        // Monitors: response models
        'MonitorDto.config' => 'MonitorConfig',
        'MonitorDto.config.visualComparison' => 'MonitorVisualComparison',
        'MonitorDto.tags[]' => 'MonitorTag',
        'MonitorDto.tags[].monitors[]' => 'TaggedMonitor',
        'MonitorDto.lastIncident' => 'LastIncident',
        'MonitorDto.lastDayUptimes' => 'UptimeHistogram',
        'MonitorDto.lastDayUptimes.histogram[]' => 'UptimeHistogramBucket',
        'UptimeStatsDto.logs[]' => 'UptimeLogEntry',
        'MonitorResponseTimeStatsDto' => 'ResponseTimeStats',
        'MonitorResponseTimeStatsDto.summary' => 'ResponseTimeSummary',
        'MonitorResponseTimeStatsDto.time_series[]' => 'ResponseTimeDataPoint',
        'MonitorResponseTimeStatsByRegionDto' => 'RegionalResponseTimeStats',
        'BulkOperationResponseDto' => 'BulkOperationResult',
        'BulkOperationResponseDto.results[]' => 'BulkOperationItem',
        // The status pages and maintenance windows of a monitor (see 'types').
        'PspDto' => 'StatusPage',
        'MaintenanceWindowDto' => 'MaintenanceWindow',

        // Monitors: models that are sent and read (see 'types')
        'RegionalDataDto' => 'RegionalData',
        'AlertContactSettings' => 'AssignedAlertContact',
        'UdpConfigFieldsDto' => 'UdpSettings',
        'VisualComparisonAreaCoordinatesDto' => 'VisualComparisonArea',

        // Request models; the variants of MonitorCreate are named in 'unions'.
        'UpdateStormProtectionDto' => 'StormProtectionUpdate',
        'UpdateStormProtectionConfigDto' => 'StormProtectionConfigUpdate',
        'UpdateMonitorDto' => 'MonitorUpdate',
        'UpdateMonitorConfigDto' => 'MonitorConfigUpdate',
        // Used by HTTP and keyword monitors.
        'HttpKeywordMonitorConfigDto' => 'HttpMonitorConfig',
        'VisualComparisonFieldsDto' => 'VisualComparisonSettings',
        // Without its selection, which BulkMonitorResource::update() takes.
        'PublicBulkUpdateDto' => 'BulkMonitorUpdate',

        // Monitor groups; MonitorGroupDto is MonitorGroup.
        'CreateMonitorGroupDto' => 'MonitorGroupCreate',
        'UpdateMonitorGroupDto' => 'MonitorGroupUpdate',
    ],

    'properties' => [
        // The casing of other acronyms in property names, e.g. ipv4Only.
        'MonitorDto.checkSSLErrors' => 'checkSslErrors',
        'CreateHttpMonitorDto.checkSSLErrors' => 'checkSslErrors',
        'CreateKeywordMonitorDto.checkSSLErrors' => 'checkSslErrors',
        'CreateApiMonitorDto.checkSSLErrors' => 'checkSslErrors',
        'UpdateMonitorDto.checkSSLErrors' => 'checkSslErrors',
        'PublicBulkUpdateDto.checkSSLErrors' => 'checkSslErrors',
        'PspDto.customSettings.features.showMonitorURL' => 'showMonitorUrl',
    ],

    'enums' => [
        // The response copy has x-enumNames (Count, Percentage), the request copy
        // derives the same names from its values.
        'StormProtectionSettingsResponseDto.thresholdType' => 'StormProtectionThresholdType',
        'UpdateStormProtectionDto.thresholdType' => 'StormProtectionThresholdType',
        'AlertContactDto.enableNotificationsFor' => 'NotificationEvent',
        // Monitors. The locations of MonitorDto that the specification erases get
        // their values in 'types'.
        'MonitorDto.type' => 'MonitorType',
        'UpdateMonitorDto.type' => 'MonitorType',
        'MonitorDto.status' => 'MonitorStatus',
        'MonitorDto.tags[].monitors[].status' => 'MonitorStatus',
        'MonitorsController_list.status[]' => 'MonitorStatus',
        'MonitorDto.authType' => 'HttpAuthType',
        'Create*MonitorDto.authType' => 'HttpAuthType',
        'UpdateMonitorDto.authType' => 'HttpAuthType',
        'MonitorDto.httpMethodType' => 'HttpMethod',
        'Create*MonitorDto.httpMethodType' => 'HttpMethod',
        'UpdateMonitorDto.httpMethodType' => 'HttpMethod',
        'MonitorDto.keywordType' => 'KeywordType',
        'CreateKeywordMonitorDto.keywordType' => 'KeywordType',
        'UpdateMonitorDto.keywordType' => 'KeywordType',
        'MonitorDto.keywordCaseType' => 'KeywordCaseType',
        'CreateKeywordMonitorDto.keywordCaseType' => 'KeywordCaseType',
        'UpdateMonitorDto.keywordCaseType' => 'KeywordCaseType',
        'MonitorDto.postValueType' => 'PostValueType',
        'Create*MonitorDto.postValueType' => 'PostValueType',
        'UpdateMonitorDto.postValueType' => 'PostValueType',
        '*.ipVersion' => 'IpVersion',
        'RegionalDataDto.REGION[]' => 'Region',
        'RegionalDataDto.INFRASTRUCTURE' => 'RegionInfrastructure',
        'PublicApiAssertionsDto.logic' => 'AssertionLogic',
        'PublicApiAssertionCheckDto.comparison' => 'AssertionComparison',
        'VisualComparisonFieldsDto.viewport' => 'VisualComparisonViewport',
        'MonitorDto.config.visualComparison.viewport' => 'VisualComparisonViewport',
        'MonitorsController_getUptimeStats.timeFrame' => 'UptimeTimeFrame',
        'UptimeStatsDto.logs[].type' => 'UptimeLogType',
        'BulkOperationResponseDto.results[].status' => 'BulkOperationStatus',
        // The region filter adds "all" to the four regions.
        'MonitorsController_getMonitorResponseTimeStats.region' => 'ResponseTimeRegion',
        'MaintenanceWindowDto.interval' => 'MaintenanceWindowInterval',
        'MaintenanceWindowDto.status' => 'MaintenanceWindowStatus',
        // Lower case here, title case in the request DTOs of status pages
        // ("Light", "Normal"), and not verifiable live (the account has no status
        // pages): an enum could read a value the API actually sends as null.
        'PspDto.customSettings.page.theme' => false,
        'PspDto.customSettings.page.density' => false,
    ],

    'enumCases' => [
        // The numbers of the response and of PATCH; see 'types'.
        'KeywordCaseType' => [0 => 'CaseSensitive', 1 => 'CaseInsensitive'],
        // The x-enumNames of the response copy (MonitorDto.regionalData.REGION).
        'Region' => ['na' => 'NorthAmerica', 'eu' => 'Europe', 'as' => 'Asia', 'oc' => 'Oceania'],
        'ResponseTimeRegion' => ['na' => 'NorthAmerica', 'eu' => 'Europe', 'as' => 'Asia', 'oc' => 'Oceania', 'all' => 'All'],
    ],

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

        // Monitors. The items of a page are an inline copy of MonitorDto
        // (identical, and identical to GET /monitors/{id} live).
        'MonitorPaginationDto.data[]' => ['$ref' => '#/components/schemas/MonitorDto'],
        // MonitorDto erases these enums to {}. The API sends them in
        // upper case (verified live) with the values of the request DTOs; for
        // status, the values the list filter's validator names ("Allowed values:
        // PAUSED, STARTED, UP, LOOKS_DOWN, DOWN"), of which UP, STARTED and
        // PAUSED were seen live.
        'MonitorDto.type' => ['type' => 'string', 'enum' => $monitorTypes],
        'MonitorDto.status' => ['type' => 'string', 'enum' => $monitorStatuses, 'description' => $monitorStatus],
        'MonitorDto.tags[].monitors[].status' => ['type' => 'string', 'enum' => $monitorStatuses],
        // New monitors report HTTP_BASIC with empty credentials when created
        // without authType, whatever their type (verified live for HTTP, KEYWORD,
        // PING, PORT, DNS, API and HEARTBEAT).
        'MonitorDto.authType' => [
            'type' => 'string',
            'enum' => ['NONE', 'HTTP_BASIC', 'DIGEST', 'BEARER'],
            'description' => 'The authentication of the check. Monitors created without one report HTTP_BASIC with empty credentials, whatever their type (verified live).',
        ],
        // Null for HTTP and keyword monitors created without it (verified live).
        'MonitorDto.httpMethodType' => [
            'type' => 'string',
            'enum' => $httpMethods,
            'description' => 'The HTTP method of the check; null for HTTP and keyword monitors created without one, which use HEAD then (the documented default).',
        ],
        'MonitorDto.keywordType' => [
            'type' => 'string',
            'enum' => ['ALERT_EXISTS', 'ALERT_NOT_EXISTS'],
            'description' => 'Whether to alert when the keyword exists or when it does not; null on monitors of other types (verified live).',
        ],
        // oneOf [0..0, 1..1] here, numbers in UpdateMonitorDto, names in
        // CreateKeywordMonitorDto. A keyword monitor created with "CaseSensitive"
        // reads 0; PATCH with 1 or "CaseInsensitive" reads 1, with 0 or
        // "CaseSensitive" 0 (verified live). The create validator takes the
        // numbers too (verified live: 1 passes, 2 fails with "keywordCaseType must
        // be one of the following values: CaseSensitive,CaseInsensitive"), so one
        // int enum serves create, update and response.
        'MonitorDto.keywordCaseType' => [
            'type' => 'integer',
            'enum' => [0, 1],
            'description' => 'Whether the keyword match is case sensitive; CaseSensitive on monitors of other types (verified live).',
        ],
        'CreateKeywordMonitorDto.keywordCaseType' => ['type' => 'number', 'enum' => [0, 1]],
        'MonitorDto.keywordValue' => ['type' => 'string', 'description' => 'The keyword to look for; empty on monitors of other types (verified live).'],
        // A oneOf of string, number, object and array; the API returns an object or
        // null, also after a JSON string was written (verified live).
        'MonitorDto.postValueData' => ['type' => 'object', 'description' => 'The body the check sends, as the decoded JSON object, or null.'],
        // Values are strings or numbers; header values are text.
        'MonitorDto.customHttpHeaders' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
            'description' => 'The headers the check sends; numbers are read as strings.',
        ],
        'MonitorDto.httpUsername' => ['type' => 'string', 'description' => 'The user name of the authentication; empty if none is set (verified live).'],
        'MonitorDto.httpPassword' => [
            'type' => 'string',
            'description' => 'The password of the authentication, in plain text (verified live); empty if none is set. Treat monitor data as secret.',
        ],
        'MonitorDto.url' => [
            'type' => 'string',
            'description' => 'The URL, host name or IP address that is checked; for heartbeat monitors, the token of the URL the heartbeats are sent to (verified live).',
        ],
        'MonitorDto.interval' => ['type' => 'number', 'description' => 'Seconds between two checks.'],
        'MonitorDto.responseTimeThreshold' => ['type' => 'number', 'description' => 'Always 0; the thresholds are in regionalData (verified live).'],
        'MonitorDto.currentStateDuration' => ['type' => 'number', 'description' => 'Seconds since the status last changed; 0 for a new monitor.'],
        'MonitorDto.lastIncidentId' => [
            'type' => 'string',
            'description' => 'The ID of the latest incident, as a string: incident IDs exceed the integers JSON numbers hold exactly (verified live: "352577094135060139").',
        ],
        'MonitorDto.groupId' => ['type' => 'number', 'description' => 'The monitor group; 0 if the monitor is in none (verified live).'],
        // zod's Date | string: ISO 8601 in UTC (verified live: "2026-09-21T09:05:10.000Z").
        'MonitorDto.createDateTime' => $date,
        'MonitorDto.sslExpiryDateTime' => $date,
        'MonitorDto.domainExpireDate' => $date,
        'MonitorDto.lastIncident.startedAt' => $date,
        // Erased; "Resolved" is the only value seen, and none are documented.
        'MonitorDto.lastIncident.status' => ['type' => 'string'],
        // Copies of components (structurally identical apart from bounds and
        // descriptions), typed through them so that the models are shared with
        // the requests and their configuration applies in one place.
        'MonitorDto.config.dnsRecords' => ['$ref' => '#/components/schemas/DnsRecordsDto'],
        'MonitorDto.config.udp' => ['$ref' => '#/components/schemas/UdpConfigFieldsDto'],
        'MonitorDto.config.visualComparison.areaCoordinates' => ['$ref' => '#/components/schemas/VisualComparisonAreaCoordinatesDto'],
        'MonitorDto.assignedAlertContacts[]' => ['$ref' => '#/components/schemas/AlertContactSettings'],
        'MonitorDto.maintenanceWindows[]' => ['$ref' => '#/components/schemas/MaintenanceWindowDto'],
        'MonitorDto.psps[]' => ['$ref' => '#/components/schemas/PspDto'],
        // oneOf [the request's PublicApiAssertionsDto plus checks[].id and index,
        // {}]. The API returns the assertions as they were sent, without id and
        // index (verified live: {"logic":"AND","checks":[{"property":"status_code",
        // "comparison":"equals","target":200}]}).
        'MonitorDto.config.apiAssertions' => ['$ref' => '#/components/schemas/PublicApiAssertionsDto'],
        // The request's RegionalDataDto plus INFRASTRUCTURE (see 'additions'),
        // with THRESHOLD as a map (below). MANUAL_SELECTED and THRESHOLD are only
        // present once the regions were chosen (verified live).
        'MonitorDto.regionalData' => ['$ref' => '#/components/schemas/RegionalDataDto', 'description' => 'The regions the checks run from.'],
        'RegionalDataDto.REGION' => [
            'type' => 'array',
            'items' => ['type' => 'string', 'enum' => ['na', 'eu', 'as', 'oc']],
            'description' => 'The regions the checks run from; at least one.',
        ],
        // Absent until the regions are set with regionData, which makes it true
        // (verified live).
        'RegionalDataDto.MANUAL_SELECTED' => ['type' => 'boolean', 'description' => 'Whether the regions were chosen rather than assigned by default.'],
        // Fixed keys na, eu, as and oc in the request DTO, a map in the response;
        // whole milliseconds (verified live: {"eu": 2000}).
        'RegionalDataDto.THRESHOLD' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'number'],
            'description' => 'Response time thresholds in milliseconds by region code (na, eu, as, oc).',
        ],
        // Inline copies of MonitorResponseTimeStatsDto; null for the regions the
        // monitor is not checked from (verified live: {"na":null,"eu":{...}}).
        'MonitorResponseTimeStatsByRegionDto.na' => ['$ref' => '#/components/schemas/MonitorResponseTimeStatsDto', 'nullable' => true],
        'MonitorResponseTimeStatsByRegionDto.eu' => ['$ref' => '#/components/schemas/MonitorResponseTimeStatsDto', 'nullable' => true],
        'MonitorResponseTimeStatsByRegionDto.as' => ['$ref' => '#/components/schemas/MonitorResponseTimeStatsDto', 'nullable' => true],
        'MonitorResponseTimeStatsByRegionDto.oc' => ['$ref' => '#/components/schemas/MonitorResponseTimeStatsDto', 'nullable' => true],
        'MonitorResponseTimeStatsByRegionDto.all' => ['$ref' => '#/components/schemas/MonitorResponseTimeStatsDto', 'nullable' => true],
        // Status pages and maintenance windows, as monitors embed them. Always
        // empty on this account, so nothing here is verified live.
        'MaintenanceWindowDto.created' => $date,
        // Erased; PAUSED and ENABLED are documented for requests only.
        'PspDto.status' => ['type' => 'string'],
        // The strings "true" and "false" (booleans in the request DTOs), which a
        // bool reads as well.
        'PspDto.customSettings.features.*' => ['type' => 'boolean'],

        // Monitor lists: status and tags are comma-separated lists in a plain
        // string parameter, of which any value matches (verified live).
        'MonitorsController_list.status' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $monitorStatuses, 'description' => $monitorStatus]],
        'MonitorsController_list.tags' => ['type' => 'array', 'items' => ['type' => 'string']],

        // Monitor statistics. The query parameters from and to take ISO 8601
        // ("from must be a Date instance" otherwise, verified live).
        'MonitorsController_getMonitor*.from' => $date,
        'MonitorsController_getMonitor*.to' => $date,
        // ISO 8601 in UTC in the responses (verified live: "2026-09-20T08:58:51.465Z").
        'Monitor*Stats*Dto.from' => $date,
        'Monitor*Stats*Dto.to' => $date,
        'MonitorResponseTimeStatsDto.time_series[].timestamp' => $date,
        'UptimeStatsDto.logs[].datetime' => $date,
        // The two uptimes have different scales, which the specification does not
        // say (verified live: 0.99697 and 1 against 92.54 and 100).
        'UptimeStatsDto.overallUptime' => [
            'type' => 'number',
            'description' => 'The uptime of all monitors as a fraction from 0 to 1, e.g. 0.9969, unlike the percentage of MonitorResource::uptime() (verified live).',
        ],
        'MonitorUptimeStatsDto.uptime' => [
            'type' => 'number',
            'description' => 'The uptime as a percentage from 0 to 100, e.g. 99.95, unlike the fraction of MonitorResource::uptimeStats() (verified live).',
        ],
        'UptimeStatsDto.totalTimeWithoutIncidents' => ['type' => 'number', 'description' => 'Seconds without incidents; beyond 2^31 for the time frame ALL (verified live).'],
        'UptimeStatsDto.mtbf' => ['type' => 'number', 'description' => 'The mean time between failures in seconds; null without incidents (verified live).'],
        'UptimeStatsDto.logs[].duration' => ['type' => 'number', 'description' => 'Seconds.'],
        'MonitorResponseTimeStatsDto.data_points' => [
            'type' => 'number',
            'description' => 'The number of time series points the statistics are based on, not of checks (verified live).',
        ],

        // Monitor updates, verified live on own monitors.
        'UpdateMonitorDto.config' => ['$ref' => '#/components/schemas/UpdateMonitorConfigDto', 'nullable' => true],
        'UpdateMonitorDto.customHttpHeaders' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
            'description' => 'The headers the check sends. Replaces all current headers; an empty array removes them (verified live). The serialized JSON must not exceed 3000 characters.',
        ],
        'UpdateMonitorDto.customFields' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
            'description' => 'Custom key-value metadata. Replaces all current fields (verified live). Max 20 keys. Keys: alphanumeric + underscore + hyphen, max 64 chars. Values: max 255 chars. Paid plans only.',
        ],
        'UpdateMonitorDto.successHttpResponseCodes' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'The HTTP status codes or ranges such as "2xx" that count as up. Replaces the current ones; an empty list resets them to ["2xx", "3xx"] (verified live).',
        ],
        // Nullable in the specification, but null is rejected: 400
        // "keywordValue should not be empty" (verified live).
        'UpdateMonitorDto.keywordValue' => ['type' => 'string', 'nullable' => false, 'description' => 'The keyword to look for (keyword monitors); null is rejected.'],
        // "type: object", while the description says "Can be JSON string or
        // string"; a JSON string is stored as the object it encodes (verified live).
        'UpdateMonitorDto.postValueData' => [
            'oneOf' => [['type' => 'string'], ['type' => 'object']],
            'description' => 'The body the check sends: an array, sent as a JSON object, or a JSON string; the API stores either as an object (verified live). Not applicable for the HTTP method HEAD.',
        ],
        'UpdateMonitorDto.responseTimeThreshold' => [
            'type' => 'number',
            'description' => 'Response time threshold in milliseconds, stored in regionalData THRESHOLD; the property itself keeps reading 0 (verified live).',
        ],
        // oneOf [string, object]; any JSON value in PHP.
        'Create*MonitorDto.postValueData' => [
            'oneOf' => [['type' => 'string'], ['type' => 'object']],
            'description' => 'The body the check sends: an array, sent as a JSON object, or a JSON string; the API stores either as an object (verified live). Not applicable for the HTTP method HEAD.',
        ],
        // A bare object in the bulk update; the values are strings as for a
        // single monitor.
        'PublicBulkUpdateDto.customFields' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
            'description' => 'Custom key-value metadata to set on the selected monitors; a single monitor\'s update replaces all its fields (verified live), the bulk update presumably too (not verified). Max 20 keys. Keys: alphanumeric + underscore + hyphen, max 64 chars. Values: max 255 chars. Paid plans only.',
        ],
        // The API monitor enum lacks HEAD, which its description forbids; one
        // enum for all monitor types.
        'CreateApiMonitorDto.httpMethodType' => ['type' => 'string', 'enum' => $httpMethods],

        // Monitor groups. The items of a page are an inline copy of
        // MonitorGroupDto (identical, and identical to GET /monitor-groups/{id}
        // live). A group carries neither its monitors nor their number (verified
        // live); the monitors name it in groupId.
        'MonitorGroupPaginationDto.data[]' => ['$ref' => '#/components/schemas/MonitorGroupDto'],
        // GET /monitor-groups/0 answers 404 "Monitor group not found" (verified
        // live), although the specification calls 0 the default group.
        'MonitorGroupDto.id' => [
            'type' => 'number',
            'description' => 'The ID, which the monitors in the group report as groupId; the monitors in no group report 0, for which there is no group to read (verified live).',
        ],
        // zod's Date | string: ISO 8601 in UTC (verified live: "2026-08-18T12:57:15.000Z").
        'MonitorGroupDto.createdAt' => $date,
        'MonitorGroupDto.updatedAt' => $date,
        // A monitor has one groupId, so assigning it moves it out of its group.
        'CreateMonitorGroupDto.monitorIds' => [
            'type' => 'array',
            'items' => ['type' => 'number'],
            'description' => 'The monitors to put into the group. A monitor is in one group at most (its groupId), so they leave the group they are in.',
        ],
        // Items from 0 (verified live: -1 is rejected with "each value in groupIds
        // must not be less than 0"); 0 is the specification's default group, the
        // one the monitors in no group report.
        'CreateMonitorGroupDto.groupIds' => [
            'type' => 'array',
            'items' => ['type' => 'number'],
            'description' => 'Groups whose monitors are moved into the new group; 0 stands for the monitors in no group, which the specification calls the default group.',
        ],
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
        // Lists of IDs.
        '*Ids[]',
        // Monitor settings in seconds, milliseconds, minutes, days and ports,
        // whole numbers in every response (verified live, e.g. interval 300,
        // timeout 30, port 443, gracePeriod 300, THRESHOLD {"eu": 2000},
        // assignedAlertContacts threshold 0, sslExpirationPeriodDays [7,14]).
        '*.interval',
        '*.timeout',
        '*.port',
        '*.gracePeriod',
        '*.responseTimeThreshold',
        'RegionalDataDto.THRESHOLD{}',
        'AlertContactSettings.threshold',
        'AlertContactSettings.recurrence',
        '*.sslExpirationPeriodDays[]',
        '*.applicationErrorRetries',
        // 0-100; an integer in the response copy.
        'VisualComparisonFieldsDto.sensitivityThreshold',
        // Seconds, counts and codes (verified live: 1852819, 333333, 64, 0).
        'MonitorDto.currentStateDuration',
        'MonitorDto.lastIncident.cause',
        'MonitorDto.lastIncident.duration',
        'MonitorDto.lastDayUptimes.bucketSize',
        // Never populated live; presumably a Unix timestamp.
        'MonitorDto.lastDayUptimes.histogram[].timestamp',
        // Minutes, days of the week or month, and a count (not observable).
        'MaintenanceWindowDto.duration',
        'MaintenanceWindowDto.days[]',
        'PspDto.monitorsCount',
        // Statistics: seconds, counts and milliseconds (verified live, e.g.
        // totalTimeWithoutIncidents 3155579985, summary {"min":79,"max":2080,
        // "avg":166}, time_series value 134).
        'UptimeStatsDto.totalIncidents',
        'UptimeStatsDto.totalTimeWithoutIncidents',
        'UptimeStatsDto.affectedMonitors',
        'UptimeStatsDto.mtbf',
        'UptimeStatsDto.logs[].duration',
        'MonitorUptimeStatsDto.total_downtime_seconds',
        'MonitorUptimeStatsDto.incident_count',
        'MonitorUptimeStatsDto.mtbf',
        'MonitorResponseTimeStatsDto.summary.*',
        'MonitorResponseTimeStatsDto.data_points',
        'MonitorResponseTimeStatsDto.time_series[].value',
        // Query parameters: the page size, the ID of the last monitor of the
        // previous page, the number of log entries and Unix seconds.
        'MonitorsController_list.limit',
        'MonitorsController_list.cursor',
        'MonitorsController_getUptimeStats.logLimit',
        'MonitorsController_getUptimeStats.start',
        'MonitorsController_getUptimeStats.end',
        // The ID of the last group of the previous page (verified live: cursor=29454
        // returned the groups after 29454).
        'MonitorGroupsController_list.cursor',
        // Counts of the bulk operations.
        'BulkOperationResponseDto.totalSuccess',
        'BulkOperationResponseDto.totalError',
    ],

    'floats' => [
        // Fractions and percentages (verified live: 0.9905905723745785 and
        // 92.54237891737893).
        'UptimeStatsDto.overallUptime',
        'MonitorUptimeStatsDto.uptime',
        'MonitorDto.lastDayUptimes.histogram[].uptime',
        // Percentages (not verified live).
        'UdpConfigFieldsDto.packetLossThreshold',
        'VisualComparisonAreaCoordinatesDto.*',
    ],

    'mixed' => [
        // Any JSON value to compare with; its type is kept (verified live: 200).
        'PublicApiAssertionCheckDto.target',
        // A JSON object (as an array) or a JSON string.
        'Create*MonitorDto.postValueData',
        'UpdateMonitorDto.postValueData',
    ],

    'excludedProperties' => [
        // The deprecated region string; regionData, which takes priority, sets
        // the regions and their thresholds.
        'Create*MonitorDto.regionalData',
        'UpdateMonitorDto.regionalData',
        // The selection of the monitors to change: parameters of
        // BulkMonitorResource::update(), which checks that one is given.
        'PublicBulkUpdateDto.groupId',
        'PublicBulkUpdateDto.tagId',
    ],

    'nullableProperties' => [
        // The keys of a monitor's config are absent unless set (verified live:
        // null, {} or e.g. {"sslExpirationPeriodDays":[7,14]} after ipVersion was
        // removed); null tells "not set" apart from an empty object or 0.
        'MonitorDto.config.dnsRecords',
        'MonitorDto.config.apiAssertions',
        'MonitorDto.config.udp',
        'MonitorDto.config.visualComparison',
        'MonitorDto.config.applicationErrorRetries',
        'MonitorDto.config.visualComparison.areaCoordinates',
        // Never present (verified live), which 0 would misstate.
        'MonitorDto.lastDayUptimes.totalChanges',
        // Optional, presumably only present for a monitor that failed (not
        // verified live: bulk operations would change the account's monitors).
        'BulkOperationResponseDto.results[].error',
        'BulkOperationResponseDto.results[].code',
    ],

    'commaSeparated' => [
        'MonitorsController_list.status',
        'MonitorsController_list.tags',
    ],

    'unions' => [
        // A discriminator with a mapping. The API matches the type case-insensitively
        // (verified live: "http" creates an HTTP monitor); the variants send it in
        // upper case.
        'MonitorsController_create.body' => [
            'interface' => 'MonitorCreate',
            'discriminator' => 'type',
            'variants' => [
                'HTTP' => 'HttpMonitorCreate',
                'KEYWORD' => 'KeywordMonitorCreate',
                'PING' => 'PingMonitorCreate',
                'PORT' => 'PortMonitorCreate',
                'HEARTBEAT' => 'HeartbeatMonitorCreate',
                'DNS' => 'DnsMonitorCreate',
                'API' => 'ApiMonitorCreate',
                'UDP' => 'UdpMonitorCreate',
                'VISUAL_COMPARISON' => 'VisualComparisonMonitorCreate',
            ],
            'description' => 'A monitor to create: one model per monitor type, each with the fields that type requires.',
        ],
    ],

    'extraModels' => [
        // MonitorResource::uptimeStats() takes dates for the Unix seconds.
        'UptimeStatsDto',
        // BulkMonitorResource checks the selection, which the specification
        // requires in prose only.
        'BulkOperationResponseDto',
    ],

    'extraRequestModels' => [
        'PublicBulkUpdateDto',
    ],

    'additions' => [
        'schemas' => [
            // UpdateMonitorDto.config is a bare object that the API merges into
            // the current config key by key: a key left out is kept, a key set
            // to null is removed, and "config": null clears all of them; an API
            // monitor's apiAssertions are replaced as a whole (all verified
            // live). The keys are those of the config DTOs of the monitor types.
            'UpdateMonitorConfigDto' => [
                'type' => 'object',
                'description' => 'Changes to the settings of a monitor type, merged into the current ones key by key: a key left out is kept, a key set to null is removed (verified live).',
                'properties' => [
                    'sslExpirationPeriodDays' => [
                        'type' => 'array',
                        'items' => ['type' => 'integer'],
                        'nullable' => true,
                        'description' => 'Days before the SSL certificate expires on which to send a reminder (0-365, at most 10 items). HTTP, keyword and API monitors.',
                    ],
                    'ipVersion' => [
                        'type' => 'string',
                        'enum' => ['ipv4Only', 'ipv6Only'],
                        'nullable' => true,
                        'description' => 'IP version preference; null restores the default behavior (IPv4 priority, falls back to IPv6).',
                    ],
                    'applicationErrorRetries' => [
                        'type' => 'integer',
                        'nullable' => true,
                        'description' => 'Number of retries on application errors (HTTP 4xx/5xx), 0-3. HTTP, keyword and API monitors.',
                    ],
                    'dnsRecords' => ['$ref' => '#/components/schemas/DnsRecordsDto', 'nullable' => true, 'description' => 'The expected DNS records of a DNS monitor.'],
                    'apiAssertions' => [
                        '$ref' => '#/components/schemas/PublicApiAssertionsDto',
                        'nullable' => true,
                        'description' => 'The assertions of an API monitor; replaces the current ones as a whole (verified live).',
                    ],
                    'udp' => ['$ref' => '#/components/schemas/UdpConfigFieldsDto', 'nullable' => true, 'description' => 'The settings of a UDP monitor.'],
                    'visualComparison' => [
                        '$ref' => '#/components/schemas/VisualComparisonFieldsDto',
                        'nullable' => true,
                        'description' => 'The settings of a visual comparison monitor.',
                    ],
                ],
            ],
        ],
        'properties' => [
            // Only in the response copy of RegionalDataDto (MonitorDto.regionalData),
            // which the request and the response share (see 'types').
            'RegionalDataDto.INFRASTRUCTURE' => [
                'type' => 'string',
                'enum' => ['Legacy', 'New'],
                'readOnly' => true,
                'description' => 'The checking infrastructure, set by the API (verified live: New).',
            ],
        ],
    ],

    'pagination' => [
        // {"data": [...], "nextLink": "https://api.uptimerobot.com/v3/monitors/?limit=1&cursor=803767164"}:
        // an absolute URI that repeats the filters; its cursor is the ID of the
        // last item, and nextLink is null on the last page (verified live).
        'nextLink' => [
            'cursor' => 'cursor',
            'size' => 'limit',
            'items' => 'data',
            'next' => 'nextLink',
            'factory' => 'Pagination\\Cursor::fromNextLink',
        ],
        // GET /monitors: like nextLink, whose key is absent on the last page there
        // (verified live), but all() requests pages of 200 monitors, the most the
        // API accepts ("Limit must be between 1 and 200", verified live), instead
        // of the default 50, which saves requests against the rate limit.
        'monitorPages' => [
            'cursor' => 'cursor',
            'size' => 'limit',
            'allSize' => 200,
            'items' => 'data',
            'next' => 'nextLink',
            'factory' => 'Pagination\\Cursor::fromNextLink',
        ],
        // {"data": [...], "nextCursorId": 42}; only GET /tags (verified live:
        // {"data":[],"nextCursorId":null}).
        'nextCursorId' => [
            'cursor' => 'cursor',
            'items' => 'data',
            'next' => 'nextCursorId',
            'factory' => 'Pagination\\Cursor::fromNextCursorId',
        ],
    ],

    'resources' => [
        'monitors' => [
            'class' => 'MonitorResource',
            'description' => 'Monitors: create, read, change, pause, start and delete them, and read their uptime and response time statistics.',
            'handwritten' => true,
            'methods' => [
                'list' => [
                    'operation' => 'MonitorsController_list',
                    'pagination' => 'monitorPages',
                    'all' => 'all',
                    'note' => 'Filters combine with AND; status and tags match any of their values, while every customField entry ("key:value") must match, and the groupId 0 selects the monitors in no group (verified live). all() requests pages of 200 monitors, the most the API allows.',
                ],
                'get' => ['operation' => 'MonitorsController_get'],
                'create' => [
                    'operation' => 'MonitorsController_create',
                    'parameters' => ['@body' => 'monitor'],
                    'note' => 'Pass the model of the monitor type, e.g. HttpMonitorCreate, which sends its type. A new monitor is STARTED until its first check and, unless they are set, reports authType HTTP_BASIC and httpMethodType null (verified live). The deprecated region string regionalData is left out; set the regions with regionData.',
                ],
                'update' => [
                    'operation' => 'MonitorsController_update',
                    'parameters' => ['@body' => 'changes'],
                    'note' => 'Only the properties that are set are sent. config is merged into the current settings key by key (see MonitorConfigUpdate; null clears them), while customHttpHeaders, customFields and successHttpResponseCodes replace the current values (verified live).',
                ],
                'delete' => [
                    'operation' => 'MonitorsController_delete',
                    'note' => 'The monitor is gone at once; later calls for it fail with a NotFoundException (verified live).',
                ],
                'pause' => ['operation' => 'MonitorsController_pause', 'note' => 'Returns the monitor with the status PAUSED.'],
                'start' => ['operation' => 'MonitorsController_start', 'note' => 'Returns the monitor, with the status STARTED until its next check (verified live).'],
                'reset' => ['operation' => 'MonitorsController_reset'],
                'uptimeStats' => ['operation' => 'MonitorsController_getUptimeStats', 'handwritten' => true],
                'uptime' => [
                    'operation' => 'MonitorsController_getMonitorUptimeStats',
                    'note' => 'The uptime is a percentage from 0 to 100, unlike the fraction that uptimeStats() reports. to without from is rejected with "Maximum range is 90 days" (both verified live).',
                ],
                'responseTimeStats' => [
                    'operation' => 'MonitorsController_getMonitorResponseTimeStats',
                    'note' => 'Times are in milliseconds. Pass from and to together or neither: from alone is rejected with "to must be a Date instance", to alone with "Maximum range is 90 days". timeSeries is empty unless includeTimeSeries is true; its points summarize longer intervals for longer ranges (all verified live).',
                ],
                'responseTimeStatsByRegion' => [
                    'operation' => 'MonitorsController_getMonitorResponseTimeStatsByRegion',
                    'note' => 'Times are in milliseconds. Pass from and to together or neither, as for responseTimeStats(). The regions the monitor is not checked from are null (verified live).',
                ],
            ],
        ],
        // Hand-written: the API needs a groupId, a tagId or both, which the
        // specification says in prose only ("At least one of groupId or tagId
        // must be provided"). The methods reject a request without either before
        // it is sent, as what the API does with it was not tried: bulk
        // operations change the monitors of the account.
        'bulkMonitors' => [
            'class' => 'BulkMonitorResource',
            'description' => 'Pause, start or change the monitors of a monitor group and/or with a tag at once.',
            'handwritten' => true,
            'methods' => [
                'pause' => ['operation' => 'BulkMonitorsController_bulkPause', 'handwritten' => true],
                'start' => ['operation' => 'BulkMonitorsController_bulkStart', 'handwritten' => true],
                'update' => ['operation' => 'BulkMonitorsController_bulkUpdate', 'handwritten' => true],
            ],
        ],
        'monitorGroups' => [
            'class' => 'MonitorGroupResource',
            'description' => 'Monitor groups: named sets of monitors. A monitor is in one group at most; the monitors in none report the groupId 0.',
            'methods' => [
                'list' => [
                    'operation' => 'MonitorGroupsController_list',
                    'pagination' => 'nextLink',
                    'all' => 'all',
                    'note' => 'The groups carry neither their monitors nor their number; MonitorResource::list() with groupId lists the monitors of a group (verified live).',
                ],
                'get' => [
                    'operation' => 'MonitorGroupsController_get',
                    'note' => 'An unknown ID raises a NotFoundException, and so does 0, the groupId of the monitors in no group (verified live).',
                ],
                'create' => [
                    'operation' => 'MonitorGroupsController_create',
                    'parameters' => ['@body' => 'group'],
                    'note' => 'The monitors of monitorIds and of the groups in groupIds are moved into the new group.',
                ],
                'update' => [
                    'operation' => 'MonitorGroupsController_update',
                    'parameters' => ['@body' => 'changes'],
                    'note' => 'Only the name can be changed; MonitorResource::update() moves a monitor to another group with groupId.',
                ],
                'delete' => [
                    'operation' => 'MonitorGroupsController_delete',
                    // monitorsNewGroupId has a minimum of 1 (verified live: 0 is rejected
                    // with "monitorsNewGroupId must be a positive number"); an unknown ID
                    // answers 404 "Resource you were trying to access is not found."
                    // (verified live).
                    'note' => 'The monitors of the group are moved to monitorsNewGroupId, or to no group (groupId 0) without it; monitorsNewGroupId 0 is rejected with a BadRequestException. An unknown ID raises a NotFoundException (both verified live).',
                ],
            ],
        ],
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
        'IncidentsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_listComments' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_createComment' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_getActivityLog' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_getAlerts' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_updateComment' => 'Pending: implemented resource by resource in the following commits.',
        'IncidentsController_deleteComment' => 'Pending: implemented resource by resource in the following commits.',
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
