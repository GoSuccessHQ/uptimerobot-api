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
// Maintenance windows. The account has none and none may be created, so only
// validator messages were observable live; see 'types'.
$windowDays = 'The days a weekly or monthly window recurs on. Weekly: 1 = Monday to 7 = Sunday according to the official Terraform provider, whose acceptance tests store 7; the specification only gives [2, 4, 5] for Tuesday, Thursday and Friday, which 0 = Sunday would fit as well. Monthly: 1 to 31, or -1 for the last day of the month. The API\'s validator accepts any number (verified live: 0, 8, 32 and -2); according to the provider, the API ignores invalid days.';
$windowTime = 'The start time as HH:mm:ss, e.g. "14:30:00". The specification names no time zone.';
// Incidents. The specification documents neither the cause codes nor the
// values of status and type; the codes below are those of the 14 incidents
// of the account, each next to its reason (verified live).
$incidentId = 'The ID, a string of digits: incident IDs exceed the integers a JSON number holds exactly (verified live: "358532761126055015").';
$incidentCause = 'The cause code, which reason spells out. Verified live: the HTTP status code of an HTTP error (403 "403 Forbidden"), 333333 ("Connection Timeout"), 444444 ("No Response") and 0 for a slow response ("Response time"). The specification documents none of them.';
$incidentStatus = 'The status of the incident; the specification documents no values (verified live: Resolved).';
$alertRecipient = 'The address the alert went to: an e-mail address, a phone number or, for the mobile app, the device token (verified live: a device token).';
$alertChannel = 'The channel of the alert contact, e.g. MobileApp (verified live); the specification documents no values.';
// Incident comments need the plan feature incident-comments, which the
// account lacks: every comment endpoint answers 403 "Feature incident-comments
// is not enabled in your plan." (000-003), even for an unknown incident and a
// body the validator would reject (verified live). Everything else about them
// comes from the specification.
$commentPlan = 'Requires the plan feature incident-comments; without it, the API raises a ForbiddenException with the code 000-003 before it looks at the incident or the request (verified live). Not verified live beyond that: the test account lacks the feature.';
// Status pages. The account has none, and the owner allows none to be
// created, so the response shape is the specification's, read with the
// official Terraform provider (uptimerobot/terraform-provider-uptimerobot,
// internal/client/psp.go and internal/provider/psp); the request constraints
// come from the messages of requests the validator rejected (verified live:
// PATCH /psps/999999999 and POST /psps with invalid bodies, as JSON and as
// multipart/form-data).
$pageLayout = ['logo_on_left', 'logo_on_center'];
$pageTheme = ['light', 'dark'];
$pageDensity = ['normal', 'compact'];
$statusPageMonitors = 'The monitors on the page; [0] alone stands for every monitor of the account, including those added later, and an empty list removes every monitor. Wins over autoAddMonitors when both are sent.';

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

        // Maintenance windows; MaintenanceWindowDto is named above.
        'CreateMaintenanceWindowDto' => 'MaintenanceWindowCreate',
        'UpdateMaintenanceWindowDto' => 'MaintenanceWindowUpdate',

        // Incidents: the items of a page carry type, monitor and commentsCount,
        // a single incident its root cause instead (verified live).
        'IncidentSummaryPaginationDto.data[]' => 'IncidentSummary',
        'IncidentSummaryPaginationDto.data[].monitor' => 'IncidentMonitor',
        'IncidentDetailDto' => 'Incident',
        'IncidentDetailDto.rootCause' => 'IncidentRootCause',
        'IncidentDetailDto.rootCause.assertionDiagnostics' => 'AssertionDiagnostics',
        'IncidentDetailDto.rootCause.assertionDiagnostics.summary' => 'AssertionDiagnosticsSummary',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[]' => 'AssertionResult',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[].failingSamples[]' => 'AssertionFailingSample',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode' => 'RemoteNode',
        'PublicSentAlertsResponseDto.data[]' => 'SentAlert',

        // Incident comments; the create and update requests are identical.
        'IncidentCommentDto' => 'IncidentComment',
        'IncidentCommentDto.user' => 'IncidentCommentUser',
        'CreateIncidentCommentRequestDto' => 'IncidentCommentCreate',
        'UpdateIncidentCommentRequestDto' => 'IncidentCommentUpdate',

        // Status pages; PspDto is StatusPage (above). The requests describe the
        // design with other types than the response (booleans against the
        // strings "true" and "false", all optional), so they get models of their
        // own: the names of the response models plus Input, as bunny-api names
        // a request copy (OptimizerClassInput).
        'CreatePsPDto' => 'StatusPageCreate',
        'UpdatePspDto' => 'StatusPageUpdate',
        'CustomSettingsDto' => 'StatusPageCustomSettingsInput',
        'FontCustomSettingsDto' => 'StatusPageCustomSettingsFontInput',
        'PageCustomSettingsDto' => 'StatusPageCustomSettingsPageInput',
        'ColorsCustomSettingsDto' => 'StatusPageCustomSettingsColorsInput',
        'FeaturesCustomSettingsDto' => 'StatusPageCustomSettingsFeaturesInput',
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
        'FeaturesCustomSettingsDto.showMonitorURL' => 'showMonitorUrl',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode.IP' => 'ip',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode.IPv6' => 'ipv6',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode.privateIP' => 'privateIp',
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
        // The response, create and update copies; the response names its cases
        // like its values (x-enumNames), the requests derive the same names.
        '*MaintenanceWindowDto.interval' => 'MaintenanceWindowInterval',
        '*MaintenanceWindowDto.status' => 'MaintenanceWindowStatus',
        // The design of a status page: the same lower-case values in the
        // requests and the response (see 'types').
        'PspDto.customSettings.page.layout' => 'StatusPageLayout',
        'PageCustomSettingsDto.layout' => 'StatusPageLayout',
        'PspDto.customSettings.page.theme' => 'StatusPageTheme',
        'PageCustomSettingsDto.theme' => 'StatusPageTheme',
        'PspDto.customSettings.page.density' => 'StatusPageDensity',
        'PageCustomSettingsDto.density' => 'StatusPageDensity',
        // The value sets of the requests (see 'types'); the response reports no
        // sort, and its status as a string.
        'CreatePsPDto.status' => 'StatusPageStatus',
        'UpdatePspDto.status' => 'StatusPageStatus',
        'CreatePsPDto.sort' => 'StatusPageSort',
        'UpdatePspDto.sort' => 'StatusPageSort',
        // The assertion diagnostics of an incident share the logic and the
        // operators of the assertions an API monitor is created with; the
        // response names its cases in upper case (x-enumNames), which become the
        // same names.
        'IncidentDetailDto.rootCause.assertionDiagnostics.logic' => 'AssertionLogic',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[].comparison' => 'AssertionComparison',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[].source' => 'AssertionSource',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[].actualTypes[]' => 'AssertionValueType',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[].targetType' => 'AssertionValueType',
        'IncidentDetailDto.rootCause.assertionDiagnostics.results[].failureReason' => 'AssertionFailureReason',
        // The four regions of the monitors (verified live: oc and na).
        'ActivityLogResponseDto.data[]*.region' => 'Region',
        // The same two values in both (see 'types'; verified live: SUCCESS and
        // NOT_DELIVERED).
        'ActivityLogResponseDto.data[]<NOTIFICATION>.notificationStatus' => 'AlertDeliveryStatus',
        'PublicSentAlertsResponseDto.data[].status' => 'AlertDeliveryStatus',
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
        // Maintenance windows, as monitors embed them. Always empty on this
        // account, so nothing here is verified live.
        'MaintenanceWindowDto.created' => $date,

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

        // Maintenance windows. The account has none, and the owner allows none
        // to be created, so the response shape is the specification's; the
        // request constraints below come from the messages of requests the
        // validator rejected (verified live). The items of a page are an inline
        // copy of MaintenanceWindowDto.
        'MaintenanceWindowPaginationDto.data[]' => ['$ref' => '#/components/schemas/MaintenanceWindowDto'],
        // A string without description in the specification. The official
        // Terraform provider sends the ID of the last window of the previous
        // page and reads it from nextLink as an integer, as the other lists do;
        // live, cursor=abc was ignored (200) on the empty list.
        'MaintenanceWindowsController_list.cursor' => ['type' => 'integer'],
        // Plain strings in their own formats (below); a DateTimeInterface would
        // need a time zone, which the specification does not name.
        'MaintenanceWindowDto.date' => ['type' => 'string', 'description' => 'The start date as YYYY-MM-DD, e.g. "2024-06-20", or null. The specification names no time zone.'],
        'MaintenanceWindowDto.time' => ['type' => 'string', 'description' => $windowTime],
        'MaintenanceWindowDto.duration' => ['type' => 'number', 'description' => 'Minutes the window lasts.'],
        'MaintenanceWindowDto.days' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => $windowDays],
        // The provider's documentation: "Use [0] to auto-add all monitors"; it
        // then sends autoAddMonitors true as well.
        'MaintenanceWindowDto.monitorIds' => [
            'type' => 'array',
            'items' => ['type' => 'number'],
            'description' => 'The monitors in the window. The official Terraform provider treats [0] as all monitors, together with autoAddMonitors (not verified live).',
        ],
        'MaintenanceWindowDto.autoAddMonitors' => ['type' => 'boolean', 'description' => 'Whether all monitors are added to the window automatically.'],
        // From the description of UpdateMaintenanceWindowDto.status.
        'MaintenanceWindowDto.status' => [
            'type' => 'string',
            'enum' => ['active', 'paused'],
            'description' => 'active: the window suppresses the alerts of its monitors during its periods; paused: it does not.',
        ],
        // Neither validator checks the date: a create request without it and an
        // update with "2024-13-45" drew no message about it (verified live).
        'CreateMaintenanceWindowDto.date' => [
            'type' => 'string',
            'description' => 'The start date as YYYY-MM-DD (years 19xx and 20xx), e.g. "2024-06-20". The specification names no time zone.',
        ],
        'UpdateMaintenanceWindowDto.date' => [
            'type' => 'string',
            'description' => 'The start date as YYYY-MM-DD (years 19xx and 20xx), e.g. "2024-06-20". The specification names no time zone.',
        ],
        // A regular expression the validator applies (verified live: "25:00:00" is
        // rejected with "time must match /^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/
        // regular expression"); the specification writes it as a JavaScript
        // literal with slashes.
        'CreateMaintenanceWindowDto.time' => ['type' => 'string', 'description' => $windowTime],
        'UpdateMaintenanceWindowDto.time' => ['type' => 'string', 'description' => $windowTime],
        // Verified live: 0 is rejected with "duration must not be less than 1".
        'CreateMaintenanceWindowDto.duration' => ['type' => 'number', 'description' => 'Minutes the window lasts, at least 1.'],
        'UpdateMaintenanceWindowDto.duration' => ['type' => 'number', 'description' => 'Minutes the window lasts, at least 1.'],
        'CreateMaintenanceWindowDto.days' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => $windowDays],
        'UpdateMaintenanceWindowDto.days' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => $windowDays],
        // The provider sends the whole list and waits until the window reports
        // exactly it, which only fits a replacement.
        'UpdateMaintenanceWindowDto.monitorIds' => [
            'type' => 'array',
            'items' => ['type' => 'number'],
            'description' => 'The monitors in the window, presumably replacing the current ones: the official Terraform provider sends the whole list and expects the window to report exactly it (not verified live).',
        ],

        // Incidents, verified live on the 14 incidents of the account (all
        // resolved: downtimes of HTTP, keyword and ping monitors, and slow
        // responses). The list filters take ISO 8601 ("started_after must be a
        // Date instance" otherwise, verified live).
        'IncidentsController_list.started_after' => $date,
        'IncidentsController_list.started_before' => $date,
        'IncidentSummaryPaginationDto.data[].id' => ['type' => 'string', 'description' => $incidentId],
        // Both erased to {}; no values are documented, so they stay strings.
        'IncidentSummaryPaginationDto.data[].status' => ['type' => 'string', 'description' => $incidentStatus],
        'IncidentSummaryPaginationDto.data[].type' => [
            'type' => 'string',
            'description' => 'The kind of incident; the specification documents no values (verified live: Downtime and SlowResponse).',
        ],
        'IncidentSummaryPaginationDto.data[].cause' => ['type' => 'number', 'description' => $incidentCause],
        // Erased to {}: ISO 8601 in UTC (verified live: "2026-09-16T08:41:11.469Z").
        'IncidentSummaryPaginationDto.data[].startedAt' => $date,
        'IncidentSummaryPaginationDto.data[].resolvedAt' => [
            ...$date,
            'description' => 'When the incident ended; null according to the specification, presumably while it lasts (every incident read live was resolved).',
        ],
        // 1867 for an incident from 08:41:11.469 to 09:12:18.469 (verified live).
        'IncidentSummaryPaginationDto.data[].duration' => ['type' => 'number', 'description' => 'Seconds from startedAt to resolvedAt (verified live).'],
        'IncidentSummaryPaginationDto.data[].includeInReports' => [
            'type' => 'boolean',
            'description' => 'Whether the incident counts in the uptime reports (verified live: false for slow responses, true for downtimes).',
        ],
        'IncidentDetailDto.id' => ['type' => 'string', 'description' => $incidentId],
        'IncidentDetailDto.status' => ['type' => 'string', 'description' => $incidentStatus],
        'IncidentDetailDto.cause' => ['type' => 'number', 'description' => $incidentCause],
        // zod's Date | string: ISO 8601 in UTC (verified live).
        'IncidentDetailDto.startedAt' => $date,
        'IncidentDetailDto.resolvedAt' => [...$date, 'description' => 'When the incident ended; null according to the specification, presumably while it lasts.'],
        'IncidentDetailDto.duration' => ['type' => 'number', 'description' => 'Seconds from startedAt to resolvedAt (verified live).'],
        'IncidentDetailDto.rootCause.url' => [
            'type' => 'string',
            'description' => 'The HTTP method and the URL of the failed check, e.g. "GET https://example.com/"; empty for a slow response (verified live).',
        ],
        // additionalProperties {}: header names with string values (verified
        // live), or null for a slow response, which reads as empty.
        'IncidentDetailDto.rootCause.requestHeaders' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'string'],
            'description' => 'The headers the check sent, by name; empty for a slow response (verified live).',
        ],
        // additionalProperties {}: header names with a list of values each
        // (verified live: {"Content-Type": ["text/html"]}), {} when no response
        // came, or null for a slow response, which reads as empty.
        'IncidentDetailDto.rootCause.responseHeaders' => [
            'type' => 'object',
            'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
            'description' => 'The headers of the response, each name with the list of its values; empty if no response came and for a slow response (verified live).',
        ],
        'IncidentDetailDto.rootCause.httpResponseCode' => [
            'type' => 'number',
            'description' => 'The HTTP status code of the response; null if no response came or for a slow response (verified live).',
        ],
        'IncidentDetailDto.rootCause.responseDownloadUrl' => [
            'type' => 'string',
            'description' => 'Where to download the response body; empty or null if there is none (verified live; never set on the incidents read).',
        ],
        // The activity log. A union of three entry types (see 'unions');
        // STATUS_UPDATE and NOTIFICATION were seen live, COMMENT needs the plan
        // feature incident-comments.
        'ActivityLogResponseDto.data[]*.date' => $date,
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.alertLogType' => [
            'type' => 'string',
            'description' => 'What the check found; the specification documents no values (verified live: Down, Slow and Up).',
        ],
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.incidentStatus' => [
            'type' => 'string',
            'description' => 'The status of the incident; null if absent, as on every entry read live.',
        ],
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.cause' => [
            'type' => 'number',
            'description' => 'The cause code, as of the incident; 0 on the Up entry that ends it ("Monitor is UP", verified live).',
        ],
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.responseTime' => [
            'type' => 'number',
            'description' => 'The response time in milliseconds; only on Slow entries (verified live: 3503).',
        ],
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode.id' => ['type' => 'number', 'description' => 'The ID of the node; 0 on every node read live.'],
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode.IPv6' => [
            'type' => 'string',
            'description' => 'The IPv6 address of the node; some nodes report a private IPv4 address here, e.g. 10.0.4.16 (verified live).',
        ],
        'ActivityLogResponseDto.data[]<NOTIFICATION>.notificationType' => ['type' => 'string', 'description' => $alertChannel],
        'ActivityLogResponseDto.data[]<NOTIFICATION>.sentToValue' => ['type' => 'string', 'description' => $alertRecipient],
        // NOT_DELIVERED, SUCCESS here, SUCCESS, NOT_DELIVERED in the sent
        // alerts; one enum for both.
        'ActivityLogResponseDto.data[]<NOTIFICATION>.notificationStatus' => ['type' => 'string', 'enum' => ['SUCCESS', 'NOT_DELIVERED']],
        // A plain string: ISO 8601 in UTC (verified live: "2026-08-25T19:30:31.000Z").
        'PublicSentAlertsResponseDto.data[].timestamp' => [...$date, 'description' => 'When the alert was sent.'],
        'PublicSentAlertsResponseDto.data[].channelType' => ['type' => 'string', 'description' => $alertChannel],
        'PublicSentAlertsResponseDto.data[].recipientValue' => ['type' => 'string', 'description' => $alertRecipient],

        // Incident comments, from the specification (see $commentPlan). The items
        // of a page are an inline copy of IncidentCommentDto.
        'PublicIncidentCommentsPaginationDto.data[]' => ['$ref' => '#/components/schemas/IncidentCommentDto'],
        // A string of digits in the specification ("Cursor to paginate through
        // comments (comment ID)"), but comment IDs are numbers everywhere else:
        // IncidentCommentDto.id, the commentId of the update and delete paths and
        // of the activity log. An int lets a comment's ID be passed as is.
        'IncidentsController_listComments.cursor' => ['type' => 'integer'],
        // zod's Date | string, as the other dates, which are ISO 8601 in UTC.
        'IncidentCommentDto.created' => [...$date, 'description' => 'When the comment was written.'],
        // "content" in the requests, "comment" in the responses.
        'IncidentCommentDto.comment' => ['type' => 'string', 'description' => 'The text of the comment, which create() and update() send as content.'],
        'CreateIncidentCommentRequestDto.content' => ['type' => 'string', 'description' => 'The text of the comment, at most 10000 characters; responses name it comment.'],
        'CreateIncidentCommentRequestDto.publishOnStatusPage' => ['type' => 'boolean', 'description' => 'Whether the comment is published on the status page; false by default.'],
        'UpdateIncidentCommentRequestDto.content' => [
            'type' => 'string',
            'description' => 'The new text of the comment, at most 10000 characters. Required: an update always replaces the text.',
        ],
        'UpdateIncidentCommentRequestDto.publishOnStatusPage' => [
            'type' => 'boolean',
            'description' => 'Whether the comment is published on the status page. The specification gives false as its default, so leaving it out may unpublish the comment.',
        ],

        // Status pages (see $pageLayout). The items of a page are an inline copy
        // of PspDto (identical); monitors embed the same model.
        'PspPaginationDto.data[]' => ['$ref' => '#/components/schemas/PspDto'],
        // Erased to {}. ENABLED and PAUSED are the values of the requests; the
        // official Terraform provider reads the response into an attribute it
        // validates against them, and its tests mock "ENABLED". Not observable
        // live, so the status stays a string.
        'PspDto.status' => [
            'type' => 'string',
            'description' => 'ENABLED if the page is published, PAUSED if not, as the requests name the values; the specification documents none for the response (not verified live).',
        ],
        // The provider reads [0] as "all monitors, also future ones" and derives
        // its auto_add_monitors from it, since the response has no
        // autoAddMonitors.
        'PspDto.monitorIds' => [
            'type' => 'array',
            'items' => ['type' => 'number'],
            'description' => 'The monitors on the page; [0] means every monitor of the account, the setting autoAddMonitors of the requests, according to the official Terraform provider (not verified live). Empty if null.',
        ],
        'PspDto.isPasswordSet' => ['type' => 'boolean', 'description' => 'Whether visitors need a password; the API never returns the password itself.'],
        'PspDto.logo' => ['type' => 'string', 'description' => 'The uploaded logo as the API reports it, presumably its URL; null without one (not verified live).'],
        'PspDto.icon' => ['type' => 'string', 'description' => 'The uploaded icon as the API reports it, presumably its URL; null without one (not verified live).'],
        'PspDto.pinnedAnnouncementId' => ['type' => 'integer', 'description' => 'The announcement pinned to the page; null if none.'],
        // The validator of the requests takes these lower-case values only,
        // unlike the title-case enums of PageCustomSettingsDto (verified live:
        // "Light", "LogoOnLeft" and "Compact" are rejected with "customSettings.
        // page.theme must be one of the following values: light, dark", "...
        // layout ...: logo_on_left, logo_on_center" and "... density ...:
        // normal, compact"). The response documents theme and density in lower
        // case and layout as a plain string; the provider's acceptance tests set
        // logo_on_left, dark and compact and read them back.
        'PspDto.customSettings.page.layout' => [
            'type' => 'string',
            'enum' => $pageLayout,
            'description' => 'Where the logo sits. The requests take the same lower-case values, not the title-case ones of the specification (verified live).',
        ],
        'PspDto.customSettings.page.theme' => [
            'type' => 'string',
            'enum' => $pageTheme,
            'description' => 'The color theme. The requests take the same lower-case values, not the title-case ones of the specification (verified live).',
        ],
        'PspDto.customSettings.page.density' => [
            'type' => 'string',
            'enum' => $pageDensity,
            'description' => 'The density of the page. The requests take the same lower-case values, not the title-case ones of the specification (verified live).',
        ],
        'PageCustomSettingsDto.layout' => ['type' => 'string', 'enum' => $pageLayout],
        'PageCustomSettingsDto.theme' => ['type' => 'string', 'enum' => $pageTheme],
        'PageCustomSettingsDto.density' => ['type' => 'string', 'enum' => $pageDensity],
        // The strings "true" and "false" in the specification, booleans at times
        // according to the provider ("tolerating inconsistent api response
        // values"); a bool reads both.
        'PspDto.customSettings.features.*' => [
            'type' => 'boolean',
            'description' => 'Sent as the string "true" or "false", or as a boolean; null if not set.',
        ],
        // Verified live: -1 is rejected with "each value in monitorIds must not
        // be less than 0", 0 in tagIds with "each value in tagIds must not be
        // less than 1".
        'CreatePsPDto.monitorIds' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => $statusPageMonitors],
        'UpdatePspDto.monitorIds' => [
            'type' => 'array',
            'items' => ['type' => 'number'],
            'description' => $statusPageMonitors . ' Presumably replaces the current monitors, as the empty list suggests (not verified live).',
        ],
        // The response has no autoAddMonitors; the provider sends monitorIds [0]
        // for it and reads [0] back as it.
        'CreatePsPDto.autoAddMonitors' => [
            'type' => 'boolean',
            'description' => 'Whether every monitor of the account, also those added later, is shown on the page. An explicit monitorIds wins over it, even an empty one. Responses do not report it; they list monitorIds [0] instead, according to the official Terraform provider (not verified live).',
        ],
        'UpdatePspDto.autoAddMonitors' => [
            'type' => 'boolean',
            'description' => 'Whether every monitor of the account, also those added later, is shown on the page. An explicit monitorIds wins over it, even an empty one. Responses do not report it; they list monitorIds [0] instead, according to the official Terraform provider (not verified live).',
        ],
        'CreatePsPDto.tagIds' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'The tags assigned to the page.'],
        'UpdatePspDto.tagIds' => ['type' => 'array', 'items' => ['type' => 'number'], 'description' => 'The tags assigned to the page.'],
        // The validator turns the names into the numbers 1 to 4 and matches them
        // case-insensitively (verified live: "FriendlyNameAsc" and
        // "friendlynameasc" pass, "Manual" is rejected with "sort must be one of
        // the following values: 1, 2, 3, 4"); the provider sends the numbers.
        'CreatePsPDto.sort' => [
            'type' => 'string',
            'enum' => ['FriendlyNameAsc', 'FriendlyNameDesc', 'StatusUpDownPaused', 'StatusDownUpPaused'],
            'description' => 'How the monitors are ordered on the page. A manual order can only be arranged in the dashboard. Responses do not report the order.',
        ],
        'UpdatePspDto.sort' => [
            'type' => 'string',
            'enum' => ['FriendlyNameAsc', 'FriendlyNameDesc', 'StatusUpDownPaused', 'StatusDownUpPaused'],
            'description' => 'How the monitors are ordered on the page. A manual order can only be arranged in the dashboard. Responses do not report the order.',
        ],
        // Likewise: "ENABLED" and "enabled" pass, "FOO" is rejected with "status
        // must be one of the following values: 0, 1" (verified live).
        'CreatePsPDto.status' => ['type' => 'string', 'enum' => ['ENABLED', 'PAUSED'], 'description' => 'ENABLED publishes the page, PAUSED takes it offline.'],
        'UpdatePspDto.status' => ['type' => 'string', 'enum' => ['ENABLED', 'PAUSED'], 'description' => 'ENABLED publishes the page, PAUSED takes it offline.'],
        'CreatePsPDto.password' => ['type' => 'string', 'description' => 'A password visitors must enter, at most 255 characters. Responses only report isPasswordSet.'],
        'UpdatePspDto.password' => ['type' => 'string', 'description' => 'A password visitors must enter, at most 255 characters. Responses only report isPasswordSet.'],
        // Verified live: "nope" is rejected with "gaCode must match
        // /G-[A-Z0-9]{10}/ regular expression".
        'CreatePsPDto.gaCode' => ['type' => 'string', 'description' => 'The Google Analytics measurement ID, "G-" and 10 upper-case letters or digits; for a page with a custom domain only.'],
        'UpdatePspDto.gaCode' => ['type' => 'string', 'description' => 'The Google Analytics measurement ID, "G-" and 10 upper-case letters or digits; for a page with a custom domain only.'],
        'UpdatePspDto.pinnedAnnouncementId' => ['type' => 'number', 'description' => 'The announcement to pin to the page, as AnnouncementResource::pin() does.'],
        // Whether an update merges the design into the current one or replaces
        // it could not be tried. The provider always sends page, colors and
        // features along, as empty objects if unset; the validator does not ask
        // for them (verified live).
        'CreatePsPDto.customSettings' => ['$ref' => '#/components/schemas/CustomSettingsDto', 'description' => 'The design of the page.'],
        'UpdatePspDto.customSettings' => [
            '$ref' => '#/components/schemas/CustomSettingsDto',
            'description' => 'The design of the page. Whether it is merged into the current design or replaces it is not documented (not verified live).',
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
        '*MaintenanceWindowDto.duration',
        '*MaintenanceWindowDto.days[]',
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
        // Incidents: cause codes, seconds, counts and milliseconds (verified live,
        // e.g. cause 333333, duration 1867, commentsCount 0, httpResponseCode 403,
        // responseTime 3503).
        'IncidentSummaryPaginationDto.data[].cause',
        'IncidentSummaryPaginationDto.data[].commentsCount',
        'IncidentSummaryPaginationDto.data[].duration',
        'IncidentDetailDto.cause',
        'IncidentDetailDto.duration',
        'IncidentDetailDto.rootCause.httpResponseCode',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.cause',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.responseTime',
        // Always 1 (minimum and maximum 1).
        'IncidentDetailDto.rootCause.assertionDiagnostics.version',
        // A monitor ID, at least 1 (verified live: 0 is rejected with
        // "monitor_id must be a positive number").
        'IncidentsController_list.monitor_id',
        // The number of comments per page, 1 to 100.
        'IncidentsController_listComments.limit',
        // The ID of the last status page of the previous page, as the official
        // Terraform provider reads it from nextLink.
        'PspController_list.cursor',
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
        // Files, which StatusPageResource::create() and update() take as
        // parameters and upload as multipart/form-data.
        'CreatePsPDto.logo',
        'CreatePsPDto.icon',
        'UpdatePspDto.logo',
        'UpdatePspDto.icon',
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
        // Absent from the status updates that lack them (verified live:
        // remoteNode on "Up" entries, responseTime on all but "Slow" ones,
        // incidentStatus on every entry read).
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.remoteNode',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.responseTime',
        'ActivityLogResponseDto.data[]<STATUS_UPDATE>.incidentStatus',
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
        // A oneOf without a discriminator keyword, told apart by its
        // single-value type (verified live: STATUS_UPDATE and NOTIFICATION; COMMENT
        // needs the plan feature incident-comments). An entry of another type is
        // read as UnknownActivityLogEntry with its raw payload.
        'ActivityLogResponseDto.data[]' => [
            'interface' => 'ActivityLogEntry',
            'discriminator' => 'type',
            'variants' => [
                'STATUS_UPDATE' => 'StatusUpdateActivity',
                'COMMENT' => 'CommentActivity',
                'NOTIFICATION' => 'NotificationActivity',
            ],
            'fallback' => 'UnknownActivityLogEntry',
            'description' => 'An entry of the activity log of an incident: a status update of the checks, a comment or a notification.',
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
        // StatusPageResource::create() and update() send them as JSON, or as
        // multipart/form-data together with a logo or an icon.
        'CreatePsPDto',
        'UpdatePspDto',
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
            // In the create request and the response, but not in the update
            // request of the specification. The official Terraform provider
            // sends it in PATCH, and its acceptance test
            // TestAccMaintenanceWindow_AutoAddMonitors_NullAndSet changes it
            // from false to true in place and reads it back. Not verified live:
            // the account has no windows, and the maintenance window DTOs do not
            // reject properties they do not know ("foo" drew no message, verified
            // live), so a probe cannot tell either.
            'UpdateMaintenanceWindowDto.autoAddMonitors' => [
                'type' => 'boolean',
                'description' => 'Whether all monitors are added to the window automatically. Missing from the specification\'s update request; the official Terraform provider changes it this way (not verified live).',
            ],
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
        // GET /incidents/{id}/comments: like nextLink, but all() requests pages
        // of 100 comments, the maximum of the specification ("1-100, default
        // 50"), which saves requests against the rate limit. Not verified live
        // (see $commentPlan).
        'commentPages' => [
            'cursor' => 'cursor',
            'size' => 'limit',
            'allSize' => 100,
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
        'maintenanceWindows' => [
            'class' => 'MaintenanceWindowResource',
            'description' => 'Maintenance windows: one-time or recurring periods that suppress the alerts of the monitors assigned to them.',
            'methods' => [
                'list' => [
                    'operation' => 'MaintenanceWindowsController_list',
                    'pagination' => 'nextLink',
                    'all' => 'all',
                    'note' => 'The cursor is the ID of the last window of the previous page, as the official Terraform provider sends it. Only a single page was observable: an account without windows gets {"data": []} without nextLink (verified live).',
                ],
                'get' => [
                    'operation' => 'MaintenanceWindowsController_get',
                    // Verified live: /maintenance-windows/999999999 answers 404
                    // "Maintenance window not found", /maintenance-windows/1 403
                    // "Not belongs to user" (000-006).
                    'note' => 'An unknown ID raises a NotFoundException, the ID of another account\'s window a ForbiddenException with the code 000-006 (both verified live).',
                ],
                'create' => [
                    'operation' => 'MaintenanceWindowsController_create',
                    'parameters' => ['@body' => 'window'],
                    // The validator rejected every other required property when
                    // they were missing or invalid, but not the missing date
                    // (verified live); the Terraform provider leaves date out
                    // unless one is configured. What a window without a date
                    // does was not observable, so date stays required.
                    'note' => 'Weekly and monthly windows need days. The specification requires date for every interval, although the API\'s validator does not ask for it (verified live). There is no status here; update() pauses a window.',
                ],
                'update' => [
                    'operation' => 'MaintenanceWindowsController_update',
                    'parameters' => ['@body' => 'changes'],
                    'note' => 'Only the properties that are set are sent. The validator runs before the window is looked up (verified live), so an invalid change to an unknown ID raises a BadRequestException, a valid one a NotFoundException.',
                ],
                'delete' => [
                    'operation' => 'MaintenanceWindowsController_delete',
                    'note' => 'An unknown ID raises a NotFoundException (verified live).',
                ],
            ],
        ],
        'incidents' => [
            'class' => 'IncidentResource',
            'description' => 'Incidents: the downtimes and slow responses of the monitors, with their root cause, activity log and the alerts sent. Incident IDs are strings of digits.',
            'methods' => [
                'list' => [
                    'operation' => 'IncidentsController_list',
                    'pagination' => 'nextLink',
                    'all' => 'all',
                    // Verified live: cursor=352577094135060139 returned the
                    // incidents that started before that one; monitor_name=ns
                    // matched "fast.ns" and "quick.ns", "MY." "my.gosuccess.io";
                    // started_after and started_before bound startedAt; all 14
                    // incidents came on one page without nextLink.
                    'note' => 'Newest first. The cursor is the ID of the last incident of the previous page; the incidents that started before it follow. monitorName matches part of the name, ignoring case; startedAfter and startedBefore bound startedAt. The page size is not documented: all 14 incidents of the test account came on one page (all verified live).',
                ],
                'get' => [
                    'operation' => 'IncidentsController_get',
                    'note' => 'Unlike the items of list(), an incident has neither type nor monitor; cause 0 marks a slow response. rootCause, null according to the specification, was present on every incident read, with an empty url and null headers for a slow response; assertionDiagnostics is only expected for API monitors and was always null. An unknown ID raises a NotFoundException (all verified live).',
                ],
                'activityLog' => [
                    'operation' => 'IncidentsController_getActivityLog',
                    'unwrap' => 'data',
                    // {"nextLink": null, "data": [...]}; ?cursor=1 and ?limit=2
                    // returned the same entries (verified live), and the
                    // specification declares neither.
                    'note' => 'Newest first: status updates of the checks (remoteNode is null on Up entries, responseTime only set on Slow ones), notifications and, with the plan feature incident-comments, comments. Only the first page is available: the API reports a nextLink, but ignores cursor and limit (verified live). No incident read had more than 6 entries, and nextLink was always null.',
                ],
                'alerts' => [
                    'operation' => 'IncidentsController_getAlerts',
                    'unwrap' => 'data',
                    'note' => 'Oldest first, all in one response; empty if no alert was sent (verified live).',
                ],
            ],
        ],
        'incidentComments' => [
            'class' => 'IncidentCommentResource',
            'description' => 'Comments on incidents, optionally published on the status page. Requires the plan feature incident-comments.',
            // The incident comes first; the comment itself is $id, as in the
            // other resources.
            'parameters' => ['id' => 'incidentId'],
            'methods' => [
                'list' => [
                    'operation' => 'IncidentsController_listComments',
                    'pagination' => 'commentPages',
                    'all' => 'all',
                    'note' => 'Oldest first, according to the specification. The cursor is the ID of the last comment of the previous page. all() requests pages of 100 comments, the most the specification allows. ' . $commentPlan,
                ],
                'create' => [
                    'operation' => 'IncidentsController_createComment',
                    'parameters' => ['@body' => 'comment'],
                    // "201: Comment created successfully" without a schema,
                    // while update returns IncidentCommentDto. UptimeRobot's
                    // incident-response skill for its MCP server
                    // (github.com/uptimerobot/ai, skills/incident-response)
                    // takes "the numeric commentId from the create response", so
                    // a body is read if the API sends one.
                    'response' => 'IncidentCommentDto',
                    'nullable' => true,
                    'note' => 'The specification documents no response body. This returns the comment if the API sends one, as UptimeRobot\'s incident-response guide for its MCP server takes the commentId "from the create response", and null for an empty response. ' . $commentPlan,
                ],
                'update' => [
                    'operation' => 'IncidentsController_updateComment',
                    'parameters' => ['commentId' => 'id', '@body' => 'changes'],
                    'note' => 'content is required, so the text is always replaced. ' . $commentPlan,
                ],
                'delete' => [
                    'operation' => 'IncidentsController_deleteComment',
                    'parameters' => ['commentId' => 'id'],
                    'note' => $commentPlan,
                ],
            ],
        ],
        // Hand-written: create() and update() upload a logo and an icon as
        // multipart/form-data, which the generator does not build.
        'statusPages' => [
            'class' => 'StatusPageResource',
            'description' => 'Public status pages: the monitors they show, their design and whether they are published.',
            'handwritten' => true,
            'methods' => [
                'list' => [
                    'operation' => 'PspController_list',
                    'pagination' => 'nextLink',
                    'all' => 'all',
                    'note' => 'The cursor is the ID of the last status page of the previous page, as the official Terraform provider reads it from nextLink. Only a single page was observable: an account without status pages gets {"data": []} without nextLink (verified live).',
                ],
                'get' => [
                    'operation' => 'PspController_get',
                    // Verified live: /psps/999999999 answers 404 "PSP not found".
                    'note' => 'An unknown ID raises a NotFoundException (verified live).',
                ],
                'create' => ['operation' => 'PspController_create', 'handwritten' => true],
                'update' => ['operation' => 'PspController_update', 'handwritten' => true],
                'delete' => [
                    'operation' => 'PspController_delete',
                    // Verified live: 404 "Resource you were trying to access is not
                    // found." for /psps/999999999.
                    'note' => 'An unknown ID raises a NotFoundException (verified live).',
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
        'PspAnnouncementsController_list' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_create' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_get' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_update' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_pin' => 'Pending: implemented resource by resource in the following commits.',
        'PspAnnouncementsController_unpin' => 'Pending: implemented resource by resource in the following commits.',
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
